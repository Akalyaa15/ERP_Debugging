<?php

namespace App\Controllers;

use App\Models\VoucherTypesModel;
use CodeIgniter\API\ResponseTrait;

class Voucher_types extends BaseController
{
    use ResponseTrait;

    protected $voucherTypesModel;

    public function __construct()
    {
        parent::__construct();

        // Load necessary helpers, libraries, and models
        helper(['form', 'url']);
        $this->voucherTypesModel = new VoucherTypesModel();
        $this->access_only_admin(); // Assuming this is a method in your BaseController or a custom helper
    }

    public function index()
    {
        return view('voucher_types/index');
    }

    public function modal_form()
    {
        $id = $this->request->getPost('id');
        $model_info = $this->voucherTypesModel->find($id);

        $viewData['model_info'] = $model_info;
        return view('voucher_types/modal_form', $viewData);
    }

    public function save()
    {
        $id = $this->request->getPost('id');
        $data = [
            'title' => $this->request->getPost('title'),
            'status' => $this->request->getPost('status'),
            'description' => $this->request->getPost('description')
        ];

        $save_id = $this->voucherTypesModel->save($data, $id);

        if ($save_id) {
            return $this->respond([
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

        if ($this->request->getPost('undo')) {
            if ($this->voucherTypesModel->delete($id, true)) {
                return $this->respond([
                    'success' => true,
                    'data' => $this->_row_data($id),
                    'message' => lang('record_undone')
                ]);
            } else {
                return $this->fail(lang('error_occurred'));
            }
        } else {
            if ($this->voucherTypesModel->delete($id)) {
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
        $list_data = $this->voucherTypesModel->findAll();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        return $this->respond([
            'data' => $result
        ]);
    }

    private function _row_data($id)
    {
        $data = $this->voucherTypesModel->find($id);
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        return [
            $data['title'],
            $data['description'] ?: "-",
            lang($data['status']),
            modal_anchor('voucher_types/modal_form', '<i class="fa fa-pencil"></i>', [
                'class' => 'edit',
                'title' => lang('edit_voucher_type'),
                'data-post-id' => $data['id']
            ]) .
            js_anchor('<i class="fa fa-times fa-fw"></i>', [
                'title' => lang('delete_'),
                'class' => 'delete',
                'data-id' => $data['id'],
                'data-action-url' => 'voucher_types/delete',
                'data-action' => 'delete-confirmation'
            ])
        ];
    }

}
