ls class:name={condition}<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;

class Vat_types extends BaseController
{
    use ResponseTrait;

    protected $vattypesmodel;

    public function __construct()
    {
        parent::__construct();

        // Assuming these are custom functions from your previous setup
        $this->init_permission_checker("master_data");
        $this->access_only_allowed_members();

        // Load the model
        $this->vattypesmodel = new \App\Models\Vat_types_model(); // Adjust the model namespace as per your application
    }

    public function index()
    {
        return view('vat_types/index');
    }

    public function modal_form()
    {
        $this->validate([
            'id' => 'numeric'
        ]);

        $viewData['model_info'] = $this->vattypesmodel->get_one($this->request->getPost('id'));
        return view('vat_types/modal_form', $viewData);
    }

    public function save()
    {
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

        $save_id = $this->vattypesmodel->save($data, $id);
        if ($save_id) {
            return $this->respond([
                'success' => true,
                'data' => $this->_row_data($save_id),
                'id' => $save_id,
                'message' => lang('record_saved')
            ]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

    public function delete()
    {
        $this->validate([
            'id' => 'required|numeric'
        ]);

        $id = $this->request->getPost('id');
        if ($this->request->getPost('undo')) {
            if ($this->vattypesmodel->delete($id, true)) {
                return $this->respond([
                    'success' => true,
                    'data' => $this->_row_data($id),
                    'message' => lang('record_undone')
                ]);
            } else {
                return $this->fail(lang('error_occurred'));
            }
        } else {
            if ($this->vattypesmodel->delete($id)) {
                return $this->respond([
                    'success' => true,
                    'message' => lang('record_deleted')
                ]);
            } else {
                return $this->fail(lang('record_cannot_be_deleted'));
            }
        }
    }

    public function list_data()
    {
        $list_data = $this->vattypesmodel->get_details()->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        return $this->respond([
            'data' => $result
        ]);
    }

    private function _row_data($id)
    {
        $options = ['id' => $id];
        $data = $this->vattypesmodel->get_details($options)->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        return [
            $data->title,
            $data->description ? $data->description : "-",
            lang($data->status),
            modal_anchor(get_uri('vat_types/modal_form'), "<i class='fa fa-pencil'></i>", [
                'class' => 'edit',
                'title' => lang('edit_vat_type'),
                'data-post-id' => $data->id
            ]) .
            js_anchor("<i class='fa fa-times fa-fw'></i>", [
                'title' => lang('delete_vat_type'),
                'class' => 'delete',
                'data-id' => $data->id,
                'data-action-url' => get_uri('vat_types/delete'),
                'data-action' => 'delete-confirmation'
            ])
        ];
    }

}

