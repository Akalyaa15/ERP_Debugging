<?php

namespace App\Controllers;

use App\Models\Hsn_sac_code_model;
use CodeIgniter\API\ResponseTrait;

class Hsn_sac_code extends BaseController
{
    use ResponseTrait;

    protected $hsn_sac_code_model;

    public function __construct()
    {
        $this->hsn_sac_code_model = new Hsn_sac_code_model();
    }

    public function index()
    {
        return view('hsn_sac_code/index');
    }

    public function modal_form()
    {
        $id = $this->request->getPost('id');
        $view_data['model_info'] = $this->hsn_sac_code_model->find($id);
        return view('hsn_sac_code/modal_form', $view_data);
    }

    public function save()
    {
        $id = $this->request->getPost('id');
        
        $rules = [
            'hsn_code' => 'required',
            'gst' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $data = [
            'hsn_code' => $this->request->getPost('hsn_code'),
            'hsn_description' => $this->request->getPost('hsn_description'),
            'gst' => unformat_currency($this->request->getPost('gst'))
        ];

        if ($id) {
            $data['id'] = $id;
        }

        // Check if hsn_code already exists
        if ($this->hsn_sac_code_model->is_hsn_code_exists($data['hsn_code'], $id)) {
            return $this->fail(lang('hsn_code_already'));
        }

        // Save data
        $save_id = $this->hsn_sac_code_model->save($data);

        if ($save_id) {
            return $this->respondCreated([
                'success' => true,
                'data' => $this->_row_data($save_id),
                'id' => $save_id,
                'message' => lang('record_saved')
            ]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

    public function delete()
    {
        $id = $this->request->getPost('id');
        $undo = $this->request->getPost('undo');

        if ($undo && $this->hsn_sac_code_model->delete($id, true)) {
            return $this->respondDeleted([
                'success' => true,
                'data' => $this->_row_data($id),
                'message' => lang('record_undone')
            ]);
        } elseif (!$undo && $this->hsn_sac_code_model->delete($id)) {
            return $this->respondDeleted([
                'success' => true,
                'message' => lang('record_deleted')
            ]);
        } else {
            return $this->fail(lang('record_cannot_be_deleted'));
        }
    }

    public function list_data()
    {
        $list_data = $this->hsn_sac_code_model->findAll();
        $result = array_map(function ($data) {
            return $this->_make_row($data);
        }, $list_data);

        return $this->respond([
            'data' => $result
        ]);
    }

    private function _row_data($id)
    {
        $data = $this->hsn_sac_code_model->find($id);
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        return [
            $data['hsn_code'],
            nl2br($data['hsn_description']),
            to_decimal_format($data['gst']) . "%",
            modal_anchor(get_uri("hsn_sac_code/modal_form"), "<i class='fa fa-pencil'></i>", [
                "class" => "edit",
                "title" => lang('edit_hsn_sac_code'),
                "data-post-id" => $data['id']
            ]) . js_anchor("<i class='fa fa-times fa-fw'></i>", [
                'title' => lang('delete_hsn_sac_code'),
                "class" => "delete",
                "data-id" => $data['id'],
                "data-action-url" => get_uri("hsn_sac_code/delete"),
                "data-action" => "delete-confirmation"
            ])
        ];
    }
}

