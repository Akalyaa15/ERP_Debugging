<?php

namespace App\Controllers;

use App\Models\Items_model;
use CodeIgniter\API\ResponseTrait;

class Items extends BaseController
{
    use ResponseTrait;

    protected $items_model;

    public function __construct()
    {
        $this->items_model = new Items_model();
        $this->access_only_team_members();
    }

    protected function validate_access_to_items()
    {
        $access_invoice = $this->get_access_info("invoice");
        $access_estimate = $this->get_access_info("estimate");

        // Check if invoice or estimate module is enabled
        if (!(get_setting("module_invoice") == "1" || get_setting("module_estimate") == "1")) {
            return redirect()->to('forbidden');
        }

        // Allow admins or users with all access to view items
        if ($this->login_user->is_admin ||
            $access_invoice->access_type === "all" ||
            $access_estimate->access_type === "all") {
            return true;
        } else {
            return redirect()->to('forbidden');
        }
    }

    // Load items list view
    public function index()
    {
        $this->validate_access_to_items();

        return view('items/index');
    }

    // Load item modal form
    public function modal_form()
    {
        $this->validate_access_to_items();

        $id = $this->request->getPost('id');
        $view_data['model_info'] = $this->items_model->find($id);

        return view('items/modal_form', $view_data);
    }

    // Add or edit an item
    public function save()
    {
        $this->validate_access_to_items();

        $id = $this->request->getPost('id');

        $rules = [
            'title' => 'required',
            'unit_type' => 'permit_empty',
            'item_rate' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $item_data = [
            "title" => $this->request->getPost('title'),
            "description" => $this->request->getPost('description'),
            "unit_type" => $this->request->getPost('unit_type'),
            "rate" => unformat_currency($this->request->getPost('item_rate'))
        ];

        $item_id = $this->items_model->save($item_data, $id);

        if ($item_id) {
            $item_info = $this->items_model->find($item_id);
            return $this->respondCreated([
                'success' => true,
                'id' => $item_info['id'],
                'data' => $this->_make_item_row($item_info),
                'message' => lang('record_saved')
            ]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

    // Delete or undo an item
    public function delete()
    {
        $this->validate_access_to_items();

        $id = $this->request->getPost('id');
        $undo = $this->request->getPost('undo');

        if ($undo && $this->items_model->delete($id, true)) {
            $item_info = $this->items_model->find($id);
            return $this->respondDeleted([
                'success' => true,
                'id' => $item_info['id'],
                'data' => $this->_make_item_row($item_info),
                'message' => lang('record_undone')
            ]);
        } elseif (!$undo && $this->items_model->delete($id)) {
            return $this->respondDeleted([
                'success' => true,
                'message' => lang('record_deleted')
            ]);
        } else {
            return $this->fail(lang('record_cannot_be_deleted'));
        }
    }

    // Get list data for items
    public function list_data()
    {
        $this->validate_access_to_items();

        $list_data = $this->items_model->findAll();
        $result = array_map(function ($data) {
            return $this->_make_item_row($data);
        }, $list_data);

        return $this->respond([
            'data' => $result
        ]);
    }

    // Prepare a row for item list table
    private function _make_item_row($data)
    {
        $type = $data['unit_type'] ? $data['unit_type'] : "";

        return [
            $data['title'],
            nl2br($data['description']),
            $type,
            $data['rate'],
            modal_anchor(get_uri("items/modal_form"), "<i class='fa fa-pencil'></i>", [
                "class" => "edit",
                "title" => lang('edit_item'),
                "data-post-id" => $data['id']
            ]) . js_anchor("<i class='fa fa-times fa-fw'></i>", [
                'title' => lang('delete'),
                "class" => "delete",
                "data-id" => $data['id'],
                "data-action-url" => get_uri("items/delete"),
                "data-action" => "delete"
            ])
        ];
    }

}

/* End of file Items.php */
/* Location: ./app/Controllers/Items.php */
