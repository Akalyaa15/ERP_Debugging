<?php

namespace App\Controllers;

use App\Models\Leave_types_model;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class Leave_types extends BaseController
{
    protected $leavetypesmodel;

    public function __construct(
        RequestInterface $request,
        ResponseInterface $response,
        LoggerInterface $logger
    ) {
        parent::__construct($request, $response, $logger);

        $this->leavetypesmodel = new Leave_types_model();
        $this->access_only_admin(); // Assuming this is defined in BaseController or another helper function
    }

    // Load leave type list view
    public function index()
    {
        return view('leave_types/index');
    }

    // Load leave type add/edit form
    public function modal_form()
    {
        $view_data['model_info'] = $this->leavetypesmodel->find($this->request->getPost('id'));
        echo view('leave_types/modal_form', $view_data);
    }

    // Save leave type
    public function save()
    {
        helper(['form', 'url']);

        $validationRules = [
            'id' => 'numeric',
            'title' => 'required',
        ];
        if (!$this->validate($validationRules)) {
            echo json_encode(['success' => false, 'message' => $this->validator->getErrors()]);
            return;
        }

        $id = $this->request->getPost('id');
        $data = [
            'title' => $this->request->getPost('title'),
            'status' => $this->request->getPost('status'),
            'description' => $this->request->getPost('description'),
            'color' => $this->request->getPost('color'),
        ];

        $save_id = $this->leavetypesmodel->save($data, $id);
        if ($save_id) {
            echo json_encode(['success' => true, 'data' => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
        } else {
            echo json_encode(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    // Delete/undo a leave type
    public function delete()
    {
        helper(['form', 'url']);

        $validationRules = [
            'id' => 'required|numeric',
        ];
        if (!$this->validate($validationRules)) {
            echo json_encode(['success' => false, 'message' => $this->validator->getErrors()]);
            return;
        }

        $id = $this->request->getPost('id');
        if ($this->request->getPost('undo')) {
            if ($this->leavetypesmodel->delete($id, true)) {
                echo json_encode(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_undone')]);
            } else {
                echo json_encode(['success' => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($this->leavetypesmodel->delete($id)) {
                echo json_encode(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                echo json_encode(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }

    // Prepare leave types list data for datatable
    public function list_data()
    {
        $list_data = $this->leavetypesmodel->findAll();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        echo json_encode(['data' => $result]);
    }

    // Get a row of leave types row
    private function _row_data($id)
    {
        $data = $this->leavetypesmodel->find($id);
        return $this->_make_row($data);
    }

    // Make a row of leave types row
    private function _make_row($data)
    {
        return [
            "<span style='background-color:" . $data['color'] . "' class='color-tag pull-left'></span>" . $data['title'],
            $data['description'] ? $data['description'] : "-",
            lang($data['status']),
            modal_anchor(get_uri('leave_types/modal_form'), "<i class='fa fa-pencil'></i>", ['class' => 'edit', 'title' => lang('edit_leave_type'), 'data-post-id' => $data['id']])
                . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_leave_type'), 'class' => 'delete', 'data-id' => $data['id'], 'data-action-url' => get_uri('leave_types/delete'), 'data-action' => 'delete-confirmation']),
        ];
    }
}

/* End of file Leave_types.php */
/* Location: ./app/Controllers/Leave_types.php */
