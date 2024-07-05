<?php

namespace App\Controllers;

use App\Models\PartnerGroupsModel;

class Partner_groups extends BaseController {
    protected $partnerGroupsModel;

    public function __construct() {
        $this->partnerGroupsModel = new PartnerGroupsModel();
        $this->access_only_admin(); // Assuming this is a custom method for access control
    }

    public function index() {
        return view('partner_groups/index');
    }

    public function modal_form() {
        $this->validate([
            'id' => 'numeric'
        ]);
   $viewData['model_info'] = $this->partnerGroupsModel->find($this->request->getPost('id'));
        return view('partner_groups/modal_form', $viewData);
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

        $save_id = $this->partnerGroupsModel->save($data, $id);
        if ($save_id) {
            return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
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
            if ($this->partnerGroupsModel->delete($id, true)) {
                return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_undone')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($this->partnerGroupsModel->delete($id)) {
                return $this->response->setJSON(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }

    public function list_data() {
        $list_data = $this->partnerGroupsModel->findAll();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        return $this->response->setJSON(['data' => $result]);
    }

    private function _row_data($id) {
        $data = $this->partnerGroupsModel->find($id);
        return $this->_make_row($data);
    }

    private function _make_row($data) {
        return [
            $data['title'],
            $data['description'] ? $data['description'] : "-",
            lang($data['status']), // Assuming lang() is a helper function for language translations
            modal_anchor(route_to('partner_groups/modal_form'), "<i class='fa fa-pencil'></i>", ['class' => 'edit', 'title' => lang('edit_client_group'), 'data-post-id' => $data['id']])
                . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_client_group'), 'class' => 'delete', 'data-id' => $data['id'], 'data-action-url' => route_to('partner_groups/delete'), 'data-action' => 'delete-confirmation'])
        ];
    }
}

