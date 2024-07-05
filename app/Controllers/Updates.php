<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;

class Updates extends BaseController
{
    use ResponseTrait;

    public function __construct()
    {
        // Ensure to call the parent constructor
        parent::__construct();

        // Access control or any other initialization
        $this->access_only_admin();
    }

    public function index()
    {
        $updates_info = $this->_get_updates_info();
        $viewData = [
            'installable_updates' => $updates_info->installable_updates,
            'downloadable_updates' => $updates_info->downloadable_updates,
            'current_version' => $updates_info->current_version
        ];

        if (!empty($updates_info->error)) {
            $viewData['error'] = $updates_info->error;
        }

        return view('updates/index', $viewData);
    }

    private function _curl_get_contents($url)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
            CURLOPT_HTTPGET => TRUE,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_USERAGENT => "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)",
            CURLOPT_HTTPHEADER => ['Content-type: text/plain']
        ]);

        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }

    private function _get_updates_info()
    {
        ini_set('max_execution_time', 180);

        $current_version = get_setting("app_version");
        $app_update_url = get_setting("app_update_url");
        $item_purchase_code = get_setting("item_purchase_code");
        $remote_updates_url = $app_update_url . "?code=" . $item_purchase_code . "&domain=" . $_SERVER['HTTP_HOST'];
        $local_updates_dir = get_setting("updates_path");

        $error = "";
        $next_installable_version = "";
        $none_installed_versions = [];
        $installable_updates = [];
        $downloadable_updates = [];

        // Check updates
        $releases = $this->_curl_get_contents($remote_updates_url);

        if ($releases) {
            // Explode the string to get the released versions
            $releases = array_filter(explode("<br />", $releases));

            if ($releases[0] === "varification_failed") {
                $error = lang("varification_failed_message");
            } else {
                // Check non-installed versions
                foreach ($releases as $version_key) {
                    $version_info = $this->_get_version_and_salt($version_key);

                    // Compare current version with updates
                    if (version_compare($version_info->version, $current_version) > 0) {
                        if (!$next_installable_version) {
                            $next_installable_version = $version_info->version;
                        }
                        $none_installed_versions[$version_info->salt] = $version_info->version;
                    }
                }

                // Check the local file if the updates are already downloaded
                foreach ($none_installed_versions as $salt => $version) {
                    $update_zip = $local_updates_dir . $version . '.zip';
                    if (is_file($update_zip)) {
                        $installable_updates[$salt] = $version;
                    } else {
                        $downloadable_updates[$salt] = $version;
                    }
                }
            }
        }

        return (object)[
            'current_version' => $current_version,
            'error' => $error,
            'none_installed_versions' => $none_installed_versions,
            'installable_updates' => $installable_updates,
            'downloadable_updates' => $downloadable_updates,
            'next_installable_version' => $next_installable_version
        ];
    }

    private function _get_version_and_salt($version_key = "")
    {
        $info = new \stdClass();
        $version_array = explode("-", $version_key);
        $info->salt = $version_array[0];
        $info->version = array_key_exists(1, $version_array) ? $version_array[1] : "";

        return $info;
    }

    public function download_updates($version = "", $salt = "")
    {
        $local_updates_dir = get_setting("updates_path");
        $update_zip = $local_updates_dir . $version . ".zip";
        $download_url = get_setting("app_update_url") . $salt . "-" . $version . ".zip";

        if (is_file($update_zip)) {
            return $this->respond([
                'success' => true,
                'message' => "File already exists"
            ]);
        } else {
            // Get updates from remote
            $new_update = $this->_curl_get_contents($download_url);

            if ($new_update) {
                // Create updates folder if required
                if (!is_dir($local_updates_dir)) {
                    if (!@mkdir($local_updates_dir)) {
                        return $this->fail("Permission denied: $local_updates_dir directory is not writeable! Please set the writeable permission to the directory");
                    }
                }

                // Save the downloaded file
                if (file_put_contents($update_zip, $new_update)) {
                    return $this->respond([
                        'success' => true,
                        'message' => "Downloaded version - " . $version
                    ]);
                } else {
                    return $this->fail(lang("something_went_wrong"));
                }
            } else {
                return $this->fail("Sorry, Version - $version download has been failed!");
            }
        }
    }

    public function do_update($version = "")
    {
        ini_set('max_execution_time', 300); // 300 seconds

        if ($version) {
            // Check the sequential updates
            $updates_info = $this->_get_updates_info();

            if ($updates_info->next_installable_version != $version) {
                return $this->fail("Please install the version - $updates_info->next_installable_version first!");
            }

            $local_updates_dir = get_setting("updates_path");

            if (!function_exists("zip_open")) {
                return $this->fail("Please install the zip extension in your server.");
            }

            $zip = zip_open($local_updates_dir . $version . '.zip');

            $executable_file = "";

            while ($active_file = zip_read($zip)) {
                $file_name = zip_entry_name($active_file);
                $dir = dirname($file_name);

                if (substr($file_name, -1, 1) == '/') {
                    continue;
                }

                // Create new directory if it's not exists
                if (!is_dir('./' . $dir)) {
                    mkdir('./' . $dir, 0755, true);
                }

                // Overwrite the existing file
                if (!is_dir('./' . $file_name)) {
                    $contents = zip_entry_read($active_file, zip_entry_filesize($active_file));

                    // Execute command if required
                    if ($file_name == 'execute.php') {
                        file_put_contents($file_name, $contents);
                        $executable_file = $file_name;
                    } else {
                        file_put_contents($file_name, $contents);
                    }
                }
            }

            // If there's an executable file, run it
            if ($executable_file) {
                include_once($executable_file);
                unlink($executable_file); // Delete the file for security purpose and it's not required to keep in root directory
            }

            return $this->respond([
                'success' => true,
                'message' => "Version - $version installed successfully!"
            ]);
        } else {
            return $this->fail(lang("something_went_wrong"));
        }
    }

}

