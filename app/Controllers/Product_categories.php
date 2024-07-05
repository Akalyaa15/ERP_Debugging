<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class Product_categories extends BaseController {
    protected $productcategoriesmodel;
    protected $usersmodel;

    public function __construct() {
        parent::__construct();
        $this->init_permission_checker("production_data");
    }

    // Load expense categories list view
    public function index() {
        $this->check_module_availability("module_production_data");

        if ($this->login_user->is_admin == "1" || $this->login_user->user_type == "staff") {
            if ($this->access_type != "all" && !in_array($this->login_user->id, $this->allowed_members)) {
                return redirect()->to("forbidden");
            }
            $this->template->render("product_categories/index");
        } else {
            $this->template->render("product_categories/index");
        }
    }
   // Load expense category add/edit modal form
    public function modal_form() {
        validate_submitted_data(array(
            "id" => "numeric"
        ));

        $view_data['model_info'] = $this->Product_categories_model->get_one($this->input->post('id'));
        return $this->response->setJSON($view_data);
    }

    // Save expense category
    public function save() {
        validate_submitted_data(array(
            "id" => "numeric",
            "title" => "required"
        ));

        $id = $this->input->post('id');
        $data = array(
            "title" => $this->input->post('title'),
            "status" => $this->input->post('status'),
            "description" => $this->input->post('description'),
            "last_activity_user" => $this->login_user->id,
            "last_activity" => get_current_utc_time(),
        );

        // Check duplicate product category
        if (!$id) {
            if ($this->Product_categories_model->is_product_category_list_exists($data["title"])) {
                return $this->response->setJSON(array("success" => false, 'message' => lang('product_category_already')));
            }
        } else {
            if ($this->Product_categories_model->is_product_category_list_exists($data["title"], $id)) {
                return $this->response->setJSON(array("success" => false, 'message' => lang('product_category_already')));
            }
        }

        // Save data
        $save_id = $this->Product_categories_model->save($data, $id);
        if ($save_id) {
            return $this->response->setJSON(array("success" => true, "data" => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')));
        } else {
            return $this->response->setJSON(array("success" => false, 'message' => lang('error_occurred')));
        }
    }

    // Delete/undo an expense category
    public function delete() {
        validate_submitted_data(array(
            "id" => "required|numeric"
        ));

        $id = $this->input->post('id');
        if ($this->input->post('undo')) {
            if ($this->Product_categories_model->delete($id, true)) {
                return $this->response->setJSON(array("success" => true, "data" => $this->_row_data($id), "message" => lang('record_undone')));
            } else {
                return $this->response->setJSON(array("success" => false, 'message' => lang('error_occurred')));
            }
        } else {
            if ($this->Product_categories_model->delete($id)) {
                return $this->response->setJSON(array("success" => true, 'message' => lang('record_deleted')));
            } else {
                return $this->response->setJSON(array("success" => false, 'message' => lang('record_cannot_be_deleted')));
            }
        }
    }

    // Get data for expense category list
    public function list_data() {
        $list_data = $this->Product_categories_model->get_details()->getResult();
        $result = array();
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        return $this->response->setJSON(array("data" => $result));
    }

    // Get an expense category list row
    private function _row_data($id) {
        $options = array("id" => $id);
        $data = $this->Product_categories_model->get_details($options)->getRow();
        return $this->_make_row($data);
    }

    // Prepare an expense category list row
    private function _make_row($data) {
        // Prepare your row data as per your requirements
        return array(
            $data->title,
            $data->description ? $data->description : "-",
            lang($data->status),
            // Add other fields as needed
            modal_anchor(get_uri("product_categories/modal_form"), "<i class='fa fa-pencil'></i>", array("class" => "edit", "title" => lang('edit_product_category'), "data-post-id" => $data->id))
                . js_anchor("<i class='fa fa-times fa-fw'></i>", array('title' => lang('delete_products_category'), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("product_categories/delete"), "data-action" => "delete-confirmation"))
        );
    }
}

