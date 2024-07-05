<?php

namespace App\Controllers;
use App\Models\DcTypesModel;
use CodeIgniter\Controller;

class Dc_types extends BaseController
{
    protected $dctypesmodel;

    public function __construct()
    {
        $this->dctypesmodel = new DcTypesModel();
        parent::__construct();
        $this->accessOnlyAdmin();
    }

    public function index()
    {
        return view("dc_types/index");
    }

    public function modal_form()
    {
        $this->validate([
            'id' => 'numeric'
        ]);

        $view_data['model_info'] = $this->dctypesmodel->find($this->request->getPost('id'));
        return view('dc_types/modal_form', $view_data);
    }

    public function save()
    {
        $this->validate([
            'id' => 'numeric',
            'title' => 'required'
        ]);

        $id = $this->request->getPost('id');
        $data = [
            "title" => $this->request->getPost('title'),
            "status" => $this->request->getPost('status'),
            "description" => $this->request->getPost('description'),
        ];

        $save_id = $this->dctypesmodel->save($data, $id);
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
            $success = $this->dctypesmodel->delete($id, true);
        } else {
            $success = $this->dctypesmodel->delete($id);
        }

        if ($success) {
            return json_encode(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_undone')]);
        } else {
            return json_encode(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function list_data()
    {
        $list_data = $this->dctypesmodel->findAll();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        return json_encode(['data' => $result]);
    }

    private function _row_data($id)
    {
        $data = $this->dctypesmodel->find($id);
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        return [
            $data['title'],
            $data['description'] ? $data['description'] : "-",
            lang($data['status']),
            modal_anchor("dc_types/modal_form", "<i class='fa fa-pencil'></i>", ['class' => 'edit', 'title' => lang('edit_dc_type'), 'data-post-id' => $data['id']])
                . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_dc_type'), 'class' => 'delete', 'data-id' => $data['id'], 'data-action-url' => "dc_types/delete", 'data-action' => 'delete-confirmation'])
        ];
    }
}

/* End of file Dc_types.php */
/* Location: ./app/Controllers/Dc_types.php */
