<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\CustomFieldsModel;
use App\Models\VendorsModel;
use App\Models\PurchaseOrdersModel;
use App\Models\TaxesModel;
use App\Models\PaymentMethodsModel;
use App\Models\LutNumberModel;

class PurchaseOrders extends BaseController
{
    protected $customfieldsmodel;
    protected $vendorsmodel;
    protected $purchaseordersmodel;
    protected $taxesmodel;
    protected $paymentmethodsmodel;
    protected $lutnumbermodel;
    protected $purchaseorderitemsmodel;
    protected $partnogenerationmodel;
    protected $hsnsaccodemodel;
    protected $countriesmodel;

    public function __construct()
    {
        $this->customfieldsmodel = new CustomFieldsModel();
        $this->vendorsmodel = new VendorsModel();
        $this->purchaseordersmodel = new PurchaseOrdersModel();
        $this->taxesmodel = new TaxesModel();
        $this->paymentmethodsmodel = new PaymentMethodsModel();
        $this->lutnumbermodel = new LutNumberModel();
        // Add other model instantiations as needed

        parent::__construct();
        $this->init_permission_checker("purchase_order");
    }

    public function index()
    {
        $this->check_module_availability("module_purchase_order");
    
        $view_data["custom_field_headers"] = $this->customfieldsmodel->get_custom_field_headers_for_table("estimates", $this->session->get('is_admin'), $this->session->get('user_type'));
    
        if ($this->session->get('user_type') === "staff") {
            $this->access_only_allowed_members();
    
            return view("purchase_orders/index", $view_data);
        } else {
            // Client view
            $view_data["vendor_info"] = $this->vendorsmodel->find($this->session->get('vendor_id'));
            $view_data['vendor_id'] = $this->session->get('vendor_id');
            $view_data['page_type'] = "full";
    
            // Example for module estimate request check
            // if (get_setting("module_estimate_request") == "1") {
            //     $view_data['can_request_estimate'] = true;
            // }
    
            return view("vendors/purchase_orders/vendor_portal", $view_data);
        }
    }
    public function yearly()
    {
        return view("purchase_orders/yearly_purchase_orders");
    }

    public function modal_form()
    {
        $this->access_only_allowed_members();
    
        $validation = \Config\Services::validation();
        $validation->setRule('id', 'ID', 'numeric');
        $validation->setRule('vendor_id', 'Vendor ID', 'numeric');
    
        if (!$validation->withRequest($this->request)->run()) {
            return redirect()->back()->withInput()->with('errors', $validation->getErrors());
        }
    
        $vendor_id = $this->request->getPost('vendor_id');
        $view_data['model_info'] = $this->purchaseordersmodel->find($this->request->getPost('id'));
    
        $project_client_id = $client_id;
        if ($view_data['model_info']->client_id) {
            $project_client_id = $view_data['model_info']->client_id;
        }
    
        // Dropdown lists
        $view_data['taxes_dropdown'] = ["" => "-"] + $this->taxesmodel->get_dropdown_list(array("title"));
        $view_data['payment_methods_dropdown'] = $this->paymentmethodsmodel->get_dropdown_list(array("title"), "title", array("online_payable" => 0, "deleted" => 0));
        $view_data['vendors_dropdown'] = ["" => "-"] + $this->vendorsmodel->get_dropdown_list(array("company_name"));
        // Adjust lut_dropdown method call as per your model
        $view_data['lut_dropdown'] = $this->_get_lut_dropdown_select2_data();
    
        $view_data['vendor_id'] = $vendor_id;
    
        $view_data["custom_fields"] = $this->customfieldsmodel->get_combined_details("estimates", $view_data['model_info']->id, $this->session->get('is_admin'), $this->session->get('user_type'))->getResult();
    
        return view('purchase_orders/modal_form', $view_data);
    }

    private function _get_lut_dropdown_select2_data($show_header = false)
{
    $luts = $this->lutnumbermodel->findAll(); // Adjust model method as per CodeIgniter 4's model usage

    $lut_dropdown = [["id" => "", "text" => "-"]];

    foreach ($luts as $code) {
        $lut_dropdown[] = ["id" => $code['lut_number'], "text" => $code['lut_year']]; // Adjust array access based on model data structure
    }

    return $lut_dropdown;
}


