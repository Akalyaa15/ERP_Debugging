<?php

namespace App\Controllers;

use App\Models\DeductionsModel;
use CodeIgniter\Controller;

class Deductions extends BaseController
{
    protected $deductionsmodel;

    public function __construct()
    {
        $this->deductionsmodel = new DeductionsModel();
        $this->accessOnlyAdmin();
    }

    public function index()
    {
        return view('deductions/index');
    }

    public function modal_form()
    {
        $this->validate([
            'id' => 'numeric'
        ]);

        $view_data['model_info'] = $this->deductionsmodel->find($this->request->getPost('id'));
        return view('deductions/modal_form', $view_data);
    }

    public function save()
    {
        $this->validate([
            'id' => 'numeric',
            'title' => 'required',
            'percentage' => 'required'
        ]);

        $id = $this->request->getPost('id');
        $data = [
            "title" => $this->request->getPost('title'),
            "percentage" => unformat_currency($this->request->getPost('percentage')),
            "status" => $this->request->getPost('status'),
            "description" => $this->request->getPost('description'),
        ];

        $save_id = $this->deductionsmodel->save($data, $id);
        if ($save_id) {
            return json_encode(['success' => true, 'data' => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
        } else {
            return json_encode(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function delete()
    {
        $this->validate([
            'id' => 'required|numeric'
        ]);

        $id = $this->request->getPost('id');
        $undo = $this->request->getPost('undo');

        if ($undo) {
            $success = $this->deductionsmodel->delete($id, true);
        } else {
            $success = $this->deductionsmodel->delete($id);
        }

        if ($success) {
            return json_encode(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_undone')]);
        } else {
            return json_encode(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function list_data()
    {
        $list_data = $this->deductionsmodel->findAll();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        return json_encode(['data' => $result]);
    }

    private function _row_data($id)
    {
        $data = $this->deductionsmodel->find($id);
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        return [
            $data['title'],
            $data['description'] ?: "-",
            to_decimal_format($data['percentage']) . "%",
            lang($data['status']),
            modal_anchor("deductions/modal_form", "<i class='fa fa-pencil'></i>", ['class' => 'edit', 'title' => lang('edit_deduction'), 'data-post-id' => $data['id']])
                . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_tax'), 'class' => 'delete', 'data-id' => $data['id'], 'data-action-url' => "deductions/delete", 'data-action' => 'delete-confirmation'])
        ];
    }
}

/* End of file Deductions.php */
/* Location: ./app/Controllers/Deductions.php */
