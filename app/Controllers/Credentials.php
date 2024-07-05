<?php

namespace App\Controllers;

use App\Models\CredentialsModel;
use App\Models\NotesModel;
use App\Models\UsersModel;
use CodeIgniter\Controller;

class Credentials extends Controller
{
    protected $credentialsmodel;
    protected $notesmodel;
    protected $usersmodel;

    public function __construct()
    {
        $this->credentialsmodel = new CredentialsModel();
        $this->notesmodel = new NotesModel();
        $this->usersmodel = new UsersModel();

        parent::__construct();

        //$this->access_only_admin(); // Assuming this function restricts access to admins
        $this->init_permission_checker("assets_data"); // Assuming this function initializes permission checks for assets_data
        $this->access_only_allowed_members(); // Assuming this function restricts access based on allowed members
    }

    public function index()
    {
        $this->check_module_availability("module_assets_data");

        if ($this->login_user->is_admin == "1") {
            return view("credentials/index");
        } elseif ($this->login_user->user_type == "staff" || $this->login_user->user_type == "resource") {
            if ($this->access_type != "all" && !in_array($this->login_user->id, $this->allowed_members)) {
                return redirect()->to("forbidden");
            }
            return view("credentials/index");
        } else {
            return view("credentials/index");
        }
    }

    public function modal_form()
    {
        helper(['form', 'validation']);

        validate_submitted_data([
            "id" => "numeric"
        ]);

        $view_data['model_info'] = $this->credentialsmodel->getOne($this->request->getPost('id'));

        $labels = explode(",", $this->notesmodel->get_label_suggestions($this->login_user->id));
        $label_suggestions = [];
        foreach ($labels as $label) {
            if ($label && !in_array($label, $label_suggestions)) {
                $label_suggestions[] = $label;
            }
        }
        if (empty($label_suggestions)) {
            $label_suggestions = ["Important"];
        }
        $view_data['label_suggestions'] = $label_suggestions;

        return view('credentials/modal_form', $view_data);
    }

