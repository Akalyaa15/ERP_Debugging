<?php

namespace App\Controllers;

use App\Models\GstStateCodeModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
class Gst_state_code extends Controller
{
    protected $gststatecodemodel;

    public function __construct()
    {
        // Call parent constructor
        parent::__construct();
        
        // Load the GST State Code model
        $this->gststatecodemodel = new GstStateCodeModel();
        
        // Example of access control (adjust as needed)
        // $this->access_only_admin();
    }

    public function index()
    {
        return view("gst_state_code/index");
    }
  public function modal_form()
    {
        // Validate incoming data
        $id = $this->request->getPost('id');
        if (!is_numeric($id)) {
            die('Invalid ID'); // Adjust error handling as per your application logic
        }

        $viewData['model_info'] = $this->gststatecodemodel->find($id);
        return view('gst_state_code/modal_form', $viewData);
    }

    public function save()
    {
        // Validate incoming data
        $rules = [
            'id' => 'numeric',
            'title' => 'required',
            'gstin_number_first_two_digits' => 'required'
        ];
        if (!$this->validate($rules)) {
            // Handle validation errors
            echo json_encode(['success' => false, 'message' => $this->validator->getErrors()]);
            return;
        }

        // Process valid data
        $id = $this->request->getPost('id');
        $data = [
            'title' => $this->request->getPost('title'),
            'gstin_number_first_two_digits' => $this->request->getPost('gstin_number_first_two_digits'),
            'state_code' => $this->request->getPost('state_code')
        ];

        $save_id = $this->gststatecodemodel->save($data, $id);
        if ($save_id) {
            echo json_encode(['success' => true, 'data' => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
        } else {
            echo json_encode(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function delete()
    {
        // Validate incoming data
        $id = $this->request->getPost('id');
        if (!is_numeric($id)) {
            die('Invalid ID'); // Adjust error handling as per your application logic
        }

        // Handle delete or undo
        $undo = $this->request->getPost('undo');
        if ($undo) {
            $success = $this->gststatecodemodel->delete($id, true);
        } else {
            $success = $this->gststatecodemodel->delete($id);
        }

        if ($success) {
            echo json_encode(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_' . ($undo ? 'undone' : 'deleted'))]);
        } else {
            echo json_encode(['success' => false, 'message' => lang('record_' . ($undo ? 'cannot_be_undone' : 'cannot_be_deleted'))]);
        }
    }

    public function list_data()
    {
        $list_data = $this->gststatecodemodel->findAll();
        $result = array_map(function ($data) {
            return $this->_make_row($data);
        }, $list_data);
        echo json_encode(['data' => $result]);
    }

    private function _row_data($id)
    {
        $data = $this->gststatecodemodel->find($id);
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        return [
            $data['title'],
            $data['gstin_number_first_two_digits'],
            $data['state_code'],
            modal_anchor(get_uri("gst_state_code/modal_form"), "<i class='fa fa-pencil'></i>", ['class' => "edit", 'title' => lang('edit_gst_state_code'), 'data-post-id' => $data['id']])
            . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete'), 'class' => "delete", 'data-id' => $data['id'], 'data-action-url' => get_uri("gst_state_code/delete"), 'data-action' => "delete-confirmation"])
        ];
    }
}

/* End of file Gst_state_code.php */
/* Location: ./app/Controllers/Gst_state_code.php */
