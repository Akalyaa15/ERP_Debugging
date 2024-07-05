<?php

namespace App\Controllers;

use App\Models\ExpenseCategoriesModel; // Adjust the model path as per your application structure
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Validation\Exceptions\ValidationException;

class Expense_categories extends Controller
{
    protected $expenseCategoriesModel;

    public function __construct()
    {
        $this->expenseCategoriesModel = new ExpenseCategoriesModel(); // Load the model
        $this->accessOnlyAdmin(); // Ensure only admins can access (adjust as per your access control method)
    }

    public function index()
    {
        return view('expense_categories/index'); // Render the expense categories list view
    }

    public function modal_form()
    {
        $this->validate([
            'id' => 'numeric'
        ]);

        $viewData['model_info'] = $this->expenseCategoriesModel->getOne($this->request->getPost('id'));
        echo view('expense_categories/modal_form', $viewData);
    }

    public function save()
    {
        try {
            $this->validate([
                'id' => 'numeric',
                'title' => 'required'
            ]);
        } catch (ValidationException $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }

        $id = $this->request->getPost('id');
        $data = [
            'title' => $this->request->getPost('title'),
            'status' => $this->request->getPost('status'),
            'description' => $this->request->getPost('description')
        ];

        $save_id = $this->expenseCategoriesModel->save($data, $id);
        if ($save_id) {
            return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function delete()
    {
        $this->validate([
            'id' => 'required|numeric'
        ]);

        $id = $this->request->getPost('id');
        if ($this->request->getPost('undo')) {
            if ($this->expenseCategoriesModel->delete($id, true)) {
                return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_undone')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($this->expenseCategoriesModel->delete($id)) {
                return $this->response->setJSON(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }

    public function list_data()
    {
        $listData = $this->expenseCategoriesModel->getDetails()->getResult();
        $result = [];
        foreach ($listData as $data) {
            $result[] = $this->_make_row($data);
        }
        return $this->response->setJSON(['data' => $result]);
    }

    private function _row_data($id)
    {
        $options = ['id' => $id];
        $data = $this->expenseCategoriesModel->getDetails($options)->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        return [
            $data->title,
            $data->description ? $data->description : "-",
            lang($data->status),
            modal_anchor(get_uri('expense_categories/modal_form'), "<i class='fa fa-pencil'></i>", ['class' => 'edit', 'title' => lang('edit_expenses_category'), 'data-post-id' => $data->id])
                . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_expenses_category'), 'class' => 'delete', 'data-id' => $data->id, 'data-action-url' => get_uri('expense_categories/delete'), 'data-action' => 'delete-confirmation'])
        ];
    }
}

/* End of file Expense_categories.php */
/* Location: ./app/Controllers/Expense_categories.php */
