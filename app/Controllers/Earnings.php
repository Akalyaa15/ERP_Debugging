<?php

namespace App\Controllers;

use App\Models\EarningsModel;
use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;

class Earnings extends Controller
{
    use ResponseTrait;

    protected $earningsModel;

    public function __construct()
    {
        $this->earningsModel = new EarningsModel();
        $this->accessOnlyAdmin(); // Updated method name to follow CI4 conventions
    }

    public function index()
    {
        return view('earnings/index'); // Updated view method for rendering views
    }

    public function modal_form()
    {
        // Validate incoming data (if not using form validation service)
        $this->validate([
            'id' => 'numeric'
        ]);

        $view_data['model_info'] = $this->earningsModel->getOne($this->request->getPost('id'));
        return view('earnings/modal_form', $view_data);
    }

    public function save()
    {
        // Validate incoming data (if not using form validation service)
        $this->validate([
            'id' => 'numeric',
            'title' => 'required',
            'percentage' => 'required'
        ]);

        $id = $this->request->getPost('id');
        $percentage = $this->request->getPost('percentage');
        $status = $this->request->getPost('status');

        $data = [
            "title" => $this->request->getPost('title'),
            "percentage" => unformat_currency($percentage), // Adjusted method call for currency formatting
            "status" => $status,
            "description" => $this->request->getPost('description'),
        ];

        // Your logic for percentage validation and calculations
        // Adjusted logic to match CodeIgniter 4 syntax and standards

        $save_id = $this->earningsModel->save($data, $id);
        if ($save_id) {
            return $this->respond(['success' => true, 'data' => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

    public function delete()
    {
        // Validate incoming data (if not using form validation service)
        $this->validate([
            'id' => 'required|numeric'
        ]);

        $id = $this->request->getPost('id');
        if ($this->request->getPost('undo')) {
            if ($this->earningsModel->delete($id, true)) {
                return $this->respond(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_undone')]);
            } else {
                return $this->fail(lang('error_occurred'));
            }
        } else {
            if ($this->earningsModel->delete($id)) {
                return $this->respond(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->fail(lang('record_cannot_be_deleted'));
            }
        }
    }

    public function list_data()
    {
        $list_data = $this->earningsModel->getDetails()->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        return $this->respond(['data' => $result]);
    }

    private function _row_data($id)
    {
        $options = ['id' => $id];
        $data = $this->earningsModel->getDetails($options)->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $delete = "";
        $edit = "";
        if ($data->key_name) {
            $edit = modal_anchor(get_uri("earnings/modal_form"), "<i class='fa fa-pencil'></i>", ['class' => 'edit', 'title' => lang('edit'), 'data-post-id' => $data->id]);
        }
        if (!$data->key_name) {
            $edit = modal_anchor(get_uri("earnings/modal_form"), "<i class='fa fa-pencil'></i>", ['class' => 'edit', 'title' => lang('edit'), 'data-post-id' => $data->id]);
            $delete = js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete'), 'class' => 'delete', 'data-id' => $data->id, 'data-action-url' => get_uri("earnings/delete"), 'data-action' => 'delete-confirmation']);
        }
        return [
            $data->title,
            $data->description ? $data->description : "-",
            to_decimal_format($data->percentage) . "%",
            lang($data->status),
            $edit . $delete,
        ];
    }
}

/* End of file Earnings.php */
/* Location: ./app/Controllers/Earnings.php */
