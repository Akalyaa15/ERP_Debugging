<?php

namespace App\Controllers;

use App\Models\BankNameModel;
use App\Models\UsersModel;

class Bank_name extends BaseController
{
    protected $bankNameModel;
    protected $usersModel;

    public function __construct()
    {
        $this->bankNameModel = new BankNameModel();
        $this->usersModel = new UsersModel();
    }

    public function index()
    {
        $this->check_module_availability("module_master_data");

        if ($this->login_user->is_admin == "1") {
            return view('bank_name/index');
        } else if ($this->login_user->user_type == "staff" || $this->login_user->user_type == "resource") {
            if ($this->access_type != "all" && !in_array($this->login_user->id, $this->allowed_members)) {
                return redirect()->to("forbidden");
            }
            return view('bank_name/index');
        } else {
            return view('bank_name/index');
        }
    }

    public function modal_form()
    {
        helper(['form', 'url']);
        $id = $this->request->getPost('id');
        
        // Validate input data
        if (!is_numeric($id)) {
            return json_encode(array("success" => false, 'message' => 'Invalid ID'));
        }

        $model_info = $this->bankNameModel->find($id);
        $account_numbers = explode(",", $this->bankNameModel->get_account_number_suggestions($id));
        $account_number_suggestions = array_unique($account_numbers);
        
        if (empty($account_number_suggestions[0])) {
            $account_number_suggestions = ["0" => ""];
        }

        $data = [
            'model_info' => $model_info,
            'account_number_suggestions' => $account_number_suggestions
        ];

        return view('bank_name/modal_form', $data);
    }

    public function save()
    {
        helper('form');
        $id = $this->request->getPost('id');
        
        // Validate input data
        $validationRules = [
            'id' => 'numeric',
            'title' => 'required'
        ];
        
        if (!$this->validate($validationRules)) {
            return json_encode(array("success" => false, 'message' => 'Validation failed'));
        }

        if ($id) {
            $bankData = $this->bankNameModel->find($id);

            if (strtoupper($bankData['title']) != strtoupper($this->request->getPost('title'))) {
                if ($this->bankNameModel->is_bank_name_exists($this->request->getPost('title'))) {
                    return json_encode(array("success" => false, 'message' => lang('duplicate_bank_name')));
                }
            }
        } else {
            if ($this->bankNameModel->is_bank_name_exists($this->request->getPost('title'))) {
                return json_encode(array("success" => false, 'message' => lang('duplicate_bank_name')));
            }
        }

        $data = [
            "title" => $this->request->getPost('title'),
            "status" => $this->request->getPost('status'),
            "description" => $this->request->getPost('description'),
            "account_number" => $this->request->getPost('account_number'),
            "last_activity_user" => $this->login_user->id,
            "last_activity" => get_current_utc_time(),
        ];

        $save_id = $this->bankNameModel->save($data, $id);

        if ($save_id) {
            return json_encode(array("success" => true, "data" => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')));
        } else {
            return json_encode(array("success" => false, 'message' => lang('error_occurred')));
        }
    }

    public function delete()
    {
        helper(['form', 'url']);
        $id = $this->request->getPost('id');
        
        // Validate input data
        if (!is_numeric($id)) {
            return json_encode(array("success" => false, 'message' => 'Invalid ID'));
        }

        $data = [
            "last_activity_user" => $this->login_user->id,
            "last_activity" => get_current_utc_time(),
        ];

        $save_id = $this->bankNameModel->save($data, $id);

        if ($this->request->getPost('undo')) {
            if ($this->bankNameModel->delete($id, true)) {
                return json_encode(array("success" => true, "data" => $this->_row_data($id), "message" => lang('record_undone')));
            } else {
                return json_encode(array("success" => false, lang('error_occurred')));
            }
        } else {
            if ($this->bankNameModel->delete($id)) {
                return json_encode(array("success" => true, 'message' => lang('record_deleted')));
            } else {
                return json_encode(array("success" => false, 'message' => lang('record_cannot_be_deleted')));
            }
        }
    }
    public function list_data()
    {
        $list_data = $this->bankNameModel->findAll();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        return json_encode(array("data" => $result));
    }

    private function _row_data($id)
    {
        $data = $this->bankNameModel->find($id);
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $note_labels = "";
        
        if ($data['account_number']) {
            $labels = explode(",", $data['account_number']);
            
            foreach ($labels as $label) {
                $note_labels .= "<span class='label label-info clickable'>" . $label . "</span> ";
            }
        }

        $last_activity_by_user_name = "-";
        
        if ($data['last_activity_user']) {
            $last_activity_user_data = $this->usersModel->find($data['last_activity_user']);
            $last_activity_image_url = get_avatar($last_activity_user_data['image']);
            $last_activity_user = "<span class='avatar avatar-xs mr10'><img src='$last_activity_image_url' alt='...'></span> $last_activity_user_data['first_name'] $last_activity_user_data['last_name']";
            
            if ($last_activity_user_data['user_type'] == "resource") {
                $last_activity_by_user_name = get_rm_member_profile_link($data['last_activity_user'], $last_activity_user);
            } else if ($last_activity_user_data['user_type'] == "client") {
                $last_activity_by_user_name = get_client_contact_profile_link($data['last_activity_user'], $last_activity_user);
            } else if ($last_activity_user_data['user_type'] == "staff") {
                $last_activity_by_user_name = get_team_member_profile_link($data['last_activity_user'], $last_activity_user);
            } else if ($last_activity_user_data['user_type'] == "vendor") {
                $last_activity_by_user_name = get_vendor_contact_profile_link($data['last_activity_user'], $last_activity_user);
            }
        }

        $last_activity_date = $data['last_activity'] ? format_to_relative_time($data['last_activity']) : "-";

        return [
            $data['title'],
            $note_labels,
            $data['description'] ? $data['description'] : "-",
            lang($data['status']),
            $last_activity_by_user_name,
            $last_activity_date,
            modal_anchor(get_uri("bank_name/modal_form"), "<i class='fa fa-pencil'></i>", array("class" => "edit", "title" => lang('edit_bank'), "data-post-id" => $data['id']))
                . js_anchor("<i class='fa fa-times fa-fw'></i>", array('title' => lang('delete_tax'), "class" => "delete", "data-id" => $data['id'], "data-action-url" => get_uri("bank_name/delete"), "data-action" => "delete-confirmation"))
        ];}
}
