<?php

namespace App\Controllers;

use App\Models\CustomFieldsModel;
use App\Models\VendorsModel;
use App\Models\WorkOrdersModel;
use App\Models\TaxesModel;
use App\Models\PaymentMethodsModel;
use App\Models\ModeOfDispatchModel;
use App\Models\LutNumberModel;

class Work_orders extends BaseController
{
    protected $customfieldsmodel;
    protected $vendorsmodel;
    protected $workordersmodel;
    protected $taxesmodel;
    protected $paymentmethodsmodel;
    protected $lutnumbermodel;

    public function __construct()
    {
        $this->customfieldsmodel = new CustomFieldsModel();
        $this->vendorsmodel = new VendorsModel();
        $this->workordersmodel = new WorkOrdersModel();
        $this->taxesmodel = new TaxesModel();
        $this->paymentmethodsmodel = new PaymentMethodsModel();
        $this->lutnumbermodel = new LutNumberModel();

        $this->init_permission_checker("work_order");
    }

    /* load estimate list view */
    public function index()
    {
        $this->check_module_availability("module_work_order");

        $view_data["custom_field_headers"] = $this->customfieldsmodel->get_custom_field_headers_for_table("estimates", $this->login_user->is_admin, $this->login_user->user_type);

        if ($this->login_user->user_type === "staff") {
            $this->access_only_allowed_members();
            return view("work_orders/index", $view_data);
        } else {
            //client view
            $view_data["vendor_info"] = $this->vendorsmodel->find($this->login_user->vendor_id);
            $view_data['vendor_id'] = $this->login_user->vendor_id;
            $view_data['page_type'] = "full";
            return view("vendors/work_orders/vendor_portal", $view_data);
        }
    }

    //load the yearly view of estimate list
    public function yearly()
    {
        return view("work_orders/yearly_work_orders");
    }

    /* load new estimate modal */

