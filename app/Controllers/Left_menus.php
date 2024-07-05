<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use App\Models\Settings_model;
use App\Libraries\Left_menu;

class Left_menus extends BaseController
{
    use ResponseTrait;

    protected $settingsmodel;

    public function __construct()
    {
        $this->settingsmodel = new Settings_model();
        $this->left_menu = new Left_menu();

        // Assuming access_only_team_members(), access_only_clients(), access_only_vendors(), and access_only_admin() are defined in BaseController or helper functions
        parent::__construct();
    }

    private function check_left_menu_permission($type = "")
    {
        if ($type == "user") {
            if ($this->login_user->user_type == "staff") {
                $this->access_only_team_members();
            } else if ($this->login_user->user_type == "client") {
                $this->access_only_clients();
            } else if ($this->login_user->user_type == "vendor") {
                $this->access_only_vendors();
            }
        } else if (!$type || $type == "client_default" || $type == "vendor_default") {
            $this->access_only_admin();
        }
    }

    public function index($type = "")
    {
        $this->check_left_menu_permission($type);

        $view_data["available_items"] = $this->left_menu->get_available_items($type);
        $view_data["sortable_items"] = $this->left_menu->get_sortable_items($type);
        //$view_data["preview"] = $this->left_menu->rander_left_menu(true, $type);
        $view_data["preview"] = $this->left_menu->rander_left_menu_preview(true, $type);

        if ($type == "user") {
            return view("left_menu/user_left_menu", $view_data);
        } else {
            $view_data["setting_active_tab"] = ($type == "client_default" || $type == "vendor_default") ? $type . "_left_menu" : "left_menu";
            $view_data["type"] = $type;

            return view("left_menu/index", $view_data);
        }
    }

    public function save()
    {
        if (get_setting("disable_editing_left_menu_by_clients") && $this->login_user->user_type == "client") {
            return $this->response->redirect(site_url('forbidden'));
        }

        if (get_setting("disable_editing_left_menu_by_vendorss") && $this->login_user->user_type == "vendor") {
            return $this->response->redirect(site_url('forbidden'));
        }

        $type = $this->request->getPost("type");
        $this->check_left_menu_permission($type);

        $items_data = $this->request->getPost("data");
        if ($items_data) {
            $items_data = json_decode($items_data, true);

            // Check if the settings menu has been added, if not, add it to the bottom
            if ($this->login_user->is_admin && $type != "client_default" && $type != "vendor_default" && array_search("settings", array_column($items_data, "name")) === false) {
                $items_data[] = ["name" => "settings"];
            }

            $items_data = serialize($items_data);
        }

        if ($type == "user") {
            $this->settingsmodel->save_setting("user_" . $this->login_user->id . "_left_menu", $items_data);
            return $this->respond(json_encode(["success" => true, "redirect_to" => site_url($this->_prepare_user_custom_redirect_to_url()), "message" => lang('settings_updated')]));
        } else {
            if ($type == "client_default") {
                $this->settingsmodel->save_setting("default_client_left_menu", $items_data);
            } else if ($type == "vendor_default") {
                $this->settingsmodel->save_setting("default_vendor_left_menu", $items_data);
            } else {
                $this->settingsmodel->save_setting("default_left_menu", $items_data);
            }

            return $this->respond(json_encode(["success" => true, "message" => lang('settings_updated')]));
        }
    }

    private function _prepare_user_custom_redirect_to_url()
    {
        $redirect_to = "team_members/view/" . $this->login_user->id . "/left_menu";
        switch ($this->login_user->user_type) {
            case "client":
                $redirect_to = "clients/contact_profile/" . $this->login_user->id . "/left_menu";
                break;
            case "resource":
                $redirect_to = "rm_members/view/" . $this->login_user->id . "/left_menu";
                break;
            case "vendor":
                $redirect_to = "vendors/contact_profile/" . $this->login_user->id . "/left_menu";
                break;
        }

        return $redirect_to;
    }

    public function add_menu_item_modal_form()
    {
        $model_info = (object)[
            "title" => $this->request->getPost("title"),
            "url" => $this->request->getPost("url"),
            "is_sub_menu" => $this->request->getPost("is_sub_menu"),
            "open_in_new_tab" => $this->request->getPost("open_in_new_tab"),
            "icon" => $this->request->getPost("icon")
        ];

        $view_data["model_info"] = $model_info;

        return view("left_menu/add_menu_item_modal_form", $view_data);
    }

    public function prepare_custom_menu_item_data()
    {
        $title = $this->request->getPost("title");
        $url = $this->request->getPost("url");
        $is_sub_menu = $this->request->getPost("is_sub_menu");
        $open_in_new_tab = $this->request->getPost("open_in_new_tab");
        $icon = $this->request->getPost("icon");

        $item_array = [
            "name" => $title,
            "url" => $url,
            "is_sub_menu" => $is_sub_menu,
            "icon" => $icon,
            "open_in_new_tab" => $open_in_new_tab
        ];

        $item_data = $this->left_menu->_get_item_data($item_array);

        if ($item_data) {
            return $this->respond(json_encode(["success" => true, "item_data" => $item_data]));
        } else {
            return $this->respond(json_encode(["success" => false, "message" => lang('error_occurred')]));
        }
    }

    public function restore($type = "")
    {
        $this->check_left_menu_permission($type);

        switch ($type) {
            case "user":
                $this->settingsmodel->save_setting("user_" . $this->login_user->id . "_left_menu", "");
                return redirect()->to($this->_prepare_user_custom_redirect_to_url());
            case "client_default":
                $this->settingsmodel->save_setting("default_client_left_menu", "");
                return redirect()->to("left_menus/index/client_default");
            case "vendor_default":
                $this->settingsmodel->save_setting("default_vendor_left_menu", "");
                return redirect()->to("left_menus/index/vendor_default");
            default:
                $this->settingsmodel->save_setting("default_left_menu", "");
                return redirect()->to("left_menus");
        }
    }

}

/* End of file Left_menus.php */
/* Location: ./app/Controllers/Left_menus.php */
