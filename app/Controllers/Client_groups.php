<?php

namespace App\Controllers;

use App\Models\ClientGroupsModel;
use CodeIgniter\API\ResponseTrait;

class ClientGroups extends BaseController
{
    use ResponseTrait;

    protected $clientGroupsModel;

    public function __construct()
    {
        $this->clientGroupsModel = new ClientGroupsModel();
        $this->accessOnlyAdmin(); // Assuming accessOnlyAdmin() is defined in BaseController or similar.
    }

    public function index()
    {
        return view('client_groups/index'); // Using view() helper to render views in CI4.
    }

    public function modal_form()
    {
        $id = $this->request->getPost('id');
        $viewData['model_info'] = $this->clientGroupsModel->getOne($id);
        return view('client_groups/modal_form', $viewData);
    }

    public function save()
    {
        $id = $this->request->getPost('id');
        $data = [
            "title" => $this->request->getPost('title'),
            "status" => $this->request->getPost('status'),
            "description" => $this->request->getPost('description')
        ];

        validate($data, [
            'id' => 'numeric',
            'title' => 'required'
        ]);

        $saveId = $this->clientGroupsModel->save($data, $id);
        if ($saveId) {
            return $this->respond([
                "success" => true,
                "data" => $this->_rowData($saveId),
                'id' => $saveId,
                'message' => lang('record_saved')
            ]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }
    public function delete()
    {
        $id = $this->request->getPost('id');
        if ($this->request->getPost('undo')) {
            if ($this->clientGroupsModel->delete($id, true)) {
                return $this->respond([
                    "success" => true,
                    "data" => $this->_rowData($id),
                    "message" => lang('record_undone')
                ]);
            } else {
                return $this->fail(lang('error_occurred'));
            }
        } else {
            if ($this->clientGroupsModel->delete($id)) {
                return $this->respond(["success" => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->fail(lang('record_cannot_be_deleted'));
            }
        }
    }

    public function list_data()
    {
        $listData = $this->clientGroupsModel->getDetails()->getResult();
        $result = [];
        foreach ($listData as $data) {
            $result[] = $this->_makeRow($data);
        }
        return $this->respond(["data" => $result]);
    }

    private function _rowData($id)
    {
        $options = ["id" => $id];
        $data = $this->clientGroupsModel->getDetails($options)->getRow();
        return $this->_makeRow($data);
    }
   private function _makeRow($data)
    {
        return [
            $data->title,
            $data->description ? $data->description : "-",
            lang($data->status),
            modal_anchor(get_uri("client_groups/modal_form"), "<i class='fa fa-pencil'></i>", ["class" => "edit", "title" => lang('edit_client_group'), "data-post-id" => $data->id])
            . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_client_group'), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("client_groups/delete"), "data-action" => "delete-confirmation"])
        ];
    }
}
