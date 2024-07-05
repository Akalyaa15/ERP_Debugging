<?php

namespace App\Controllers;

use App\Models\PaymentStatusModel;

class Payment_status extends BaseController {
    protected $paymentStatusModel;

    public function __construct() {
        $this->paymentStatusModel = new PaymentStatusModel();
        $this->access_only_admin(); // Assuming this is a custom method for access control
    }

    public function index() {
        return view('payment_status/index');
    }

    public function modal_form() {
        $this->validate([
            'id' => 'numeric'
        ]);

        $viewData['model_info'] = $this->paymentStatusModel->getOne($this->request->getPost('id'));
        return view('payment_status/modal_form', $viewData);
    }

    public function save() {
        $this->validate([
            'id' => 'numeric',
            'title' => 'required'
        ]);

        $id = $this->request->getPost('id');
        $data = [
            'title' => $this->request->getPost('title'),
            'status' => $this->request->getPost('status'),
            'description' => $this->request->getPost('description')
        ];

        $saveId = $this->paymentStatusModel->save($data, $id);
        if ($saveId) {
            return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($saveId), 'id' => $saveId, 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function delete() {
        $this->validate([
            'id' => 'required|numeric'
        ]);

        $id = $this->request->getPost('id');
        if ($this->request->getPost('undo')) {
            if ($this->paymentStatusModel->delete($id, true)) {
                return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_undone')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($this->paymentStatusModel->delete($id)) {
                return $this->response->setJSON(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }

    public function list_data() {
        $listData = $this->paymentStatusModel->getDetails()->getResult();
        $result = [];
        foreach ($listData as $data) {
            $result[] = $this->_make_row($data);
        }
        return $this->response->setJSON(['data' => $result]);
    }

    private function _row_data($id) {
        $options = ['id' => $id];
        $data = $this->paymentStatusModel->getDetails($options)->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data) {
        return [
            $data->title,
            $data->description ?: "-",
            lang($data->status),
            modal_anchor(route_to('payment_status/modal_form'), "<i class='fa fa-pencil'></i>", ['class' => 'edit', 'title' => lang('edit_payment_status'), 'data-post-id' => $data->id])
        ];
    }
}

