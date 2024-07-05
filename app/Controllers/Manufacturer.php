<?php

namespace App\Controllers;

use App\Models\ManufacturerModel;
use App\Models\UsersModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

class Manufacturer extends BaseController {
    protected $manufacturerModel;
    protected $usersModel;

    public function __construct() {
        $this->manufacturerModel = new ManufacturerModel();
        $this->usersModel = new UsersModel();
        helper(['form', 'url']);
    }

    public function index() {
        $this->check_module_availability("module_production_data");
        if ($this->login_user->is_admin == "1" || 
            $this->login_user->user_type == "staff" || 
            $this->login_user->user_type == "resource") {
            return view('manufacturer/index');
        } else {
            return view('manufacturer/index');
        }
    }

    public function modal_form() {
        $id = $this->request->getPost('id');
        $view_data['model_info'] = $this->manufacturerModel->find($id);
        return view('manufacturer/modal_form', $view_data);
    }

    public function save() {
        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'numeric',
            'title' => 'required'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON(['success' => false, 'message' => $validation->getErrors()]);
        }

        $id = $this->request->getPost('id');
        $data = [
            'title' => $this->request->getPost('title'),
            'status' => $this->request->getPost('status'),
            'description' => $this->request->getPost('description'),
            'address' => $this->request->getPost('address'),
            'website' => $this->request->getPost('website'),
            'last_activity_user' => $this->login_user->id,
            'last_activity' => date('Y-m-d H:i:s')
        ];

        if (!$id && $this->manufacturerModel->is_manufacturer_list_exists($data['title'])) {
            return $this->response->setJSON(['success' => false, 'message' => lang('manufacturer_already')]);
        }

        if ($id && $this->manufacturerModel->is_manufacturer_list_exists($data['title'], $id)) {
            return $this->response->setJSON(['success' => false, 'message' => lang('manufacturer_already')]);
        }

        if ($this->manufacturerModel->save($data, $id)) {
            return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($id), 'id' => $id, 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function delete() {
        $validation = \Config\Services::validation();
        $validation->setRules(['id' => 'required|numeric']);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON(['success' => false, 'message' => $validation->getErrors()]);
        }

        $id = $this->request->getPost('id');
        $data = [
            'last_activity_user' => $this->login_user->id,
            'last_activity' => date('Y-m-d H:i:s')
        ];

        $this->manufacturerModel->save($data, $id);
        if ($this->request->getPost('undo')) {
            if ($this->manufacturerModel->delete($id, true)) {
                return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_undone')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($this->manufacturerModel->delete($id)) {
                return $this->response->setJSON(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }

    public function list_data() {
        $list_data = $this->manufacturerModel->findAll();
        $result = array();
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        return $this->response->setJSON(['data' => $result]);
    }

    private function _row_data($id) {
        $data = $this->manufacturerModel->find($id);
        return $this->_make_row($data);
    }

    private function _make_row($data) {
        // Last activity user name and date
        $last_activity_by_user_name = "-";
        if ($data['last_activity_user']) {
            $last_activity_user_data = $this->usersModel->find($data['last_activity_user']);
            $last_activity_image_url = get_avatar($last_activity_user_data['image']);
            $last_activity_user = "<span class='avatar avatar-xs mr10'><img src='$last_activity_image_url' alt='...'></span> {$last_activity_user_data['first_name']} {$last_activity_user_data['last_name']}";

            switch ($last_activity_user_data['user_type']) {
                case 'resource':
                    $last_activity_by_user_name = get_rm_member_profile_link($data['last_activity_user'], $last_activity_user);
                    break;
                case 'client':
                    $last_activity_by_user_name = get_client_contact_profile_link($data['last_activity_user'], $last_activity_user);
                    break;
                case 'staff':
                    $last_activity_by_user_name = get_team_member_profile_link($data['last_activity_user'], $last_activity_user);
                    break;
                case 'vendor':
                    $last_activity_by_user_name = get_vendor_contact_profile_link($data['last_activity_user'], $last_activity_user);
                    break;
            }
        }

        $last_activity_date = "-";
        if ($data['last_activity']) {
            $last_activity_date = format_to_relative_time($data['last_activity']);
        }

        $website_link = "";
        if ($data['website']) {
            $website_address = to_url($data['website']); // check http or https in URL
            $website_link = "<a target='_blank' href='$website_address'>{$data['website']}</a>";
        }

        return [
            $data['title'],
            $data['address'],
            $website_link ? $website_link : "-",
            $data['description'] ? $data['description'] : "-",
            lang($data['status']),
            $last_activity_by_user_name,
            $last_activity_date,
            modal_anchor(get_uri("manufacturer/modal_form"), "<i class='fa fa-pencil'></i>", ["class" => "edit", "title" => lang('edit_manufacturer'), "data-post-id" => $data['id']]) .
            js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete'), "class" => "delete", "data-id" => $data['id'], "data-action-url" => get_uri("manufacturer/delete"), "data-action" => "delete-confirmation"])
        ];
    }
}
