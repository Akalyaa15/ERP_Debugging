<?php

namespace App\Controllers;
use App\Models\BuyerTypesModel;
use CodeIgniter\API\ResponseTrait;

class BuyerTypes extends BaseController
{
    use ResponseTrait;

    protected $buyerTypesModel;

    public function __construct()
    {
        $this->buyerTypesModel = new BuyerTypesModel();
    }

    public function index()
    {
        return view('buyer_types/index');
    }

    public function modal_form()
    {
        $id = $this->request->getPost('id');
        $viewData['model_info'] = $this->buyerTypesModel->find($id);
        return view('buyer_types/modal_form', $viewData);
    }

    public function save()
    {
        $id = $this->request->getPost('id');
        
        $validationRules = [
            'id' => 'numeric',
            'buyer_type' => 'required',
            'profit_margin' => 'required'
        ];
        
        if (!$this->validate($validationRules)) {
            return $this->fail($this->validator->getErrors());
        }

        $data = [
            'buyer_type' => $this->request->getPost('buyer_type'),
            'profit_margin' => unformat_currency($this->request->getPost('profit_margin')),
            'status' => $this->request->getPost('status'),
            'description' => $this->request->getPost('description'),
        ];

        $save_id = $this->buyerTypesModel->save($data, $id);
        
        if ($save_id) {
            return $this->respondCreated(['success' => true, 'data' => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
        } else {
            return $this->fail(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function delete()
    {
        $id = $this->request->getPost('id');
        $undo = $this->request->getPost('undo');

        if ($undo) {
            $deleted = $this->buyerTypesModel->delete($id, true);
        } else {
            $deleted = $this->buyerTypesModel->delete($id);
        }

        if ($deleted) {
            return $this->respond(['success' => true, 'message' => lang('record_deleted')]);
        } else {
            return $this->fail(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
        }
    }

    public function list_data()
    {
        $list_data = $this->buyerTypesModel->findAll();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        return $this->respond(['data' => $result]);
    }

    private function _row_data($id)
    {
        $data = $this->buyerTypesModel->find($id);
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        return [
            $data['buyer_type'],
            $data['description'] ? $data['description'] : "-",
            to_decimal_format($data['profit_margin']) . "%",
            lang($data['status']),
            modal_anchor('buyer_types/modal_form', "<i class='fa fa-pencil'></i>", ['class' => "edit", 'title' => lang('edit_buyer_type'), 'data-post-id' => $data['id']])
            . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_buyer_type'), 'class' => "delete", 'data-id' => $data['id'], 'data-action-url' => 'buyer_types/delete', 'data-action' => "delete-confirmation"])
        ];
    }
}

