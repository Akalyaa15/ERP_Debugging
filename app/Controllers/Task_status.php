<?php

namespace App\Controllers;

use App\Models\TaskStatusModel;
use CodeIgniter\API\ResponseTrait;

class TaskStatus extends BaseController
{
    use ResponseTrait;

    protected $taskStatusModel;

    public function __construct()
    {
        $this->taskStatusModel = new TaskStatusModel();
        $this->accessOnlyAdmin(); // Assuming this is a custom method for admin access control
    }

    public function index()
    {
        return view('task_status/index'); // Assuming you are using the default view folder setup
    }

    public function modal_form()
    {
        $id = $this->request->getPost('id');
        helper(['form', 'url']);

        if (!is_numeric($id)) {
            return $this->failValidation('Invalid ID');
        }

        $viewData['model_info'] = $this->taskStatusModel->find($id);
        return view('task_status/modal_form', $viewData);
    }

    public function save()
    {
        helper(['form', 'url']);
        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'numeric',
            'title' => 'required',
            'color' => 'required'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $id = $this->request->getPost('id');
        $data = [
            'title' => $this->request->getPost('title'),
            'color' => $this->request->getPost('color')
        ];

        if (!$id) {
            $maxSortValue = $this->taskStatusModel->getMaxSortValue();
            $data['sort'] = $maxSortValue + 1;
        }

        $saveId = $this->taskStatusModel->save($data, $id);
        if ($saveId) {
            return $this->respondCreated(['success' => true, 'data' => $this->_rowData($saveId), 'id' => $saveId, 'message' => lang('record_saved')]);
        } else {
            return $this->fail('Error occurred.');
        }
    }

    public function update_field_sort_values($id = 0)
    {
        $sortValues = $this->request->getPost("sort_values");
        if ($sortValues) {
            $sortArray = explode(",", $sortValues);
            foreach ($sortArray as $value) {
                $sortItem = explode("-", $value);
                $id = $sortItem[0];
                $sort = $sortItem[1];

                $data = ['sort' => $sort];
                $this->taskStatusModel->save($data, $id);
            }
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
            if ($this->taskStatusModel->delete($id, true)) {
                return $this->respondDeleted(['success' => true, 'data' => $this->_rowData($id), 'message' => lang('record_undone')]);
            } else {
                return $this->fail('Error occurred.');
            }
        } else {
            if ($this->taskStatusModel->delete($id)) {
                return $this->respondDeleted(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->fail('Record cannot be deleted.');
            }
        }
    }

    public function list_data()
    {
        $listData = $this->taskStatusModel->findAll();
        $result = [];
        foreach ($listData as $data) {
            $result[] = $this->_makeRow($data);
        }

        return $this->respond(['data' => $result]);
    }

    private function _rowData($id)
    {
        $data = $this->taskStatusModel->find($id);
        return $this->_makeRow($data);
    }

    private function _makeRow($data)
    {
        $delete = '';
        $edit = '';

        if (!$data['key_name']) {
            $edit = '<a href="' . route_to('task_status.modal_form') . '/' . $data['id'] . '" class="edit" title="' . lang('edit_task_status') . '"><i class="fa fa-pencil"></i></a>';
            $delete = js_anchor('<i class="fa fa-times fa-fw"></i>', ['title' => lang('delete_task_status'), 'class' => 'delete', 'data-id' => $data['id'], 'data-action-url' => route_to('task_status.delete'), 'data-action' => 'delete-confirmation']);
        }

        return [
            $data['sort'],
            '<div class="pt10 pb10 field-row" data-id="' . $data['id'] . '"><i class="fa fa-bars pull-left move-icon"></i> <span style="background-color:' . $data['color'] . '" class="color-tag  pull-left"></span>' . ($data['key_name'] ? lang($data['key_name']) : $data['title']) . '</div>',
            $edit . $delete
        ];
    }
}
