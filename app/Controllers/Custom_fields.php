<?php

namespace App\Controllers;

use App\Models\CustomFieldsModel;
use CodeIgniter\Controller;

class Custom_fields extends BaseController
{
    protected $customfieldsmodel;

    public function __construct()
    {
        $this->customfieldsmodel = new CustomFieldsModel();
        parent::__construct();
        $this->accessOnlyAdmin();
    }

    public function index()
    {
        return redirect()->to("custom_fields/view");
    }

    public function view($tab = "client")
    {
        $view_data["tab"] = $tab;
        return view('custom_fields/settings/index', $view_data);
    }

    public function modal_form()
    {
        $id = $this->request->getPost('id');
        $model_info = $this->customfieldsmodel->find($id);
        $related_to = $model_info->related_to ?? $this->request->getPost("related_to");

        $view_data = [
            'model_info' => $model_info,
            'related_to' => $related_to
        ];

        return view('custom_fields/settings/modal_form', $view_data);
    }

    public function save()
    {
        $id = $this->request->getPost('id');

        $rules = [
            "title" => "required",
            "related_to" => "required"
        ];

        if (!$id) {
            $rules["field_type"] = "required";
        }

        if (!$this->validate($rules)) {
            return json_encode(['success' => false, 'message' => $this->validator->getErrors()]);
        }

        $data = [
            "title" => $this->request->getPost('title'),
            "placeholder" => $this->request->getPost('placeholder'),
            "required" => (bool)$this->request->getPost('required'),
            "show_in_table" => (bool)$this->request->getPost('show_in_table'),
            "show_in_invoice" => (bool)$this->request->getPost('show_in_invoice'),
            "show_in_estimate" => (bool)$this->request->getPost('show_in_estimate'),
            "visible_to_admins_only" => (bool)$this->request->getPost('visible_to_admins_only'),
            "hide_from_clients" => (bool)$this->request->getPost('hide_from_clients'),
            "related_to" => $this->request->getPost('related_to'),
            "options" => $this->request->getPost('options') ?? ""
        ];

        if (!$id) {
            $data["field_type"] = $this->request->getPost('field_type');
            $max_sort_value = $this->customfieldsmodel->getMaxSortValue($data["related_to"]);
            $data["sort"] = $max_sort_value + 1;
        }

        $save_result = $this->customfieldsmodel->save($data, $id);
        if ($save_result) {
            return json_encode(['success' => true, 'data' => $this->_row_data($save_result), 'newData' => !$id, 'id' => $save_result, 'message' => lang('record_saved')]);
        } else {
            return json_encode(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function list_data($related_to)
    {
        $list_data = $this->customfieldsmodel->getDetails(['related_to' => $related_to]);
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_field_row($data);
        }
        return json_encode(['data' => $result]);
    }

    private function _row_data($id)
    {
        $data = $this->customfieldsmodel->find($id);
        return $this->_make_field_row($data);
    }

    private function _make_field_row($data)
    {
        $required = $data->required ? '*' : '';

        $field = "<label data-id='$data->id' class='field-row'>$data->title $required</label>";
        $field .= "<div class='form-group'>" . view("custom_fields/input_" . $data->field_type, ["field_info" => $data]) . "</div>";

        return [
            $field,
            $data->sort,
            modal_anchor("custom_fields/modal_form/", "<i class='fa fa-pencil'></i>", ["class" => "edit", "title" => lang('edit_field'), "data-post-id" => $data->id])
                . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_field'), "class" => "delete", "data-id" => $data->id, "data-action-url" => "custom_fields/delete", "data-action" => "delete"])
        ];
    }

    public function update_field_sort_values($id = 0)
    {
        $sort_values = $this->request->getPost("sort_values");
        if ($sort_values) {
            $sort_array = explode(",", $sort_values);

            foreach ($sort_array as $value) {
                [$id, $sort] = explode("-", $value);
                $this->customfieldsmodel->save(["sort" => $sort], $id);
            }
        }
    }

    public function delete()
    {
        $id = $this->request->getPost('id');
        $undo = $this->request->getPost('undo');

        $success = $undo ? $this->customfieldsmodel->delete($id, true) : $this->customfieldsmodel->delete($id);

        if ($success) {
            return json_encode(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_undone')]);
        } else {
            return json_encode(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function contacts()
    {
        return view('custom_fields/settings/contacts');
    }

    public function projects()
    {
        return view('custom_fields/settings/projects');
    }

    public function tasks()
    {
        return view('custom_fields/settings/tasks');
    }

    public function team_members()
    {
        return view('custom_fields/settings/team_members');
    }

    public function tickets()
    {
        return view('custom_fields/settings/tickets');
    }

    public function invoices()
    {
        return view('custom_fields/settings/invoices');
    }

    public function events()
    {
        return view('custom_fields/settings/events');
    }

    public function expenses()
    {
        return view('custom_fields/settings/expenses');
    }

    public function estimates()
    {
        return view('custom_fields/settings/estimates');
    }
}

/* End of file Custom_fields.php */
/* Location: ./app/Controllers/Custom_fields.php */
