<?php

namespace App\Controllers;

use App\Models\ModeOfDispatchModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

class Mode_of_dispatch extends BaseController {
    protected $modeOfDispatchModel;

    public function __construct() {
        $this->modeOfDispatchModel = new ModeOfDispatchModel();
        helper(['form', 'url']);
        $this->access_only_admin();
    }

    public function index() {
        return view('mode_of_dispatch/index');
    }

    public function modal_form() {
        $id = $this->request->getPost('id');
        $view_data['model_info'] = $this->modeOfDispatchModel->find($id);
        return view('mode_of_dispatch/modal_form', $view_data);
    }

    public function save() {
        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'numeric',
            'title' => 'required'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON(['success' => false, 'message' => $validation->getErrors()]);
        }

        $id = $this->request->getPost('id');
        $data = [
            'title' => $this->request->getPost('title'),
            'status' => $this->request->getPost('status'),
            'description' => $this->request->getPost('description')
        ];

        $save_id = $this->modeOfDispatchModel->save($data, $id);
        if ($save_id) {
            return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
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
            if ($this->modeOfDispatchModel->delete($id, true)) {
                return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_undone')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($this->modeOfDispatchModel->delete($id)) {
                return $this->response->setJSON(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }

    public function list_data() {
        $list_data = $this->modeOfDispatchModel->findAll();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        return $this->response->setJSON(['data' => $result]);
    }

    private function _row_data($id) {
        $data = $this->modeOfDispatchModel->find($id);
        return $this->_make_row($data);
    }

    private function _make_row($data) {
        return [
            $data['title'],
            $data['description'] ? $data['description'] : "-",
            lang($data['status']),
            modal_anchor(get_uri("mode_of_dispatch/modal_form"), "<i class='fa fa-pencil'></i>", ["class" => "edit", "title" => lang('edit_mode_of_dispatch'), "data-post-id" => $data['id']]) .
            js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_mode_of_dispatch'), "class" => "delete", "data-id" => $data['id'], "data-action-url" => get_uri("mode_of_dispatch/delete"), "data-action" => "delete-confirmation"])
        ];
    }
}
