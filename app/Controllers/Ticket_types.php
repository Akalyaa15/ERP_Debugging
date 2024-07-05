<?php

namespace App\Controllers;

use App\Models\TicketTypesModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;

class Ticket_types extends ResourceController
{
    protected $modelName = 'App\Models\TicketTypesModel';

    public function __construct()
    {
        // Ensure to call the parent constructor
        parent::__construct();
        $this->access_only_admin();
    }

    public function index()
    {
        return view('ticket_types/index');
    }

    public function modal_form()
    {
        $id = $this->request->getPost('id');
        $viewData['model_info'] = $this->model->find($id);

        return view('ticket_types/modal_form', $viewData);
    }

    public function save()
    {
        $id = $this->request->getPost('id');
        $data = [
            'title' => $this->request->getPost('title'),
            'status' => $this->request->getPost('status'),
            'description' => $this->request->getPost('description')
        ];

        if ($this->model->save($data, $id)) {
            return $this->respondUpdated([
                'success' => true,
                'data' => $this->_row_data($id),
                'id' => $id,
                'message' => lang('record_saved')
            ]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

    public function delete($id = null)
    {
        $id = $this->request->getPost('id');

        if ($this->model->delete($id)) {
            return $this->respondDeleted([
                'success' => true,
                'message' => lang('record_deleted')
            ]);
        } else {
            return $this->fail(lang('record_cannot_be_deleted'));
        }
    }

    public function list_data()
    {
        $data = $this->model->findAll();
        $rows = [];
        foreach ($data as $row) {
            $rows[] = $this->_make_row($row);
        }

        return $this->respond([
            'data' => $rows
        ]);
    }

    private function _row_data($id)
    {
        $data = $this->model->find($id);
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        return [
            $data['title'],
            $data['description'] ? $data['description'] : "-",
            lang($data['status']),
            modal_anchor(route_to('ticket_types/modal_form'), '<i class="fa fa-pencil"></i>', [
                'class' => 'edit',
                'title' => lang('edit_ticket_type'),
                'data-post-id' => $data['id']
            ]) . js_anchor('<i class="fa fa-times fa-fw"></i>', [
                'title' => lang('delete_ticket_type'),
                'class' => 'delete',
                'data-id' => $data['id'],
                'data-action-url' => route_to('ticket_types/delete'),
                'data-action' => 'delete-confirmation'
            ])
        ];
    }
}