    public function save()
    {
        helper(['form', 'validation']);

        validate_submitted_data([
            "id" => "numeric",
            "title" => "required",
            "username" => "required",
            "password" => "required"
        ]);

        $id = $this->request->getPost('id');

        $target_path = get_setting("timeline_file_path");
        $files_data = move_files_from_temp_dir_to_permanent_dir($target_path, "credentials");
        $new_files = unserialize($files_data);

        $data = [
            "title" => $this->request->getPost('title'),
            "username" => $this->request->getPost('username'),
            "description" => $this->request->getPost('description'),
            "password" => $this->request->getPost('password'),
            "url" => $this->request->getPost('url'),
            "labels" => $this->request->getPost('labels'),
            "last_activity_user" => $this->login_user->id,
            "last_activity" => get_current_utc_time(),
        ];

        if ($id) {
            $note_info = $this->notesmodel->get_one($id);
            $timeline_file_path = get_setting("timeline_file_path");
            $new_files = update_saved_files($timeline_file_path, $note_info->files, $new_files);
        }

        $data["files"] = serialize($new_files);

        if (!$id) {
            $data['created_date'] = get_current_utc_time();
        }

        $save_id = $this->credentialsmodel->save($data, $id);

        if ($save_id) {
            return $this->response->setJSON(["success" => true, "data" => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(["success" => false, 'message' => lang('error_occurred')]);
        }
    }

    public function delete()
    {
        helper(['form', 'validation']);

        validate_submitted_data([
            "id" => "numeric|required"
        ]);

        $id = $this->request->getPost('id');

        $data = [
            "last_activity_user" => $this->login_user->id,
            "last_activity" => get_current_utc_time(),
        ];

        $save_id = $this->credentialsmodel->save($data, $id);

        if ($this->request->getPost('undo')) {
            if ($this->credentialsmodel->delete($id, true)) {
                return $this->response->setJSON(["success" => true, "data" => $this->_row_data($id), "message" => lang('record_undone')]);
            } else {
                return $this->response->setJSON(["success" => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($this->credentialsmodel->delete($id)) {
                return $this->response->setJSON(["success" => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->response->setJSON(["success" => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }

    public function list_data()
    {
        $list_data = $this->credentialsmodel->get_details()->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        return $this->response->setJSON(["data" => $result]);
    }

    private function _row_data($id)
    {
        $options = ["id" => $id];
        $data = $this->credentialsmodel->get_details($options)->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        helper('language');

        $title = $data->title;
        $note_labels = "";

        if ($data->labels) {
            $labels = explode(",", $data->labels);
            foreach ($labels as $label) {
                $note_labels .= "<span class='label label-info clickable'>" . $label . "</span> ";
            }
            $title .= "<br />" . $note_labels;
        }

        $last_activity_by_user_name = "-";
        if ($data->last_activity_user) {
            $last_activity_user_data = $this->usersmodel->get_one($data->last_activity_user);
            $last_activity_image_url = get_avatar($last_activity_user_data->image);
            $last_activity_user = "<span class='avatar avatar-xs mr10'><img src='$last_activity_image_url' alt='...'></span> $last_activity_user_data->first_name $last_activity_user_data->last_name";

            if ($last_activity_user_data->user_type == "resource") {
                $last_activity_by_user_name = get_rm_member_profile_link($data->last_activity_user, $last_activity_user);
            } else if ($last_activity_user_data->user_type == "client") {
                $last_activity_by_user_name = get_client_contact_profile_link($data->last_activity_user, $last_activity_user);
            } else if ($last_activity_user_data->user_type == "staff") {
                $last_activity_by_user_name = get_team_member_profile_link($data->last_activity_user, $last_activity_user);
            } else if ($last_activity_user_data->user_type == "vendor") {
                $last_activity_by_user_name = get_vendor_contact_profile_link($data->last_activity_user, $last_activity_user);
            }
        }

        $last_activity_date = "-";
        if ($data->last_activity) {
            $last_activity_date = format_to_relative_time($data->last_activity);
        }

        $website_link = "";
        if ($data->url) {
            $website_address = to_url($data->url);
            $website_link .= "<a target='_blank' href='$website_address'>$data->url</a>";
        }

        $files_link = "";
        if ($data->files) {
            $files = unserialize($data->files);
            if (!empty($files)) {
                foreach ($files as $file) {
                    $file_name = get_array_value($file, "file_name");
                    $link = " fa fa-" . get_file_icon(strtolower(pathinfo($file_name, PATHINFO_EXTENSION)));
                    $files_link .= js_anchor(" ", ['title' => "", 'data-toggle' => "app-modal", 'data-sidebar' => "0", 'class' => "pull-left font-22 $link", 'title' => remove_file_prefix($file_name), 'data-url' => route_to("credentials/file_preview/$file_name")]);
                }
            }
        }

        return [
            $data->created_date,
            $title,
            $data->username,
            $data->password,
            $data->description,
            $website_link ? $website_link : "-",
            $files_link,
            $last_activity_by_user_name,
            $last_activity_date,
            modal_anchor(route_to("credentials/modal_form"), "<i class='fa fa-pencil'></i>", ["class" => "edit", "title" => lang('edit_credential'), "data-post-id" => $data->id])
                . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_credential'), "class" => "delete", "data-id" => $data->id, "data-action-url" => route_to("credentials/delete"), "data-action" => "delete-confirmation"])
        ];
    }

    public function file_preview($file_name = "")
    {
        if ($file_name) {
            $view_data["file_url"] = get_file_uri(get_setting("timeline_file_path") . $file_name);
            $view_data["is_image_file"] = is_image_file($file_name);
            $view_data["is_google_preview_available"] = is_google_preview_available($file_name);

            return view("credentials/file_preview", $view_data);
        } else {
            return show_404();
        }
    }

    public function upload_file()
    {
        upload_file_to_temp();
    }

    public function validate_notes_file()
    {
        return validate_post_file($this->request->getPost("file_name"));
    }
}

