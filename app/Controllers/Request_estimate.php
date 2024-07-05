<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class Request_estimate extends BaseController {
    protected $estimateformsmodel;
    protected $customfieldsmodel;
    protected $leadsmodel;
    protected $estimaterequestsmodel;
    protected $customfieldvaluesmodel;

    public function __construct() {
        parent::__construct();

        // Load necessary models using service() helper
        $this->estimateformsmodel = service('models')->Estimate_forms_model;
        $this->customfieldsmodel = service('models')->Custom_fields_model;
        $this->leadsmodel = service('models')->Leads_model;
        $this->estimaterequestsmodel = service('models')->Estimate_requests_model;
        $this->customfieldvaluesmodel = service('models')->Custom_field_values_model;
    }

    public function index() {
        if (!get_setting("module_estimate_request")) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $view_data['topbar'] = "includes/public/topbar";
        $view_data['left_menu'] = false;

        $view_data["estimate_forms"] = $this->estimateformsmodel->where(['status' => "active", "public" => "1", "deleted" => 0])->findAll();
        return view("request_estimate/index", $view_data);
    }

    public function form($id = 0) {
        if (!get_setting("module_estimate_request")) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if (!$id) {
            return redirect()->to("request_estimate");
        }

        $view_data['topbar'] = "includes/public/topbar";
        $view_data['left_menu'] = false;

        $model_info = $this->estimateformsmodel->where(['id' => $id, "public" => "1", "status" => "active", "deleted" => 0])->first();

        if ($model_info && get_setting("module_estimate_request")) {
            $view_data['model_info'] = $model_info;
            return view('request_estimate/estimate_request_form', $view_data);
        } else {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
    }

    // Save estimate request from client
    public function save_estimate_request() {
        $form_id = $this->request->getPost('form_id');

        // Validate form data
        $this->validate([
            "form_id" => "required|numeric",
            "company_name" => "required",
            "first_name" => "required",
            "last_name" => "required",
            "email" => "required",
        ]);

        $options = ["related_to" => "estimate_form-" . $form_id];
        $form_fields = $this->customfieldsmodel->where($options)->findAll();

        $target_path = get_setting("timeline_file_path");
        $files_data = move_files_from_temp_dir_to_permanent_dir($target_path, "estimate");

        $leads_data = [
            "company_name" => $this->request->getPost('company_name'),
            "address" => $this->request->getPost('address'),
            "city" => $this->request->getPost('city'),
            "state" => $this->request->getPost('state'),
            "zip" => $this->request->getPost('zip'),
            "country" => $this->request->getPost('country'),
            "phone" => $this->request->getPost('phone'),
            "first_name" => $this->request->getPost('first_name'),
            "last_name" => $this->request->getPost('last_name'),
            "email" => $this->request->getPost('email')
        ];

        $leads_data = clean_data($leads_data);
        $lead_id = $this->leadsmodel->insert($leads_data);

        $request_data = [
            "estimate_form_id" => $form_id,
            "created_by" => 0,
            "created_at" => get_current_utc_time(),
            "client_id" => 0,
            "lead_id" => $lead_id,
            "assigned_to" => 0,
            "status" => "new",
            "files" => $files_data  // Don't clean serialized data
        ];

        $request_data = clean_data($request_data);

        $save_id = $this->estimaterequestsmodel->insert($request_data);
        if ($save_id) {
            // Estimate request has been saved, now save the field values
            foreach ($form_fields as $field) {
                $value = $this->request->getPost("custom_field_" . $field['id']);
                if ($value) {
                    $field_value_data = [
                        "related_to_type" => "estimate_request",
                        "related_to_id" => $save_id,
                        "custom_field_id" => $field['id'],
                        "value" => $value
                    ];

                    $field_value_data = clean_data($field_value_data);

                    $this->customfieldvaluesmodel->insert($field_value_data);
                }
            }

            // Create notification
            log_notification("estimate_request_received", ["estimate_request_id" => $save_id, "user_id" => "999999999"]);

            return $this->response->setJSON(["success" => true, 'message' => lang('estimate_submission_message')]);
        } else {
            return $this->response->setJSON(["success" => false, 'message' => lang('error_occurred')]);
        }
    }

    // Prepare data for datatable for estimate form's field list
    public function estimate_form_field_list_data($id = 0) {
        $options = ["related_to" => "estimate_form-" . $id];
        $list_data = $this->customfieldsmodel->where($options)->findAll();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_form_field_row($data);
        }
        return $this->response->setJSON(["data" => $result]);
    }

    // Prepare a row of estimates form's field list
    private function _make_form_field_row($data) {
        $required = $data['required'] ? "*" : "";
        $field = "<label data-id='{$data['id']}' class='field-row'>{$data['title']} {$required}</label>";
        $field .= "<div class='form-group'>" . view("custom_fields/input_" . $data['field_type'], ["field_info" => $data], true) . "</div>";

        // Extract estimate id from related_to field. 2nd index should be the id
        $estimate_form_id = explode("-", $data['related_to'])[1];

        return [
            $field,
            $data['sort'],
            modal_anchor("estimate_requests/estimate_form_field_modal_form/{$estimate_form_id}", "<i class='fa fa-pencil'></i>", ["class" => "edit", "title" => lang('edit_form'), "data-post-id" => $data['id']])
                . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete'), "class" => "delete", "data-id" => $data['id'], "data-action-url" => "estimate_requests/estimate_form_field_delete", "data-action" => "delete"])
        ];
    }

    // Upload a file
    public function upload_file() {
        upload_file_to_temp();
    }

    // Check valid file for ticket
    public function validate_file() {
        return validate_post_file($this->request->getPost("file_name"));
    }
}
