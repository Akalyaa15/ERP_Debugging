<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;

class Vap_category extends BaseController
{
    use ResponseTrait;

    protected $vapcategorymodel;

    public function __construct()
    {
        // Ensure to call the parent constructor
        parent::__construct();

        // Access control or any other initialization
        $this->access_only_admin();
        
        // Load the model
        $this->vapcategorymodel = new \App\Models\Vap_category_model(); // Adjust the model namespace as per your application
    }

    public function index()
    {
        return view('vap_category/index');
    }

    public function modal_form()
    {
        $this->validate([
            'id' => 'numeric'
        ]);

        $viewData['model_info'] = $this->vapcategorymodel->get_one($this->request->getPost('id'));
        return view('vap_category/modal_form', $viewData);
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
            //'hsn_description' => $this->request->getPost('hsn_description'),
            'status' => $this->request->getPost('status'),
            'description' => $this->request->getPost('description')
        ];

        $save_id = $this->vapcategorymodel->save($data, $id);
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
            'id' => 'numeric|required'
        ]);

        $id = $this->request->getPost('id');
        if ($this->request->getPost('undo')) {
            if ($this->vapcategorymodel->delete($id, true)) {
                return $this->respond([
                    'success' => true,
                    'data' => $this->_row_data($id),
                    'message' => lang('record_undone')
                ]);
            } else {
                return $this->fail(lang('error_occurred'));
            }
        } else {
            if ($this->vapcategorymodel->delete($id)) {
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
        $list_data = $this->vapcategorymodel->get_details()->getResult();
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
        $data = $this->vapcategorymodel->get_details($options)->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        return [
            $data->title,
            //$data->hsn_description ? nl2br($data->hsn_description) : "-",
            $data->description ? $data->description : "-",
            lang($data->status),
            modal_anchor(get_uri('vap_category/modal_form'), "<i class='fa fa-pencil'></i>", [
                'class' => 'edit',
                'title' => lang('edit_vap_category'),
                'data-post-id' => $data->id
            ]) .
            js_anchor("<i class='fa fa-times fa-fw'></i>", [
                'title' => lang('delete_vap_category'),
                'class' => 'delete',
                'data-id' => $data->id,
                'data-action-url' => get_uri('vap_category/delete'),
                'data-action' => 'delete-confirmation'
            ])
        ];
    }

}

