<?php

namespace App\Controllers;

use App\Models\PaymentMethodsModel;

class Payment_methods extends BaseController {
    protected $paymentMethodsModel;

    public function __construct() {
        $this->paymentMethodsModel = new PaymentMethodsModel();
        $this->access_only_admin(); // Assuming this is a custom method for access control
    }

    public function index() {
        return view('payment_methods/index');
    }

    public function modal_form() {
        $this->validate([
            'id' => 'numeric'
        ]);

        $viewData['model_info'] = $this->paymentMethodsModel->getOneWithSettings($this->request->getPost('id'));
        $viewData['settings'] = $this->paymentMethodsModel->getSettings($viewData['model_info']['type']);

        return view('payment_methods/modal_form', $viewData);
    }

    public function save() {
        $this->validate([
            'id' => 'numeric'
        ]);

        $id = $this->request->getPost('id');
        $data = [
            'title' => $this->request->getPost('title'),
            'description' => $this->request->getPost('description'),
            'available_on_invoice' => unformat_currency($this->request->getPost('available_on_invoice')),
            'available_on_purchase_order' => unformat_currency($this->request->getPost('available_on_purchase_order')),
            'available_on_work_order' => unformat_currency($this->request->getPost('available_on_work_order')),
            'minimum_payment_amount' => unformat_currency($this->request->getPost('minimum_payment_amount'))
        ];

        $modelInfo = $this->paymentMethodsModel->getOne($id);
        $settings = $this->paymentMethodsModel->getSettings($modelInfo['type']);
        $settingsData = [];

        foreach ($settings as $setting) {
            $fieldType = $setting['type'];
            $settingsName = $setting['name'];
            $value = $this->request->getPost($settingsName);

            if ($fieldType == 'boolean' && $value != '1') {
                $value = '0';
            }

            if ($fieldType != 'readonly') {
                $settingsData[$settingsName] = $value;
            }
        }

        $data['settings'] = serialize($settingsData);

        $saveId = $this->paymentMethodsModel->save($data, $id);
        if ($saveId) {
            return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($saveId), 'id' => $saveId, 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function delete() {
        $this->validate([
            'id' => 'numeric'
        ]);

        $id = $this->request->getPost('id');
        if ($this->request->getPost('undo')) {
            if ($this->paymentMethodsModel->delete($id, true)) {
                return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_undone')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($this->paymentMethodsModel->delete($id)) {
                return $this->response->setJSON(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }

    public function list_data() {
        $listData = $this->paymentMethodsModel->getDetails()->getResult();
        $result = [];
        foreach ($listData as $data) {
            $result[] = $this->_make_row($data);
        }
        return $this->response->setJSON(['data' => $result]);
    }

    private function _row_data($id) {
        $options = ['id' => $id];
        $data = $this->paymentMethodsModel->getDetails($options)->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data) {
        $options = modal_anchor(route_to('payment_methods/modal_form'), "<i class='fa fa-pencil'></i>", ['class' => 'edit', 'title' => lang('edit_payment_method'), 'data-post-id' => $data->id]);

        if (!$data->online_payable) {
            $options .= js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_payment_method'), 'class' => 'delete', 'data-id' => $data->id, 'data-action-url' => route_to('payment_methods/delete'), 'data-action' => 'delete-confirmation']);
        }

        return [
            $data->title,
            $data->description,
            $data->online_payable ? ($data->available_on_invoice ? lang('yes') : lang('no')) : "-",
            $data->online_payable ? ($data->available_on_purchase_order ? lang('yes') : lang('no')) : "-",
            $data->online_payable ? ($data->available_on_work_order ? lang('yes') : lang('no')) : "-",
            $data->minimum_payment_amount ? to_decimal_format($data->minimum_payment_amount) : "-",
            $options
        ];
    }
}

