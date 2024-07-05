<?php

namespace App\Controllers;

use App\Models\UnitTypeModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;

class Unit_type extends BaseController
{
    use ResponseTrait;

    protected $unitTypeModel;

    public function __construct()
    {
        $this->unitTypeModel = new UnitTypeModel(); // Example model initialization

        // Ensure to call the parent constructor
        parent::__construct();

        // Access control or any other initialization
        $this->access_only_admin();
    }

    public function index()
    {
        return view('unit_type/index');
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

        $viewData['model_info'] = $this->unitTypeModel->find($this->request->getPost('id'));
        return view('unit_type/modal_form', $viewData);
    }

    public function save()
    {
        // Validate input
        $validationRules = [
            'id' => 'numeric',
            'title' => 'required',
            'status' => 'required',
            'description' => 'required'
        ];

        if (!$this->validate($validationRules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $id = $this->request->getPost('id');
        $data = [
            'title' => $this->request->getPost('title'),
            'status' => $this->request->getPost('status'),
            'description' => $this->request->getPost('description')
        ];

        $saveId = $this->unitTypeModel->save($data, $id);

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
            if ($this->unitTypeModel->delete($id, true)) {
                return $this->respond([
                    'success' => true,
                    'data' => $this->_row_data($id),
                    'message' => lang('record_undone')
                ]);
            } else {
                return $this->fail(lang('error_occurred'));
            }
        } else {
            if ($this->unitTypeModel->delete($id)) {
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
        $listData = $this->unitTypeModel->get_details()->getResult();
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
        return [
            $data->title,
            $data->description ? $data->description : "-",
            lang($data->status),
            modal_anchor(get_uri("unit_type/modal_form"), "<i class='fa fa-pencil'></i>", [
                'class' => 'edit',
                'title' => lang('edit_unit_type'),
                'data-post-id' => $data->id
            ]) . js_anchor("<i class='fa fa-times fa-fw'></i>", [
                'title' => lang('delete_unit_type'),
                'class' => 'delete',
                'data-id' => $data->id,
                'data-action-url' => get_uri("unit_type/delete"),
                'data-action' => 'delete-confirmation'
            ])
        ];
    }

}

