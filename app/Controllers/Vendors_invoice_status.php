<?php

namespace App\Controllers;

use App\Models\VendorsInvoiceStatusModel;
use CodeIgniter\API\ResponseTrait;

class Vendors_invoice_status extends BaseController
{
    use ResponseTrait;

    protected $vendorsInvoiceStatusModel;

    public function __construct()
    {
        parent::__construct();

        // Load necessary helpers, libraries, and models
        helper(['form', 'url']);
        $this->vendorsInvoiceStatusModel = new VendorsInvoiceStatusModel();
        $this->access_only_admin(); // Assuming this is a method in your BaseController or a custom helper
    }

    public function index()
    {
        return view('vendors_invoice_status/index');
    }

    public function modal_form()
    {
        $id = $this->request->getPost('id');
        $model_info = $this->vendorsInvoiceStatusModel->find($id);

        $viewData['model_info'] = $model_info;
        return view('vendors_invoice_status/modal_form', $viewData);
    }

    public function save()
    {
        $id = $this->request->getPost('id');
        $data = [
            'title' => $this->request->getPost('title'),
            'color' => $this->request->getPost('color')
        ];

        if (!$id) {
            // Get max sort value and increase it
            $max_sort_value = $this->vendorsInvoiceStatusModel->max('sort');
            $data['sort'] = ($max_sort_value ?? 0) + 1;
        }

        $save_id = $this->vendorsInvoiceStatusModel->save($data, $id);

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

    public function update_field_sort_values()
    {
        $sort_values = $this->request->getPost("sort_values");

        if ($sort_values) {
            $sort_array = explode(",", $sort_values);

            foreach ($sort_array as $value) {
                $sort_item = explode("-", $value);
                $id = $sort_item[0];
                $sort = $sort_item[1];

                $data = ["sort" => $sort];
                $this->vendorsInvoiceStatusModel->save($data, $id);
            }
        }
    }

    public function delete()
    {
        $id = $this->request->getPost('id');

        if ($this->request->getPost('undo')) {
            if ($this->vendorsInvoiceStatusModel->delete($id, true)) {
                return $this->respond([
                    'success' => true,
                    'data' => $this->_row_data($id),
                    'message' => lang('record_undone')
                ]);
            } else {
                return $this->fail(lang('error_occurred'));
            }
        } else {
            if ($this->vendorsInvoiceStatusModel->delete($id)) {
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
        $list_data = $this->vendorsInvoiceStatusModel->findAll();
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
        $data = $this->vendorsInvoiceStatusModel->find($id);
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $edit = modal_anchor('vendors_invoice_status/modal_form', '<i class="fa fa-pencil"></i>', [
            'class' => 'edit',
            'title' => lang('edit_task_status'),
            'data-post-id' => $data['id']
        ]);

        $delete = js_anchor('<i class="fa fa-times fa-fw"></i>', [
            'title' => lang('delete_task_status'),
            'class' => 'delete',
            'data-id' => $data['id'],
            'data-action-url' => 'vendors_invoice_status/delete',
            'data-action' => 'delete-confirmation'
        ]);

        return [
            $data['sort'],
            '<div class="pt10 pb10 field-row" data-id="' . $data['id'] . '"><i class="fa fa-bars pull-left move-icon"></i> <span style="background-color:' . $data['color'] . '" class="color-tag pull-left"></span>' . ($data['key_name'] ? lang($data['key_name']) : $data['title']) . '</div>',
            $edit . $delete
        ];
    }

}
