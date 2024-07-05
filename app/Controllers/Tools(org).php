<?php

namespace App\Controllers;

use App\Models\ToolsModel;
use App\Models\UnitTypeModel;
use CodeIgniter\Controller;

class Tools extends BaseController
{
    protected $toolsModel;
    protected $unitTypeModel;

    public function __construct()
    {
        $this->toolsModel = new ToolsModel(); // Example model initialization
        $this->unitTypeModel = new UnitTypeModel(); // Example model initialization

        // Ensure to call the parent constructor
        parent::__construct();

        // Initialize permission checks or any other initialization needed
        $this->init_permission_checker("tools");
    }

    public function index()
    {
        $this->check_module_availability("module_assets_data");

        // Handle different user types or access levels
        if ($this->login_user->is_admin == "1") {
            return view('tools/index');
        } else if ($this->login_user->user_type == "staff") {
            // Ensure only allowed members access
            if ($this->access_type != "all" && !in_array($this->login_user->id, $this->allowed_members)) {
                return redirect()->to('forbidden');
            }
            return view('tools/index');
        } else {
            return view('tools/index');
        }
    }

    public function modal_form()
    {
        // Validate input
        $validationRules = [
            'id' => 'numeric'
        ];

        if (!$this->validate($validationRules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $viewData['model_info'] = $this->toolsModel->find($this->request->getPost('id'));
        $viewData['unit_type_dropdown'] = $this->_get_unit_type_dropdown_select2_data();

        return view('tools/modal_form', $viewData);
    }

    private function _get_unit_type_dropdown_select2_data()
    {
        $unitTypes = $this->unitTypeModel->where(['deleted' => 0, 'status' => 'active'])->findAll();
        $unitTypeDropdown = [];

        foreach ($unitTypes as $type) {
            $unitTypeDropdown[] = ['id' => $type['title'], 'text' => $type['title']];
        }

        return $unitTypeDropdown;
    }

    public function save()
    {
        // Validate input
        $validationRules = [
            'id' => 'numeric',
            'title' => 'required',
            'quantity' => 'required'
        ];

        if (!$this->validate($validationRules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $id = $this->request->getPost('id');
        $data = [
            'title' => $this->request->getPost('title'),
            'quantity' => unformat_currency($this->request->getPost('quantity')),
            'description' => $this->request->getPost('description'),
            'category' => $this->request->getPost('category'),
            'make' => $this->request->getPost('make'),
            'unit_type' => $this->request->getPost('unit_type'),
            'tool_location' => $this->request->getPost('tool_location'),
            'rate' => unformat_currency($this->request->getPost('item_rate'))
        ];

        $saveId = $this->toolsModel->save($data, $id);

        if ($saveId) {
            return $this->respond([
                'success' => true,
                'data' => $this->_row_data($saveId),
                'id' => $saveId,
                'message' => lang('record_saved')
            ]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

    public function delete()
    {
        // Validate input
        $validationRules = [
            'id' => 'numeric|required'
        ];

        if (!$this->validate($validationRules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $id = $this->request->getPost('id');

        if ($this->request->getPost('undo')) {
            if ($this->toolsModel->delete($id, true)) {
                return $this->respond([
                    'success' => true,
                    'data' => $this->_row_data($id),
                    'message' => lang('record_undone')
                ]);
            } else {
                return $this->fail(lang('error_occurred'));
            }
        } else {
            if ($this->toolsModel->delete($id)) {
                return $this->respond([
                    'success' => true,
                    'message' => lang('record_deleted')
                ]);
            } else {
                return $this->fail(lang('record_cannot_be_deleted'));
            }
        }
    }

    public function list_data()
    {
        $listData = $this->toolsModel->get_details()->getResult();
        $result = [];

        foreach ($listData as $data) {
            $result[] = $this->_make_row($data);
        }

        return $this->respond([
            'data' => $result
        ]);
    }

    private function _row_data($data)
    {
        $type = $data->unit_type ? $data->unit_type : "";

        return [
            $data->title,
            $data->description,
            $data->tool_location,
            to_decimal_format($data->quantity),
            $data->category,
            $data->make,
            $type,
            $data->rate,
            "₹" . ($data->quantity * $data->rate),
            modal_anchor(get_uri("tools/modal_form"), "<i class='fa fa-pencil'></i>", [
                'class' => 'edit',
                'title' => lang('edit_tool'),
                'data-post-id' => $data->id
            ]) . js_anchor("<i class='fa fa-times fa-fw'></i>", [
                'title' => lang('delete_tool'),
                'class' => 'delete',
                'data-id' => $data->id,
                'data-action-url' => get_uri("tools/delete"),
                'data-action' => 'delete-confirmation'
            ])
        ];
    }
}

