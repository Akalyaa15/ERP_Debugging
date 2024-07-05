<?php

namespace App\Controllers;

use App\Models\LutNumberModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

class Lut_number extends BaseController {
    protected $lutNumberModel;
    public function __construct() {
        $this->lutNumberModel = new LutNumberModel();
        helper(['form', 'url']);
    }
   public function index() {
        return view('lut_number/index');
    }

    public function modal_form() {
        $id = $this->request->getPost('id');

        $view_data['model_info'] = $this->lutNumberModel->find($id);
        return view('lut_number/modal_form', $view_data);
    }

    public function save() {
        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'numeric',
            'lut_year' => 'required',
            'lut_number' => 'required'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON(['success' => false, 'message' => $validation->getErrors()]);
        }

        $id = $this->request->getPost('id');
        $data = [
            'lut_year' => $this->request->getPost('lut_year'),
            'status' => $this->request->getPost('status'),
            'description' => $this->request->getPost('description'),
            'lut_number' => $this->request->getPost('lut_number')
        ];

        if ($this->lutNumberModel->save($data, $id)) {
            return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($id), 'id' => $id, 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function delete() {
        $validation = \Config\Services::validation();
        $validation->setRules(['id' => 'required|numeric']);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON(['success' => false, 'message' => $validation->getErrors()]);
        }

        $id = $this->request->getPost('id');
        if ($this->request->getPost('undo')) {
            if ($this->lutNumberModel->delete($id, true)) {
                return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_undone')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($this->lutNumberModel->delete($id)) {
                return $this->response->setJSON(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }

    public function list_data() {
        $list_data = $this->lutNumberModel->findAll();
        $result = array();
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        return $this->response->setJSON(['data' => $result]);
    }

    private function _row_data($id) {
        $data = $this->lutNumberModel->find($id);
        return $this->_make_row($data);
    }

    private function _make_row($data) {
        return [
            $data['lut_year'],
            $data['description'] ? $data['description'] : '-',
            $data['lut_number'],
            lang($data['status']),
            '<a href="#" class="edit" title="' . lang('edit_lut_number') . '" data-post-id="' . $data['id'] . '"><i class="fa fa-pencil"></i></a>' .
            '<a href="#" class="delete" title="' . lang('delete_hsn_sac_code') . '" data-id="' . $data['id'] . '" data-action-url="' . base_url('lut_number/delete') . '" data-action="delete-confirmation"><i class="fa fa-times fa-fw"></i></a>'
        ];
    }
}
