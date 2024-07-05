<?php

namespace App\Controllers;
use App\Models\ChequeCategoriesModel;
use CodeIgniter\API\ResponseTrait;
class ChequeCategories extends BaseController
{
    use ResponseTrait;

    protected $chequeCategoriesModel;

    public function __construct()
    {
        $this->chequeCategoriesModel = new ChequeCategoriesModel();
    }

    public function index()
    {
        return view('cheque_categories/index');
    }

    public function modal_form()
    {
        $id = $this->request->getPost('id');
        $viewData['model_info'] = $this->chequeCategoriesModel->find($id);
        return view('cheque_categories/modal_form', $viewData);
    }

    public function save()
    {
        $id = $this->request->getPost('id');
        
        $validationRules = [
            'id' => 'numeric',
            'title' => 'required'
        ];
        
        if (!$this->validate($validationRules)) {
            return $this->fail($this->validator->getErrors());
        }

        $data = [
            'title' => $this->request->getPost('title'),
            'status' => $this->request->getPost('status'),
            'description' => $this->request->getPost('description')
        ];

        $save_id = $this->chequeCategoriesModel->save($data, $id);
        
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
            $deleted = $this->chequeCategoriesModel->delete($id, true);
        } else {
            $deleted = $this->chequeCategoriesModel->delete($id);
        }

        if ($deleted) {
            return $this->respond(['success' => true, 'message' => lang('record_deleted')]);
        } else {
            return $this->fail(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
        }
    }

    public function list_data()
    {
        $list_data = $this->chequeCategoriesModel->findAll();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        return $this->respond(['data' => $result]);
    }

    private function _row_data($id)
    {
        $data = $this->chequeCategoriesModel->find($id);
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        return [
            $data['title'],
            $data['description'] ? $data['description'] : "-",
            lang($data['status']),
            modal_anchor('cheque_categories/modal_form', "<i class='fa fa-pencil'></i>", ['class' => "edit", 'title' => lang('edit_cheque_category'), 'data-post-id' => $data['id']])
            . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_cheque_category'), 'class' => "delete", 'data-id' => $data['id'], 'data-action-url' => 'cheque_categories/delete', 'data-action' => "delete-confirmation"])
        ];
    }
}

