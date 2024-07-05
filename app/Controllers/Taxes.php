<?php

namespace App\Controllers;

use App\Models\TaxesModel;
use CodeIgniter\API\ResponseTrait;

class Taxes extends BaseController
{
    use ResponseTrait;

    protected $taxesModel;

    public function __construct()
    {
        $this->taxesModel = new TaxesModel();
        $this->accessOnlyAdmin(); // Assuming this is a custom method for admin access control
    }

    public function index()
    {
        return view('taxes/index'); // Assuming you are using the default view folder setup
    }

    public function modal_form()
    {
        $id = $this->request->getPost('id');
        helper(['form', 'url']);

        if (!is_numeric($id)) {
            return $this->failValidation('Invalid ID');
        }

        $viewData['model_info'] = $this->taxesModel->find($id);
        return view('taxes/modal_form', $viewData);
    }

    public function save()
    {
        helper(['form', 'url']);
        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'numeric',
            'title' => 'required',
            'percentage' => 'required'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $id = $this->request->getPost('id');
        $data = [
            'title' => $this->request->getPost('title'),
            'percentage' => unformat_currency($this->request->getPost('percentage'))
        ];

        $saveId = $this->taxesModel->save($data, $id);
        if ($saveId) {
            return $this->respondCreated(['success' => true, 'data' => $this->_rowData($saveId), 'id' => $saveId, 'message' => lang('record_saved')]);
        } else {
            return $this->fail('Error occurred.');
        }
    }

    public function delete()
    {
        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'required|numeric'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $id = $this->request->getPost('id');
        if ($this->request->getPost('undo')) {
            if ($this->taxesModel->delete($id, true)) {
                return $this->respondDeleted(['success' => true, 'data' => $this->_rowData($id), 'message' => lang('record_undone')]);
            } else {
                return $this->fail('Error occurred.');
            }
        } else {
            if ($this->taxesModel->delete($id)) {
                return $this->respondDeleted(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->fail('Record cannot be deleted.');
            }
        }
    }

    public function list_data()
    {
        $listData = $this->taxesModel->findAll();
        $result = [];
        foreach ($listData as $data) {
            $result[] = $this->_makeRow($data);
        }

        return $this->respond(['data' => $result]);
    }

    private function _rowData($id)
    {
        $data = $this->taxesModel->find($id);
        return $this->_makeRow($data);
    }

    private function _makeRow($data)
    {
        return [
            $data['title'],
            to_decimal_format($data['percentage']),
            modal_anchor(route_to('taxes.modal_form'), '<i class="fa fa-pencil"></i>', ['class' => 'edit', 'title' => lang('edit_tax'), 'data-post-id' => $data['id']])
                . js_anchor('<i class="fa fa-times fa-fw"></i>', ['title' => lang('delete_tax'), 'class' => 'delete', 'data-id' => $data['id'], 'data-action-url' => route_to('taxes.delete'), 'data-action' => 'delete'])
        ];
    }
}
