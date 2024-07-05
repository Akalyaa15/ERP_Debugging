<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use App\Models\CompanyGroupsModel;

class Company_groups extends Controller
{
    protected $companygroupsmodel;

    public function __construct()
    {
        $this->companygroupsmodel = new CompanyGroupsModel(); // Instantiate your CompanyGroupsModel
        $this->access_only_admin(); // Example function to restrict access, adjust as needed
    }

    // Load client groups list view
    public function index()
    {
        return view("company_groups/index"); // Assuming `view()` is used to render views in CodeIgniter 4
    }

    // Load client groups add/edit modal form
    public function modal_form()
    {
        helper('form'); // Load the form helper if not already loaded
        helper('validation'); // Assuming `validate_submitted_data` is a custom validation helper

        validate_submitted_data([
            "id" => "numeric"
        ]);

        $view_data['model_info'] = $this->companygroupsmodel->getOne($this->request->getPost('id'));
        return view('company_groups/modal_form', $view_data);
    }

    // Save client groups category
    public function save()
    {
        helper('validation');

        validate_submitted_data([
            "id" => "numeric",
            "title" => "required"
        ]);

        $id = $this->request->getPost('id');
        $data = [
            "title" => $this->request->getPost('title'),
            "status" => $this->request->getPost('status'),
            "description" => $this->request->getPost('description')
        ];

        $save_id = $this->companygroupsmodel->save($data, $id);
        if ($save_id) {
            return $this->response->setJSON(["success" => true, "data" => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(["success" => false, 'message' => lang('error_occurred')]);
        }
    }

    // Delete/undo a client groups
    public function delete()
    {
        helper('validation');

        validate_submitted_data([
            "id" => "required|numeric"
        ]);

        $id = $this->request->getPost('id');
        if ($this->request->getPost('undo')) {
            if ($this->companygroupsmodel->delete($id, true)) {
                return $this->response->setJSON(["success" => true, "data" => $this->_row_data($id), "message" => lang('record_undone')]);
            } else {
                return $this->response->setJSON(["success" => false, lang('error_occurred')]);
            }
        } else {
            if ($this->companygroupsmodel->delete($id)) {
                return $this->response->setJSON(["success" => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->response->setJSON(["success" => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }

    // Get data for client groups list
    public function list_data()
    {
        $list_data = $this->companygroupsmodel->getDetails();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        return $this->response->setJSON(["data" => $result]);
    }

    // Get a client groups category list row
    private function _row_data($id)
    {
        $options = ["id" => $id];
        $data = $this->companygroupsmodel->getDetails($options);
        return $this->_make_row($data);
    }

    // Prepare a client groups category list row
    private function _make_row($data)
    {
        helper('language');

        return [
            $data->title,
            $data->description ? $data->description : "-",
            lang($data->status),
            modal_anchor(route_to('company_groups/modal_form'), "<i class='fa fa-pencil'></i>", ["class" => "edit", "title" => lang('edit_company_group'), "data-post-id" => $data->id])
            . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_company_group'), "class" => "delete", "data-id" => $data->id, "data-action-url" => route_to('company_groups/delete'), "data-action" => "delete-confirmation"])
        ];
    }

}