    /* add or edit an estimate */
    public function save()
    {
        $this->access_only_allowed_members();
    
        $validation = \Config\Services::validation();
        $validation->setRules([
            "id" => "numeric",
            "purchase_order_vendor_id" => "required|numeric",
            "purchase_order_date" => "required",
            "valid_until" => "required",
            // Add more validation rules as needed
        ]);
    
        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON([
                "success" => false,
                "message" => $validation->getErrors()
            ]);
        }
    
        $vendor_id = $this->request->getPost('purchase_order_vendor_id');
        $id = $this->request->getPost('id');
    
        $purchase_order_data = [
            "vendor_id" => $vendor_id,
            "purchase_order_date" => $this->request->getPost('purchase_order_date'),
            "valid_until" => $this->request->getPost('valid_until'),
            "tax_id" => $this->request->getPost('tax_id') ? $this->request->getPost('tax_id') : 0,
            "tax_id2" => $this->request->getPost('tax_id2') ? $this->request->getPost('tax_id2') : 0,
            "estimate_delivery_address" => $this->request->getPost('estimate_delivery_address') ? 1 : 0,
            "delivery_address_company_name" => $this->request->getPost('delivery_address_company_name'),
            "delivery_note_date" => $this->request->getPost('delivery_note_date'),
            "supplier_ref" => $this->request->getPost('supplier_ref'),
            "other_references" => $this->request->getPost('other_references'),
            //"terms_of_payment" => $this->request->getPost('terms_of_payment'),
            "terms_of_payment" => $this->request->getPost('purchase_order_payment_method_id'),
            "purchase_order_no" => $this->request->getPost('purchase_order_no'),
            "purchase_date" => $this->request->getPost('purchase_date'),
            "destination" => $this->request->getPost('destination'),
            "dispatch_document_no" => $this->request->getPost('dispatch_document_no'),
            "dispatched_through" => $this->request->getPost('dispatched_through'),
            "terms_of_delivery" => $this->request->getPost('terms_of_delivery'),
            "delivery_address" => $this->request->getPost('delivery_address'),
            "delivery_address_state" => $this->request->getPost('delivery_address_state'),
            "delivery_address_city" => $this->request->getPost('delivery_address_city'),
            "delivery_address_country" => $this->request->getPost('delivery_address_country'),
            "delivery_address_zip" => $this->request->getPost('delivery_address_zip'),
            "without_gst" => $this->request->getPost('without_gst') ? 1 : 0,
            "note" => $this->request->getPost('purchase_order_note'),
            "lut_number" => $this->request->getPost('lut_number')
        ];
    