    public function modal_form()
    {
        $this->access_only_allowed_members();

        $validation = \Config\Services::validation();
        $validation->setRules([
            "id" => "numeric",
            "vendor_id" => "numeric"
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return redirect()->back()->withInput()->with('errors', $validation->getErrors());
        }

        $vendor_id = $this->request->getPost('vendor_id');
        $view_data['model_info'] = $this->workordersmodel->find($this->request->getPost('id'));

        $view_data['taxes_dropdown'] = ["" => "-"] + $this->taxesmodel->get_dropdown_list(["title"]);
        $view_data['payment_methods_dropdown'] = $this->paymentmethodsmodel->get_dropdown_list(["title"], "title", ["online_payable" => 0, "deleted" => 0]);
        $view_data['dispatched_through_dropdown'] = ["" => "-"] + $this->ModeOfDispatchModel->get_dropdown_list(["title"], "id", ["status" => "active"]);
        $view_data['vendors_dropdown'] = ["" => "-"] + $this->vendorsmodel->get_dropdown_list(["company_name"]);

        $view_data['vendor_id'] = $vendor_id;
        $view_data['lut_dropdown'] = $this->_get_lut_dropdown_select2_data();
        $view_data["custom_fields"] = $this->customfieldsmodel->get_combined_details("estimates", $view_data['model_info']->id, $this->login_user->is_admin, $this->login_user->user_type)->getResult();

        return view('work_orders/modal_form', $view_data);
    }

    private function _get_lut_dropdown_select2_data($show_header = false)
    {
        $luts = $this->lutnumbermodel->where(["deleted" => 0, "status" => "active"])->findAll();
        $lut_dropdown = [["id" => "", "text" => "-"]];

        foreach ($luts as $lut) {
            $lut_dropdown[] = ["id" => $lut->lut_number, "text" => $lut->lut_year];
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
            "work_order_vendor_id" => "required|numeric",
            "work_order_date" => "required",
            "valid_until" => "required"
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return redirect()->back()->withInput()->with('errors', $validation->getErrors());
        }

        $vendor_id = $this->request->getPost('work_order_vendor_id');
        $id = $this->request->getPost('id');

        $work_order_data = [
            "vendor_id" => $vendor_id,
            "work_order_date" => $this->request->getPost('work_order_date'),
            "valid_until" => $this->request->getPost('valid_until'),
            "tax_id" => $this->request->getPost('tax_id') ? $this->request->getPost('tax_id') : 0,
            "tax_id2" => $this->request->getPost('tax_id2') ? $this->request->getPost('tax_id2') : 0,
            "estimate_delivery_address" => $this->request->getPost('estimate_delivery_address') ? 1 : 0,
            "delivery_address_company_name" => $this->request->getPost('delivery_address_company_name'),
            "delivery_note_date" => $this->request->getPost('delivery_note_date'),
            "supplier_ref" => $this->request->getPost('supplier_ref'),
            "other_references" => $this->request->getPost('other_references'),
            "terms_of_payment" => $this->request->getPost('work_order_payment_method_id'),
            "work_order_no" => $this->request->getPost('work_order_no'),
            "work_date" => $this->request->getPost('work_date'),
            "destination" => $this->request->getPost('destination'),
            "dispatch_document_no" => $this->request->getPost('dispatch_document_no'),
            "dispatched_through" => $this->request->getPost('dispatched_through'),
            "terms_of_delivery" => $this->request->getPost('terms_of_delivery'),
            "delivery_address" => $this->request->getPost('delivery_address'),
            "delivery_address_state" => $this->request->getPost('delivery_address_state'),
            "delivery_address_city" => $this->request->getPost('delivery_address_city'),
            "delivery_address_country" => $this->request->getPost('delivery_address_country'),
            "delivery_address_zip" => $this->request->getPost('delivery_address_zip'),
            "delivery_address_phone" => $this->request->getPost('delivery_address_phone'),
            "without_gst" => $this->request->getPost('without_gst') ? 1 : 0,
            "note" => $this->request->getPost('work_order_note'),
            "lut_number" => $this->request->getPost('lut_number')
        ];

        if ($id) {
            // check if work_order_no already exists for update
            if ($this->workordersmodel->is_work_order_no_exists($work_order_data["work_order_no"], $id)) {
                return json_encode(["success" => false, 'message' => lang('wo_no_already')]);
            }
        } else {
            // create new work_order_no and check if it already exists
            $get_last_work_order_id = $this->workordersmodel->get_last_work_order_id_exists();
            $work_order_no_last_id = ($get_last_work_order_id->id + 1);
            $work_order_prefix = get_work_order_id($work_order_no_last_id);

            if ($this->workordersmodel->is_work_order_no_exists($work_order_prefix)) {
                return json_encode(["success" => false, 'message' => $work_order_prefix . " " . lang('po_no_already')]);
            }
        }

        $work_order_id = $this->workordersmodel->save($work_order_data, $id);
        if ($work_order_id) {
            // Save the new work_order_no
            if (!$id) {
                $work_order_prefix = get_work_order_id($work_order_id);
                $work_order_prefix_data = [
                    "work_no" => $work_order_prefix
                ];
                $this->workordersmodel->save($work_order_prefix_data, $work_order_id);
            }

            save_custom_fields("work_order", $work_order_id, $this->login_user->is_admin, $this->login_user->user_type);

            return json_encode(["success" => true, "data" => $this->_row_data($work_order_id), 'id' => $work_order_id, 'message' => lang('record_saved')]);
        } else {
            return json_encode(["success" => false, 'message' => lang('error_occurred')]);
        }
    }

    public function delete()
    {
        $this->access_only_allowed_members();

        $validation = \Config\Services::validation();
        $validation->setRules([
            "id" => "required|numeric"
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return json_encode(["success" => false, 'message' => lang('error_occurred')]);
        }

        $id = $this->request->getPost('id');
        if ($this->request->getPost('undo')) {
            if ($this->workordersmodel->deletefreight($id, true)) {
                return json_encode(["success" => true, "data" => $this->_row_data($id), "message" => lang('record_undone')]);
            } else {
                return json_encode(["success" => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($this->workordersmodel->deletefreight($id)) {
                return json_encode(["success" => true, 'message' => lang('record_deleted')]);
            } else {
                return json_encode(["success" => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }
    public function list_data()
    {
        $this->access_only_allowed_members();

        $custom_fields = $this->customfieldsmodel->get_available_fields_for_table("estimates", $this->login_user->is_admin, $this->login_user->user_type);

        $options = [
            "status" => $this->request->getPost("status"),
            "start_date" => $this->request->getPost("start_date"),
            "end_date" => $this->request->getPost("end_date"),
            "custom_fields" => $custom_fields
        ];

        $list_data = $this->workordersmodel->get_details($options)->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data, $custom_fields);
        }

        return $this->response->setJSON(["data" => $result]);
    }

    public function work_order_list_data_of_vendor($vendor_id)
    {
        $this->access_only_allowed_members_or_vendor_contact($vendor_id);

        $custom_fields = $this->customfieldsmodel->get_available_fields_for_table("estimates", $this->login_user->is_admin, $this->login_user->user_type);

        $options = [
            "vendor_id" => $vendor_id,
            "status" => $this->request->getPost("status"),
            "custom_fields" => $custom_fields
        ];

        // Exclude draft invoices for vendors
        if ($this->login_user->user_type == "vendor") {
            $options["exclude_draft"] = true;
        }

        $list_data = $this->workordersmodel->get_details($options)->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data, $custom_fields);
        }

        return $this->response->setJSON(["data" => $result]);
    }

    private function _row_data($id)
    {
        $custom_fields = $this->customfieldsmodel->get_available_fields_for_table("estimates", $this->login_user->is_admin, $this->login_user->user_type);

        $options = [
            "id" => $id,
            "custom_fields" => $custom_fields
        ];

        $data = $this->workordersmodel->get_details($options)->getRow();
        if ($data) {
            return $this->_make_row($data, $custom_fields);
        }
        return null;
    }

    private function _make_row($data, $custom_fields)
    {
        // Implement your logic here to format each row of data as needed
        // Example:
        $formatted_data = [
            "id" => $data->id,
            "field1" => $data->field1,
            "field2" => $data->field2,
            // Add more fields as needed
        ];

        return $formatted_data;
    }
    /* prepare a row of estimate list table */

    private function _make_row($data, $custom_fields)
    {
        $work_order_no_value = $data->work_no ? $data->work_no : get_work_order_id($data->id);
        $work_order_no_url = "";
        if ($this->login_user->user_type == "staff") {
            $work_order_no_url = anchor(base_url("work_orders/view/" . $data->id), $work_order_no_value);
        } else {
            $work_order_no_url = anchor(base_url("work_orders/preview/" . $data->id), $work_order_no_value);
        }

        $due = 0;
        if ($data->work_order_value) {
            $due = ignor_minor_value($data->work_order_value - $data->payment_received);
        }

        $row_data = [
            $data->id,
            $work_order_no_url,
            anchor(base_url("vendors/view/" . $data->vendor_id), $data->company_name),
            $data->work_order_date,
            format_to_date($data->work_order_date, false),
            $data->valid_until,
            format_to_date($data->valid_until, false),
            to_currency($data->work_order_value, $data->currency_symbol),
            to_currency($data->payment_received, $data->currency_symbol),
            to_currency($due, $data->currency_symbol),
            $this->_get_work_order_status_label($data),
        ];

        foreach ($custom_fields as $field) {
            $cf_id = "cfv_" . $field->id;
            $row_data[] = view("custom_fields/output_" . $field->field_type, ["value" => $data->$cf_id]);
        }

        $row_data[] = modal_anchor(base_url("work_orders/modal_form"), "<i class='fa fa-pencil'></i>", ["class" => "edit", "title" => lang('edit'), "data-post-id" => $data->id])
            . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_estimate'), "class" => "delete", "data-id" => $data->id, "data-action-url" => base_url("work_orders/delete"), "data-action" => "delete-confirmation"]);

        return $row_data;
    }
    private function _get_work_order_status_label($data, $return_html = true)
    {
        return get_work_order_status_label($data, $return_html);
    }

    /* load estimate details view */

    
    public function view($work_order_id = 0)
    {
        $this->access_only_allowed_members();

        if ($work_order_id) {
            $view_data = get_work_order_making_data($work_order_id);

            if ($view_data) {
                $view_data['work_order_status_label'] = $this->_get_work_order_status_label($view_data["work_order_info"]);
                $view_data['work_order_status'] = $this->_get_work_order_status_label($view_data["work_order_info"], false);

                $access_info = $this->get_access_info("invoice");
                $view_data["show_invoice_option"] = (get_setting("module_invoice") && $access_info->access_type == "all") ? true : false;

                $view_data["can_create_projects"] = $this->can_create_projects();

                return view("work_orders/view", $view_data);
            } else {
                return view('errors/html/error_404');
            }
        } else {
            return view('errors/html/error_404');
        }
    }


    /* estimate total section */
    private function _getWorkOrderTotalView($workOrderId = 0)
    {
        $viewData["workOrderTotalSummary"] = $this->workOrdersModel->getWorkOrderTotalSummary($workOrderId);
        return view('work_orders/work_order_total_section', $viewData);
    }

    /* load item modal */

    public function itemModalForm()
    {
        $this->accessOnlyAllowedMembers();

        $validation = \Config\Services::validation();

        $validation->setRules([
            'id' => 'numeric',
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $workOrderId = $this->request->getPost('work_order_id');

        $viewData['modelInfo'] = $this->workOrderItemsModel->getOne($this->request->getPost('id'));
        if (!$workOrderId) {
            $workOrderId = $viewData['modelInfo']->work_order_id;
        }

        $options = ['id' => $workOrderId];
        $data = $this->workOrdersModel->getDetails($options)->getRow();
        $viewData['country'] = $data->country;
        $viewData['buyerType'] = $data->buyer_type;
        $viewData["unitTypeDropdown"] = $this->_getUnitTypeDropdownSelect2Data();
        $viewData['workOrderId'] = $workOrderId;

        return view('work_orders/item_modal_form', $viewData);
    }

    private function _getUnitTypeDropdownSelect2Data()
    {
        //$unitTypes = $this->unitTypeModel->findAll(); // Adjust method name as per your actual method
        $unitTypes = $this->unitTypeModel->where(['deleted' => 0, 'status' => 'active'])->findAll(); // Adjust method and condition as per your actual method
        $unitTypeDropdown = [];

        foreach ($unitTypes as $type) {
            $unitTypeDropdown[] = ['id' => $type->title, 'text' => $type->title];
        }

        return $unitTypeDropdown;
    }
/* add or edit an estimate item */

public function saveItem()
{
    $this->accessOnlyAllowedMembers();

    $validation = \Config\Services::validation();
    $validation->setRules([
        'id' => 'numeric',
        'work_order_id' => 'required|numeric',
    ]);

    if (!$validation->withRequest($this->request)->run()) {
        return $this->failValidationErrors($validation->getErrors());
    }

    $workOrderId = $this->request->getPost('work_order_id');
    $id = $this->request->getPost('id');
    $rate = unformat_currency($this->request->getPost('work_order_item_rate'));
    $quantity = unformat_currency($this->request->getPost('work_order_item_quantity'));
    $gst = unformat_currency($this->request->getPost('work_order_item_gst'));
    $discountPercentage = unformat_currency($this->request->getPost('discount_percentage'));

    $total = $rate * $quantity;
    $discountAmount = $total * $discountPercentage / 100;
    $discountedTotal = $total - $discountAmount;
    $taxAmount = $discountedTotal * $gst / 100;
    $netTotal = $discountedTotal + $taxAmount;

    $withGst = $this->request->getPost('with_gst');
    if ($withGst == "yes") {
        $workOrderItemData = [
            'work_order_id' => $workOrderId,
            'title' => $this->request->getPost('work_order_item_title'),
            'description' => $this->request->getPost('work_order_item_description'),
            'category' => $this->request->getPost('work_order_item_category'),
            'hsn_code' => $this->request->getPost('work_order_item_hsn_code'),
            'gst' => $this->request->getPost('work_order_item_gst'),
            'hsn_description' => $this->request->getPost('work_order_item_hsn_code_description'),
            'quantity' => $quantity,
            'unit_type' => $this->request->getPost('work_order_unit_type'),
            'rate' => unformat_currency($this->request->getPost('work_order_item_rate')),
            'with_gst' => $withGst,
            'discount_percentage' => $discountPercentage,
            'total' => $discountedTotal,
            'tax_amount' => $taxAmount,
            'net_total' => $netTotal,
            'quantity_total' => $total,
        ];
    } else {
        $workOrderItemData = [
            'work_order_id' => $workOrderId,
            'title' => $this->request->getPost('work_order_item_title'),
            'description' => $this->request->getPost('work_order_item_description'),
            'category' => $this->request->getPost('work_order_item_category'),
            'hsn_code' => "-",
            'gst' => 0,
            'hsn_description' => "-",
            'quantity' => $quantity,
            'unit_type' => $this->request->getPost('work_order_unit_type'),
            'rate' => unformat_currency($this->request->getPost('work_order_item_rate')),
            'with_gst' => $withGst,
            'discount_percentage' => $discountPercentage,
            'total' => $discountedTotal,
            'tax_amount' => 0,
            'net_total' => $discountedTotal,
            'quantity_total' => $total,
        ];
    }

    // Check for duplicate product
    if (!$id) {
        // Check if the work order product exists
        $workOrderItemData['title'] = $this->request->getPost('work_order_item_title');
        if ($this->workOrderItemsModel->isWoProductExists($workOrderItemData['title'], $workOrderId)) {
            return $this->fail(lang('job_id_already'));
        }
    }

    if ($id) {
        // Check if the work order product exists for update
        $workOrderItemData['title'] = $this->request->getPost('work_order_item_title');
        $workOrderItemData['id'] = $id;
        if ($this->workOrderItemsModel->isWoProductExists($workOrderItemData['title'], $workOrderId, $id)) {
            return $this->fail(lang('job_id_already'));
        }
    }

    // Save the work order item data
    $workOrderItemId = $this->workOrderItemsModel->save($workOrderItemData, $id);

    if ($workOrderItemId) {
        // Check if add_new_item_to_library flag is set, add item to library if true
        $addNewItemToLibrary = $this->request->getPost('add_new_item_to_library');
        if ($addNewItemToLibrary) {
            $libraryItemData = [
                'title' => $this->request->getPost('work_order_item_title'),
                'description' => $this->request->getPost('work_order_item_description'),
                'unit_type' => $this->request->getPost('work_order_unit_type'),
                'rate' => unformat_currency($this->request->getPost('work_order_item_rate')),
                'category' => $this->request->getPost('work_order_item_category'),
                'hsn_code' => $this->request->getPost('work_order_item_hsn_code'),
                'gst' => $this->request->getPost('work_order_item_gst'),
                'hsn_description' => $this->request->getPost('work_order_item_hsn_code_description'),
            ];

            if (!$this->outsourceJobsModel->isOutsourceJobExists($libraryItemData['title'])) {
                $this->outsourceJobsModel->save($libraryItemData);
            }
        }

        // Check if add_new_item_to_librarys flag is set, add HSN/SAC code to library if true
        $addNewItemToLibrarys = $this->request->getPost('add_new_item_to_librarys');
        if ($addNewItemToLibrarys) {
            $libraryItemData = [
                'hsn_code' => $this->request->getPost('work_order_item_hsn_code'),
                'gst' => $this->request->getPost('work_order_item_gst'),
                'hsn_description' => $this->request->getPost('work_order_item_hsn_code_description'),
            ];

            if (!$this->hsnSacCodeModel->isHsnCodeExists($libraryItemData['hsn_code'])) {
                $this->hsnSacCodeModel->save($libraryItemData);
            }
        }

        // Fetch item info and return response
        $options = ['id' => $workOrderItemId];
        $itemInfo = $this->workOrderItemsModel->getDetails($options)->getRow();
        $workOrderTotalView = $this->_getWorkOrderTotalView($itemInfo->work_order_id);

        return $this->respondCreated([
            'success' => true,
            'work_order_id' => $itemInfo->work_order_id,
            'data' => $this->_makeItemRow($itemInfo),
            'work_order_total_view' => $workOrderTotalView,
            'id' => $workOrderItemId,
            'message' => lang('record_saved'),
        ]);
    } else {
        return $this->failServerError(lang('error_occurred'));
    }
}
    /* delete or undo an estimate item */

    public function deleteItem()
    {
        $this->accessOnlyAllowedMembers();

        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'required|numeric',
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $id = $this->request->getPost('id');
        $undo = $this->request->getPost('undo');

        if ($undo) {
            if ($this->workOrderItemsModel->delete($id, true)) {
                $options = ['id' => $id];
                $itemInfo = $this->workOrderItemsModel->getDetails($options)->getRow();
                return $this->respond([
                    'success' => true,
                    'work_order_id' => $itemInfo->work_order_id,
                    'data' => $this->_makeItemRow($itemInfo),
                    'work_order_total_view' => $this->_getWorkOrderTotalView($itemInfo->work_order_id),
                    'message' => lang('record_undone'),
                ]);
            } else {
                return $this->fail(lang('error_occurred'));
            }
        } else {
            if ($this->workOrderItemsModel->delete($id)) {
                $itemInfo = $this->workOrderItemsModel->getOne($id);
                return $this->respond([
                    'success' => true,
                    'work_order_id' => $itemInfo->work_order_id,
                    'work_order_total_view' => $this->_getWorkOrderTotalView($itemInfo->work_order_id),
                    'message' => lang('record_deleted'),
                ]);
            } else {
                return $this->fail(lang('record_cannot_be_deleted'));

    /* list of estimate items, prepared for datatable  */
    public function itemListData($workOrderId = 0)
    {
        $this->accessOnlyAllowedMembers();

        $listData = $this->workOrderItemsModel->where('work_order_id', $workOrderId)->findAll();
        $result = [];

        foreach ($listData as $data) {
            $result[] = $this->_makeItemRow($data);
        }

        return $this->response->setJSON(['data' => $result]);
    }
    private function _makeItemRow($data)
    {
        $item = "<b>$data->title</b>";
        if ($data->description) {
            $item .= "<br /><span>" . nl2br($data->description) . "</span>";
        }
        $type = $data->unit_type ? $data->unit_type : "";
    
        return [
            $item,
            $data->category,
            $data->hsn_code,
            $data->gst . "%",
            to_decimal_format($data->quantity) . " " . $type,
            to_currency($data->rate, $data->currency_symbol),
            to_currency($data->quantity_total, $data->currency_symbol),
            to_currency($data->tax_amount, $data->currency_symbol),
            $data->discount_percentage . "%",
            to_currency($data->total, $data->currency_symbol),
    
            modal_anchor(
                route_to('work_orders/item_modal_form'),
                "<i class='fa fa-pencil'></i>",
                [
                    'class' => 'edit',
                    'title' => lang('edit'),
                    'data-post-id' => $data->id
                ]
            )
            . js_anchor(
                "<i class='fa fa-times fa-fw'></i>",
                [
                    'title' => lang('delete'),
                    'class' => 'delete',
                    'data-id' => $data->id,
                    'data-action-url' => route_to('work_orders/delete_item'),
                    'data-action' => 'delete-confirmation'
                ]
            )
        ];
    }
}    
    /* prepare suggestion of estimate item */

     function get_estimate_item_suggestion() {
        $key = $_REQUEST["q"];
        $suggestion = array();
        $options = array("work_order_id" => $_REQUEST["s"] );
$list_data = $this->Work_order_items_model->get_details($options)->result();
if($list_data){
        $work_order_items = array();
foreach ($list_data as $code) {
            $work_order_items[] = $code->title;
        }
$aa=json_encode($work_order_items);
$vv=str_ireplace("[","(",$aa);
$d_item=str_ireplace("]",")",$vv);
       
}else{
    $d_item="('empty')";
}

        $items = $this->Work_order_items_model->get_item_suggestion($key,$d_item);

        foreach ($items as $item) {
            $suggestion[] = array("id" => $item->title, "text" => $item->title);
        }

        $suggestion[] = array("id" => "+", "text" => "+ " . lang("create_new_outsource_job"));

        echo json_encode($suggestion);
    }
    function get_estimate_item_info_suggestion() {
        $item = $this->Work_order_items_model->get_item_info_suggestion($this->input->post("item_name"));

        $itemss =  $this->Work_order_items_model->get_item_suggestionss($this->input->post("s"));
   
   $default_curr =get_setting("default_currency");
    $default_country=get_setting("company_country");
if (empty($itemss->currency))
 {
    $itemss->currency = $default_curr;
 }             //print_r($itemss->currency) ;

$currency= get_setting("default_currency")."_".$itemss->currency;
if($itemss->country !== $default_country){

     $connected = @fsockopen("www.google.com", 80);
if ($connected){
        $currency_rate = file_get_contents("https://free.currconv.com/api/v7/convert?q=$currency&compact=ultra&apiKey=7bf2a122b1e76ac358b8");
       $cur_val = json_decode($currency_rate);
    $response_value   =   $cur_val->$currency;
    }else{
        $response_value   =   'failed';
    }
}else if($itemss->country == $default_country){
              
$response_value   =  "same_country";
     
} 
        if ($item) {
            echo json_encode(array("success" => true,"item_infos" => $response_value, "item_info" => $item));
        } else {
            echo json_encode(array("success" => false));
        }
    }

    //view html is accessable to client only.
     function preview($work_order_id = 0, $show_close_preview = false) {

        $view_data = array();

        if ($work_order_id) {

            $work_order_data = get_work_order_making_data($work_order_id);
            $this->_check_work_order_access_permission($work_order_data);

            //get the label of the estimate
            $work_order_info = get_array_value($work_order_data, "work_order_info");
            $work_order_data['work_order_status_label'] = $this->_get_work_order_status_label($work_order_info);

            $view_data['work_order_preview'] = prepare_work_order_pdf($work_order_data, "html");

            //show a back button
            $view_data['show_close_preview'] = $show_close_preview && $this->login_user->user_type === "staff" ? true : false;
            $view_data['payment_methods'] = $this->Payment_methods_model->get_available_work_order_net_banking_payment_methods();


            $view_data['work_order_id'] = $work_order_id;

            $this->template->rander("work_orders/work_order_preview", $view_data);
        } else {
            show_404();
        }
    }

    function download_pdf($work_order_id = 0) {
        if ($work_order_id) {
            $work_order_data = get_work_order_making_data($work_order_id);
            $this->_check_work_order_access_permission($work_order_data);

            if (@ob_get_length())
                @ob_clean();
            //so, we have a valid estimate data. Prepare the view.

            prepare_work_order_pdf($work_order_data, "download");
        } else {
            show_404();
        }
    }


function download_work_order_without_gst_pdf($work_order_id = 0) {
        if ($work_order_id) {
            $work_order_data = get_work_order_making_data($work_order_id);
            $this->_check_work_order_access_permission($work_order_data);

            if (@ob_get_length())
                @ob_clean();
            //so, we have a valid estimate data. Prepare the view.

            prepare_work_order_without_gst_pdf($work_order_data, "download");
        } else {
            show_404();
        }
}

    private function _check_work_order_access_permission($work_order_data) {
        //check for valid estimate
        if (!$work_order_data) {
            show_404();
        }

        //check for security
        $work_order_info = get_array_value($work_order_data, "work_order_info");
        if ($this->login_user->user_type == "vendor") {

           
            if ($this->login_user->vendor_id != $work_order_info->vendor_id) {
                redirect("forbidden");
            }
        } else {
            $this->access_only_allowed_members();
        }
    }

    function get_work_order_status_bar($work_order_id = 0) {
        $this->access_only_allowed_members();

        $view_data["work_order_info"] = $this->Work_orders_model->get_details(array("id" => $work_order_id))->row();
        $view_data['work_order_status_label'] = $this->_get_work_order_status_label($view_data["work_order_info"]);
        $this->load->view('work_orders/work_order_status_bar', $view_data);
    }

    function set_work_order_status_to_not_paid($work_order_id = 0) {
        $this->access_only_allowed_members();

        if ($work_order_id) {
            //change the draft status of the invoice
            $this->Work_orders_model->set_work_order_status_to_not_paid($work_order_id);
        }
        return "";
    }


    function freight_modal_form() {
        $this->access_only_allowed_members();

      validate_submitted_data(array(
          "work_order_id" => "required|numeric"
        )); 

       $work_order_id = $this->input->post('work_order_id');

       $view_data['model_info'] = $this->Work_orders_model->get_one($work_order_id);
       $optionss = array("id" => $work_order_id);
        $datas = $this->Work_orders_model->get_details($optionss)->row();
        $view_data['country'] = $datas->country;

    $this->load->view('work_orders/freight_modal_form', $view_data);
    }

    function save_freight() {
        $this->access_only_allowed_members();

        validate_submitted_data(array(
            "work_order_id" => "required|numeric",
           
            "freight_amount" => "numeric"
            
        ));

        $work_order_id = $this->input->post('work_order_id');

        $ss = $this->input->post('with_gst');
$with_inclusive= $this->input->post('with_inclusive_tax');
if($ss=="yes" && $with_inclusive=="yes"){
    $amount = unformat_currency($this->input->post('amount'));
  $gst = $this->input->post('gst');
  $tax = $amount/(100+$gst);
  $tax_orignal=$tax*100;
  $tax_value = $amount-$tax_orignal;
  //$tax_cgst_sgst = $tax_value/2;
        $data = array(
           
            "amount" => $tax_orignal,
            "hsn_code" => $this->input->post('hsn_code'),
             "hsn_description" => $this->input->post('hsn_description'),
            "gst" => $this->input->post('gst'),
            "with_inclusive_tax" => $this->input->post('with_inclusive_tax'),
            "with_gst" => $this->input->post('with_gst'),
            "freight_tax_amount" => $tax_value,
            "freight_amount" => $amount, 
            
        );
    }else if($ss=="yes" && $with_inclusive=="no"){
        $amount = unformat_currency($this->input->post('amount'));
  $gst = $this->input->post('gst')/100;
  $tax = $amount* $gst;
  
  $total =$amount+$tax;
  //$tax_cgst_sgst = $tax_value/2;
        $data = array(
           
            "amount" => $amount,
            "hsn_code" => $this->input->post('hsn_code'),
             "hsn_description" => $this->input->post('hsn_description'),
            "gst" => $this->input->post('gst'),
            "with_inclusive_tax" => $this->input->post('with_inclusive_tax'),
            "with_gst" => $this->input->post('with_gst'),
            "freight_tax_amount" => $tax,
            "freight_amount" => $total, 
            
        );
    }else {
        $amount = unformat_currency($this->input->post('amount'));
        $data = array(
           
            "amount" => $amount,
            "hsn_code" => "-",
             "hsn_description" =>"-" ,
            "gst" => 0,
            "with_inclusive_tax" => $this->input->post('with_inclusive_tax'),
            "with_gst" => $this->input->post('with_gst'),
            "freight_tax_amount" => 0,
            "freight_amount" => $amount, 
            
        );
    }

        $data = clean_data($data);

        $save_data = $this->Work_orders_model->save($data, $work_order_id);
        if ($save_data) {

            $add_new_item_to_library = $this->input->post('add_new_item_to_library');
            if ($add_new_item_to_library) {
                $library_item_data = array(
                    "hsn_code" => $this->input->post('hsn_code'),
                    "gst" => $this->input->post('gst'),
                    "hsn_description" => $this->input->post('hsn_description')
                    
                );
                $this->Hsn_sac_code_model->save($library_item_data);
            }
            echo json_encode(array("success" => true, "work_order_total_view" => $this->_get_work_order_total_view($work_order_id), 'message' => lang('record_saved'), "work_order_id" => $work_order_id));
        } else {
            echo json_encode(array("success" => false, 'message' => lang('error_occurred')));
        }
    }


    function get_invoice_freight_suggestion() {
        $key = $_REQUEST["q"];
        $suggestion = array();

        $items = $this->Hsn_sac_code_model->get_freight_suggestion($key);

        foreach ($items as $item) {
            $suggestion[] = array("id" => $item->hsn_code, "text" => $item->hsn_code." (".$item->hsn_description.")");
        }

        $suggestion[] = array("id" => "+", "text" => "+ " . lang("create_new_hsn_code"));

        echo json_encode($suggestion);
    }

    function get_invoice_freight_info_suggestion() {
        $item = $this->Hsn_sac_code_model->get_item_freight_suggestion($this->input->post("item_name"));
        if ($item) {
            echo json_encode(array("success" => true, "item_info" => $item));
        } else {
            echo json_encode(array("success" => false));
        }
    }

    function get_vendor_country_item_info_suggestion() {
        $item = $this->Vendors_model->get_vendor_country_info_suggestion($this->input->post("item_name"));
       // $itemss =  $this->Countries_model->get_item_suggestions_country_name($this->input->post("country_name"));
//print_r($itemss);
    
        if ($item) {
            echo json_encode(array("success" => true, "item_info" => $item));
        } else {
            echo json_encode(array("success" => false));
        }
    }
}
/* End of file estimates.php */
/* Location: ./application/controllers/estimates.php */