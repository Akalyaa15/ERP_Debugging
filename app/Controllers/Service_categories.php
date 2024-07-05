<?php

namespace App\Controllers;
use CodeIgniter\Controller;
use App\Models\Service_categories_model;
use App\Models\Users_model;

class Service_categories extends Controller {
    protected $unittypemodel;
    protected $jobidgenerationmodel;
    protected $serviceidgenerationmodel;
    protected $servicecategoriesmodel;
    protected $hsnsaccodemodel;
    protected $manufacturermodel;
    protected $usersmodel;
    public function __construct() {
        $this->servicecategoriesmodel = new Service_categories_model();
        $this->usersmodel = new Users_model();
    }

    public function index() {
        $this->check_module_availability("module_production_data");

        if ($this->login_user->is_admin == "1" || $this->login_user->user_type == "staff") {
            if ($this->access_type != "all" && !in_array($this->login_user->id, $this->allowed_members)) {
                return redirect()->to("forbidden");
            }
        }

        return view("service_categories/index");
    }

    public function modal_form() {
        $id = $this->request->getPost('id');
        $view_data['model_info'] = $this->servicecategoriesmodel->get_one($id);
        return view('service_categories/modal_form', $view_data);
    }

    public function save() {
        $id = $this->request->getPost('id');
        $data = [
            "title" => $this->request->getPost('title'),
            "status" => $this->request->getPost('status'),
            "description" => $this->request->getPost('description'),
            "last_activity_user" => $this->login_user->id,
            "last_activity" => date('Y-m-d H:i:s'), // Example format, adjust as needed
        ];

        if (!$id && $this->servicecategoriesmodel->is_service_category_list_exists($data["title"])) {
            return $this->response->setJSON(["success" => false, 'message' => lang('service_category_already')]);
        }

        if ($id && $this->servicecategoriesmodel->is_service_category_list_exists($data["title"], $id)) {
            return $this->response->setJSON(["success" => false, 'message' => lang('service_category_already')]);
        }

        $save_id = $this->servicecategoriesmodel->save($data, $id);
        if ($save_id) {
            return $this->response->setJSON(["success" => true, "data" => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(["success" => false, 'message' => lang('error_occurred')]);
        }
    }

    public function delete() {
        $id = $this->request->getPost('id');
        $undo = $this->request->getPost('undo');

        if ($undo && $this->servicecategoriesmodel->delete($id, true)) {
            return $this->response->setJSON(["success" => true, "data" => $this->_row_data($id), "message" => lang('record_undone')]);
        } elseif (!$undo && $this->servicecategoriesmodel->delete($id)) {
            return $this->response->setJSON(["success" => true, 'message' => lang('record_deleted')]);
        } else {
            return $this->response->setJSON(["success" => false, 'message' => lang('record_cannot_be_deleted')]);
        }
    }

    public function list_data() {
        $list_data = $this->servicecategoriesmodel->get_details()->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        return $this->response->setJSON(["data" => $result]);
    }

    private function _row_data($id) {
        $options = ["id" => $id];
        $data = $this->servicecategoriesmodel->get_details($options)->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data) {
        $last_activity_by_user_name = "-";
        $last_activity_date = "-";

        if ($data->last_activity_user) {
            $last_activity_user_data = $this->usersmodel->get_one($data->last_activity_user);
            $last_activity_image_url = get_avatar($last_activity_user_data->image);
            $last_activity_user = "<span class='avatar avatar-xs mr10'><img src='$last_activity_image_url' alt='...'></span> $last_activity_user_data->first_name $last_activity_user_data->last_name";

            if ($last_activity_user_data->user_type == "resource") {
                $last_activity_by_user_name = get_rm_member_profile_link($data->last_activity_user, $last_activity_user);
            } elseif ($last_activity_user_data->user_type == "client") {
                $last_activity_by_user_name = get_client_contact_profile_link($data->last_activity_user, $last_activity_user);
            } elseif ($last_activity_user_data->user_type == "staff") {
                $last_activity_by_user_name = get_team_member_profile_link($data->last_activity_user, $last_activity_user);
            } elseif ($last_activity_user_data->user_type == "vendor") {
                $last_activity_by_user_name = get_vendor_contact_profile_link($data->last_activity_user, $last_activity_user);
            }

            $last_activity_date = format_to_relative_time($data->last_activity);
        }

        return [
            $data->title,
            $data->description ?: "-",
            lang($data->status),
            $last_activity_by_user_name,
            $last_activity_date,
            modal_anchor(get_uri("service_categories/modal_form"), "<i class='fa fa-pencil'></i>", ["class" => "edit", "title" => lang('edit_service_category'), "data-post-id" => $data->id]) . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_service_category'), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("service_categories/delete"), "data-action" => "delete-confirmation"]),
        ];
    }

}