        $purchase_order_id = $this->purchaseordersmodel->save($purchase_order_data, $id);
        if ($purchase_order_id) {
            // Save custom fields if needed
            // save_custom_fields("purchase_order", $purchase_order_id, $this->session->get('is_admin'), $this->session->get('user_type'));
    
            return $this->response->setJSON([
                "success" => true,
                "data" => $this->_row_data($purchase_order_id), // Ensure _row_data method exists or handle data as needed
                'id' => $purchase_order_id,
                'message' => lang('record_saved')
            ]);
        } else {
            return $this->response->setJSON([
                "success" => false,
                "message" => lang('error_occurred')
            ]);
        }
    }

    public function delete()
    {
        $this->access_only_allowed_members();
    
        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'required|numeric'
        ]);
    
        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $validation->getErrors()
            ]);
        }
    
        $id = $this->request->getPost('id');
        if ($this->request->getPost('undo')) {
            if ($this->Purchase_orders_model->deletefreight($id, true)) {
                return $this->response->setJSON([
                    'success' => true,
                    'data' => $this->_row_data($id),
                    'message' => lang('record_undone')
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => lang('error_occurred')
                ]);
            }
        } else {
            if ($this->Purchase_orders_model->deletefreight($id)) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => lang('record_deleted')
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => lang('record_cannot_be_deleted')
                ]);
            }
        }
    }
    

    /* list of estimates, prepared for datatable  */

    public function list_data()
    {
        $this->access_only_allowed_members();
    
        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("estimates", $this->session->get('is_admin'), $this->session->get('user_type'));
    
        $options = [
            "status" => $this->request->getPost("status"),
            "start_date" => $this->request->getPost("start_date"),
            "end_date" => $this->request->getPost("end_date"),
            "custom_fields" => $custom_fields
        ];
    
        $list_data = $this->Purchase_orders_model->get_details($options)->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data, $custom_fields);
        }
    
        return $this->response->setJSON([
            'data' => $result
        ]);
    }
    
    /* list of estimate of a specific client, prepared for datatable  */
    public function purchase_order_list_data_of_vendor($vendor_id)
    {
        $this->access_only_allowed_members_or_vendor_contact($vendor_id);
    
        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("estimates", $this->session->get('is_admin'), $this->session->get('user_type'));
    
        $options = [
            "vendor_id" => $vendor_id,
            "status" => $this->request->getPost("status"),
            "custom_fields" => $custom_fields
        ];
    
        // Don't show draft invoices to client
        if ($this->session->get('user_type') == "vendor") {
            $options["exclude_draft"] = true;
        }
    
        $list_data = $this->Purchase_orders_model->get_details($options)->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data, $custom_fields);
        }
    
        return $this->response->setJSON([
            'data' => $result
        ]);
    }
    
    /* return a row of estimate list table */

    private function _row_data($id)
{
    $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("estimates", $this->session->get('is_admin'), $this->session->get('user_type'));

    $options = [
        "id" => $id,
        "custom_fields" => $custom_fields
    ];

    $data = $this->Purchase_orders_model->get_details($options)->getRow();
    return $this->_make_row($data, $custom_fields);
}


    /* prepare a row of estimate list table */

    private function _make_row($data, $custom_fields)
{
    $purchase_order_url = "";
    if ($this->session->get('user_type') == "staff") {
        $purchase_order_url = anchor('purchase_orders/view/' . $data->id, get_purchase_order_id($data->id));
    } else {
        // For client view
        $purchase_order_url = anchor('purchase_orders/preview/' . $data->id, get_purchase_order_id($data->id));
    }

    $due = 0;
    if ($data->purchase_order_value) {
        $due = ignor_minor_value($data->purchase_order_value - $data->payment_received);
    }

    $row_data = [
        $purchase_order_url,
        anchor('vendors/view/' . $data->vendor_id, $data->company_name),
        $data->purchase_order_date,
        format_to_date($data->purchase_order_date, false),
        $data->valid_until,
        format_to_date($data->valid_until, false),
        to_currency($data->purchase_order_value, $data->currency_symbol),
        to_currency($data->payment_received, $data->currency_symbol),
        to_currency($due, $data->currency_symbol),
        $this->_get_purchase_order_status_label($data)
    ];

    foreach ($custom_fields as $field) {
        $cf_id = 'cfv_' . $field->id;
        $row_data[] = view('custom_fields/output_' . $field->field_type, ['value' => $data->$cf_id]);
    }

    $row_data[] = modal_anchor('purchase_orders/modal_form', '<i class="fa fa-pencil"></i>', [
        'class' => 'edit',
        'title' => lang('edit_estimate'),
        'data-post-id' => $data->id
    ]) . js_anchor('<i class="fa fa-times fa-fw"></i>', [
        'title' => lang('delete_estimate'),
        'class' => 'delete',
        'data-id' => $data->id,
        'data-action-url' => 'purchase_orders/delete',
        'data-action' => 'delete'
    ]);

    return $row_data;
}

    private function _get_purchase_order_status_label($data, $return_html = true) {
        return get_purchase_order_status_label($data, $return_html);
    }
/* load estimate details view */

public function view($purchase_order_id = 0)
{
    $this->access_only_allowed_members();

    if ($purchase_order_id) {
        $view_data = get_purchase_order_making_data($purchase_order_id);

        if ($view_data) {
            $view_data['purchase_order_status_label'] = $this->_get_purchase_order_status_label($view_data["purchase_order_info"]); 
            $view_data['purchase_order_status'] = $this->_get_purchase_order_status_label($view_data["purchase_order_info"], false);

            $access_info = $this->get_access_info("invoice");
            $view_data["show_invoice_option"] = (get_setting("module_invoice") && $access_info->access_type == "all");

            $view_data["can_create_projects"] = $this->can_create_projects();

            return view("purchase_orders/view", $view_data);
        } else {
            throw PageNotFoundException::forPageNotFound();
        }
    } else {
        throw PageNotFoundException::forPageNotFound();
    }
}


    /* estimate total section */

    private function _get_purchase_order_total_view($purchase_order_id = 0)
    {
        $view_data["purchase_order_total_summary"] = $this->Purchase_orders_model->get_purchase_order_total_summary($purchase_order_id);
        return view('purchase_orders/purchase_order_total_section', $view_data);
    }
    
    /* load item modal */

    public function item_modal_form()
    {
        $this->access_only_allowed_members();
    
        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'numeric'
        ]);
    
        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $validation->getErrors()
            ]);
        }
    
        $purchase_order_id = $this->request->getPost('purchase_order_id');
    
        $model_info = $this->Purchase_order_items_model->find($this->request->getPost('id'));
        if (!$purchase_order_id) {
            $purchase_order_id = $model_info->purchase_order_id;
        }
    
        $options = ["id" => $purchase_order_id];
        $data = $this->Purchase_orders_model->get_details($options)->getRow();
        $view_data['country'] = $data->country;
        $view_data['purchase_order_id'] = $purchase_order_id;
    
        return view('purchase_orders/item_modal_form', $view_data);
    }
    
    /* add or edit an estimate item */

    public function save_item()
    {
        $this->access_only_allowed_members();
    
        $rules = [
            'id' => 'numeric',
            'purchase_order_id' => 'required|numeric',
            'purchase_order_item_rate' => 'required',
            'purchase_order_item_quantity' => 'required',
            'purchase_order_item_gst' => 'required',
            'discount_percentage' => 'required',
            'with_gst' => 'required'
        ];
    
        if ($this->request->getPost('with_gst') == "yes") {
            $rules['purchase_order_item_hsn_code'] = 'required';
            $rules['purchase_order_item_hsn_code_description'] = 'required';
        }
    
        $validation = \Config\Services::validation();
        $validation->setRules($rules);
    
        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $validation->getErrors()
            ]);
        }
    
        $purchase_order_id = $this->request->getPost('purchase_order_id');
        $id = $this->request->getPost('id');
        $rate = unformat_currency($this->request->getPost('purchase_order_item_rate'));
        $quantity = unformat_currency($this->request->getPost('purchase_order_item_quantity'));
        $gst = unformat_currency($this->request->getPost('purchase_order_item_gst'));
        $discount_percentage = unformat_currency($this->request->getPost('discount_percentage'));
        $total = $rate * $quantity;
        $discount_amount = $total * $discount_percentage / 100;
        $discount = $total - $discount_amount;
        $tax = $discount * $gst / 100;
        $net_total = $discount + $tax;
    
        $purchase_order_item_data = [
            'purchase_order_id' => $purchase_order_id,
            'title' => $this->request->getPost('purchase_order_item_title'),
            'description' => $this->request->getPost('purchase_order_item_description'),
            'category' => $this->request->getPost('purchase_order_item_category'),
            'make' => $this->request->getPost('purchase_order_item_make'),
            'hsn_code' => $this->request->getPost('purchase_order_item_hsn_code') ?? "-",
            'gst' => $this->request->getPost('purchase_order_item_gst') ?? 0,
            'hsn_description' => $this->request->getPost('purchase_order_item_hsn_code_description') ?? "-",
            'quantity' => $quantity,
            'unit_type' => $this->request->getPost('purchase_order_unit_type'),
            'rate' => $rate,
            'discount_percentage' => $discount_percentage,
            'with_gst' => $this->request->getPost('with_gst'),
            'total' => $discount,
            'tax_amount' => $tax,
            'net_total' => $net_total,
        ];
    
        $purchase_order_item_id = $this->Purchase_order_items_model->save($purchase_order_item_data, $id);
    
        if ($purchase_order_item_id) {
            // Check if the add_new_item flag is on, if so, add the item to library.
            $add_new_item_to_library = $this->request->getPost('add_new_item_to_library');
            if ($add_new_item_to_library) {
                $library_item_data = [
                    'title' => $this->request->getPost('purchase_order_item_title'),
                    'description' => $this->request->getPost('purchase_order_item_description'),
                    'unit_type' => $this->request->getPost('purchase_order_unit_type'),
                    'rate' => $rate,
                    'category' => $this->request->getPost('purchase_order_item_category'),
                    'make' => $this->request->getPost('purchase_order_item_make'),
                    'hsn_code' => $this->request->getPost('purchase_order_item_hsn_code'),
                    'gst' => $this->request->getPost('purchase_order_item_gst'),
                    'hsn_description' => $this->request->getPost('purchase_order_item_hsn_code_description'),
                ];
                $this->Part_no_generation_model->save($library_item_data);
            }
    
            // Another condition for add_new_item_to_librarys.
            $add_new_item_to_librarys = $this->request->getPost('add_new_item_to_librarys');
            if ($add_new_item_to_librarys) {
                $library_item_data = [
                    'hsn_code' => $this->request->getPost('purchase_order_item_hsn_code'),
                    'gst' => $this->request->getPost('purchase_order_item_gst'),
                    'hsn_description' => $this->request->getPost('purchase_order_item_hsn_code_description'),
                ];
                $this->Hsn_sac_code_model->save($library_item_data);
            }
    
            $options = ['id' => $purchase_order_item_id];
            $item_info = $this->Purchase_order_items_model->get_details($options)->getRow();
            return $this->response->setJSON([
                'success' => true,
                'purchase_order_id' => $item_info->purchase_order_id,
                'data' => $this->_make_item_row($item_info),
                'purchase_order_total_view' => $this->_get_purchase_order_total_view($item_info->purchase_order_id),
                'id' => $purchase_order_item_id,
                'message' => lang('record_saved')
            ]);
        } else {
            return $this->response->setJSON([
                'success' => false,
                'message' => lang('error_occurred')
            ]);
        }
    }/* delete or undo an estimate item */

    public function delete_item()
    {
        $this->access_only_allowed_members();
    
        $rules = [
            'id' => 'required|numeric'
        ];
    
        $validation = \Config\Services::validation();
        $validation->setRules($rules);
    
        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $validation->getErrors()
            ]);
        }
    
        $id = $this->request->getPost('id');
        if ($this->request->getPost('undo')) {
            if ($this->Purchase_order_items_model->delete($id, true)) {
                $options = ['id' => $id];
                $item_info = $this->Purchase_order_items_model->get_details($options)->getRow();
                return $this->response->setJSON([
                    'success' => true,
                    'purchase_order_id' => $item_info->purchase_order_id,
                    'data' => $this->_make_item_row($item_info),
                    'purchase_order_total_view' => $this->_get_purchase_order_total_view($item_info->purchase_order_id),
                    'message' => lang('record_undone')
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => lang('error_occurred')
                ]);
            }
        } else {
            if ($this->Purchase_order_items_model->delete($id)) {
                $item_info = $this->Purchase_order_items_model->get_one($id);
                return $this->response->setJSON([
                    'success' => true,
                    'purchase_order_id' => $item_info->purchase_order_id,
                    'purchase_order_total_view' => $this->_get_purchase_order_total_view($item_info->purchase_order_id),
                    'message' => lang('record_deleted')
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => lang('record_cannot_be_deleted')
                ]);
            }
        }
    }
    
    /* list of estimate items, prepared for datatable  */

    public function item_list_data($purchase_order_id = 0)
    {
        $this->access_only_allowed_members();
    
        $list_data = $this->Purchase_order_items_model->get_details(['purchase_order_id' => $purchase_order_id])->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_item_row($data);
        }
        return $this->response->setJSON(['data' => $result]);
    }
    
    /* prepare a row of estimate item list table */

    private function _make_item_row($data)
    {
        $item = "<b>{$data->title}</b>";
        if ($data->description) {
            $item .= "<br /><span>" . nl2br($data->description) . "</span>";
        }
        $type = $data->unit_type ? $data->unit_type : "";
    
        return [
            $item,
            $data->category,
            $data->make,
            $data->hsn_code,
            to_decimal_format($data->quantity) . " " . $type,
            to_currency($data->rate, $data->currency_symbol),
            to_currency($data->total, $data->currency_symbol),
            $data->gst . "%",
            to_currency($data->tax_amount, $data->currency_symbol),
            $data->discount_percentage . "%",
            to_currency($data->net_total),
    
            modal_anchor(get_uri("purchase_orders/item_modal_form"), "<i class='fa fa-pencil'></i>", ['class' => "edit", 'title' => lang('edit_estimate'), 'data-post-id' => $data->id])
            . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete'), 'class' => "delete", 'data-id' => $data->id, 'data-action-url' => route_to("purchase_orders/delete_item"), 'data-action' => "delete"])
        ];
    }

    /* prepare suggestion of estimate item */

    public function get_estimate_item_suggestion()
    {
        $key = $this->request->getVar("q");
        $suggestion = [];
    
        $items = $this->Part_no_generation_model->get_part_no_suggestion($key);
    
        foreach ($items as $item) {
            $suggestion[] = ["id" => $item->title, "text" => $item->title];
        }
    
        $suggestion[] = ["id" => "+", "text" => "+ " . lang("create_new_product_id")];
    
        return $this->response->setJSON($suggestion);
    }
    public function get_estimate_item_info_suggestion()
{
    $item = $this->Part_no_generation_model->get_part_no_info_suggestion($this->request->getPost("item_name"));
    
    if ($item) {
        return $this->response->setJSON(["success" => true, "item_info" => $item]);
    } else {
        return $this->response->setJSON(["success" => false]);
    }
}
 //view html is accessable to client only.
 public function preview($purchase_order_id = 0, $show_close_preview = false)
 {
     $viewData = [];
 
     if ($purchase_order_id) {
         $purchase_order_data = get_purchase_order_making_data($purchase_order_id);
         $this->_check_purchase_order_access_permission($purchase_order_data);
 
         // Get the label of the purchase order
         $purchase_order_info = get_array_value($purchase_order_data, "purchase_order_info");
         $viewData['purchase_order_status_label'] = $this->_get_purchase_order_status_label($purchase_order_info);
 
         // Prepare purchase order preview HTML
         $viewData['purchase_order_preview'] = prepare_purchase_order_pdf($purchase_order_data, "html");
 
         // Show a back button if requested and user is staff
         $viewData['show_close_preview'] = $show_close_preview && $this->login_user->user_type === "staff";
 
         // Get payment methods and PayPal URL
         $viewData['payment_methods'] = $this->Payment_methods_model->get_available_purchase_order_net_banking_payment_methods();
         $paypal = new \App\Libraries\Paypal(); // adjust this if your library namespace is different
         $viewData['paypal_url'] = $paypal->get_paypal_url();
 
         $viewData['purchase_order_id'] = $purchase_order_id;
 
         return view('purchase_orders/purchase_order_preview', $viewData);
     } else {
         throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
     }
 }
 public function download_pdf($purchase_order_id = 0)
{
    if ($purchase_order_id) {
        $purchase_order_data = get_purchase_order_making_data($purchase_order_id);
        $this->_check_purchase_order_access_permission($purchase_order_data);

        if (@ob_get_length()) {
            @ob_clean();
        }

        // Prepare purchase order PDF for download
        prepare_purchase_order_pdf($purchase_order_data, "download");
    } else {
        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }
}
public function download_purchase_order_without_gst_pdf($purchase_order_id = 0)
{
    if ($purchase_order_id) {
        $purchase_order_data = get_purchase_order_making_data($purchase_order_id);
        $this->_check_purchase_order_access_permission($purchase_order_data);

        if (@ob_get_length()) {
            @ob_clean();
        }

        // Prepare purchase order without GST PDF for download
        prepare_purchase_order_without_gst_pdf($purchase_order_data, "download");
    } else {
        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }
}
private function _check_purchase_order_access_permission($purchase_order_data)
{
    // Check for valid purchase order data
    if (!$purchase_order_data) {
        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }

    // Check for security
    $purchase_order_info = get_array_value($purchase_order_data, "purchase_order_info");
    
    if ($this->login_user->user_type == "vendor") {
        if ($this->login_user->vendor_id != $purchase_order_info->vendor_id) {
            return redirect()->to(site_url("forbidden"));
        }
    } else {
        $this->access_only_allowed_members();
    }
}

public function get_purchase_order_status_bar($purchase_order_id = 0)
{
    $this->access_only_allowed_members();

    $viewData["purchase_order_info"] = $this->Purchase_orders_model->get_details(["id" => $purchase_order_id])->getRow();
    $viewData['purchase_order_status_label'] = $this->_get_purchase_order_status_label($viewData["purchase_order_info"]);

    echo view('purchase_orders/purchase_order_status_bar', $viewData);
}
public function set_purchase_order_status_to_not_paid($purchase_order_id = 0)
{
    $this->access_only_allowed_members();

    if ($purchase_order_id) {
        // Change purchase order status to not paid
        $this->Purchase_orders_model->set_purchase_order_status_to_not_paid($purchase_order_id);
    }
    return "";
}

public function freight_modal_form()
{
    $this->access_only_allowed_members();

    $validationRules = [
        'purchase_order_id' => 'required|numeric'
    ];

    if ($this->validate($validationRules)) {
        $purchase_order_id = $this->request->getPost('purchase_order_id');
        $viewData['model_info'] = $this->Purchase_orders_model->get_one($purchase_order_id);

        echo view('purchase_orders/freight_modal_form', $viewData);
    } else {
        echo json_encode(['success' => false, 'message' => lang('error_occurred')]);
    }
}
public function save_freight()
{
    $this->access_only_allowed_members();

    $validationRules = [
        'purchase_order_id' => 'required|numeric',
        'freight_amount' => 'numeric'
    ];

    if ($this->validate($validationRules)) {
        $purchase_order_id = $this->request->getPost('purchase_order_id');

        $data = [
            'freight_amount' => $this->request->getPost('freight_amount'),
            'hsn_code' => $this->request->getPost('hsn_code'),
            'hsn_description' => $this->request->getPost('hsn_description'),
            'gst' => $this->request->getPost('gst')
        ];

        $data = clean_data($data); // Assuming clean_data is a valid function or method

        $save_data = $this->Purchase_orders_model->save($data, $purchase_order_id);
        if ($save_data) {
            $add_new_item_to_library = $this->request->getPost('add_new_item_to_library');
            if ($add_new_item_to_library) {
                $library_item_data = [
                    'hsn_code' => $data['hsn_code'],
                    'gst' => $data['gst'],
                    'hsn_description' => $data['hsn_description']
                ];
                $this->Hsn_sac_code_model->save($library_item_data);
            }

            echo json_encode([
                'success' => true,
                'purchase_order_total_view' => $this->_get_purchase_order_total_view($purchase_order_id),
                'message' => lang('record_saved'),
                'purchase_order_id' => $purchase_order_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => lang('error_occurred')]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => $this->validator->getErrors()]);
    }
}
public function get_invoice_freight_suggestion()
{
    $key = $this->request->getVar('q');
    $suggestion = [];

    $items = $this->Hsn_sac_code_model->get_freight_suggestion($key);

    foreach ($items as $item) {
        $suggestion[] = ["id" => $item->hsn_code, "text" => $item->hsn_code];
    }

    $suggestion[] = ["id" => "+", "text" => "+ " . lang("create_new_hsn_code")];

    echo json_encode($suggestion);
}

public function get_invoice_freight_info_suggestion()
{
    $item = $this->Hsn_sac_code_model->get_item_freight_suggestion($this->request->getPost("item_name"));
    if ($item) {
        echo json_encode(["success" => true, "item_info" => $item]);
    } else {
        echo json_encode(["success" => false]);
    }
}

public function get_vendor_country_item_info_suggestion()
{
    $item = $this->Vendors_model->get_vendor_country_info_suggestion($this->request->getPost("item_name"));
    if ($item) {
        echo json_encode(["success" => true, "item_info" => $item]);
    } else {
        echo json_encode(["success" => false]);
    }
}}

/* End of file estimates.php */
/* Location: ./application/controllers/estimates.php */