<?php

namespace App\Controllers;

use App\Models\CustomFieldsModel;
use App\Models\DeliveryModel;
use App\Models\TaxesModel;
use App\Models\UsersModel;
use App\Models\ClientsModel;
use App\Models\ModeOfDispatchModel;
use App\Models\DeliveryItemsModel;
use App\Models\InvoiceItemsModel;
use App\Models\EstimateItemsModel;
use App\Models\EstimatesModel;
use App\Models\DcTypesModel;
use App\Models\InvoicesModel;

class Delivery extends BaseController
{
    protected $customfieldsmodel;
    protected $deliverymodel;
    protected $taxesmodel;
    protected $usersmodel;
    protected $clientsmodel;
    protected $modeofdispatchmodel;
    protected $deliveryitemsmodel;
    protected $invoiceitemsmodel;
    protected $estimateitemsmodel;
    protected $estimatesmodel;
    protected $dctypesmodel;
    protected $invoicesmodel;

    public function __construct()
    {
        $this->customfieldsmodel = new CustomFieldsModel();
        $this->deliverymodel = new DeliveryModel();
        $this->taxesmodel = new TaxesModel();
        $this->usersmodel = new UsersModel();
        $this->clientsmodel = new ClientsModel();
        $this->modeofdispatchmodel = new ModeOfDispatchModel();
        $this->deliveryitemsmodel = new DeliveryItemsModel();
        $this->invoiceitemsmodel = new InvoiceItemsModel();
        $this->estimateitemsmodel = new EstimateItemsModel();
        $this->estimatesmodel = new EstimatesModel();
        $this->dctypesmodel = new DcTypesModel();
        $this->invoicesmodel = new InvoicesModel();

        parent::__construct();
        $this->initPermissionChecker("delivery");
    }

    public function index()
    {
        $this->checkModuleAvailability("module_delivery");

        $viewData["custom_field_headers"] = $this->customfieldsmodel->getCustomFieldHeadersForTable("delivery", $this->loginUser->isAdmin, $this->loginUser->userType);

        if ($this->loginUser->isAdmin == "1") {
            return view("delivery/index", $viewData);
        } else if ($this->loginUser->userType == "staff" || $this->loginUser->userType == "resource") {
            if ($this->accessType != "all" && !in_array($this->loginUser->id, $this->allowedMembers)) {
                return redirect()->to("forbidden");
            }
            return view("delivery/index", $viewData);
        } else {
            return view("delivery/index", $viewData);
        }
    }

    public function yearly()
    {
        return view("estimates/yearly_estimates");
    }

    public function modal_form()
    {
        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'numeric',
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return redirect()->back()->withInput()->with('error', $validation->getErrors());
        }

        $client_id = $this->request->getPost('client_id');
        $viewData['model_info'] = $this->deliverymodel->getOne($this->request->getPost('id'));

        $project_client_id = $client_id;
        if ($viewData['model_info']->client_id) {
            $project_client_id = $viewData['model_info']->client_id;
        }

        // Make the dropdown lists
        $viewData['clients_dropdown'] = ['' => '-'] + $this->usersmodel->getDropdownList(['first_name', 'last_name'], 'id', ['user_type' => 'staff']);
        $viewData['rm_dropdown'] = ['' => '-'] + $this->usersmodel->getDropdownList(['first_name', 'last_name'], 'id', ['user_type' => 'resource']);
        $viewData['client_id'] = $client_id;
        $viewData['org_clients_dropdown'] = ['' => '-'] + $this->clientsmodel->getDropdownList(['company_name']);
        $viewData['dispatched_through_dropdown'] = ['' => '-'] + $this->modeofdispatchmodel->getDropdownList(['title'], 'id', ['status' => 'active']);
        $viewData['voucher_dropdown'] = ['0' => '-'] + $this->invoicesmodel->getDropdownList(['invoice_no'], 'id', ['deleted' => '0']);
        $viewData['vouchers_dropdown'] = ['0' => '-'] + $this->estimatesmodel->getDropdownList(['estimate_no'], 'id', ['deleted' => '0']);
        $viewData['dc_types_dropdown'] = ['' => '-'] + $this->dctypesmodel->getDropdownList(['title'], 'id', ['deleted' => 0, 'status' => 'active']);
        $viewData["custom_fields"] = $this->customfieldsmodel->getCombinedDetails("estimates", $viewData['model_info']->id, $this->loginUser->isAdmin, $this->loginUser->userType)->getResult();

        return view('delivery/modal_form', $viewData);
    }

    public function save()
    {
        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'numeric',
            'estimate_date' => 'required',
            'valid_until' => 'required'
            // Add more validation rules as needed
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON(['success' => false, 'message' => $validation->getErrors()]);
        }

        $client_id = $this->request->getPost('estimate_client_id');
        $id = $this->request->getPost('id');
        $member = $this->request->getPost('member_type');

        if ($member == 'others') {
            $estimate_data = [
                "client_id" => 0,
                "estimate_date" => $this->request->getPost('estimate_date'),
                "valid_until" => $this->request->getPost('valid_until'),
                "import_from" => $this->request->getPost('import_from'),
                "invoice_no" => $this->request->getPost('invoice_no'),
                "proformainvoice_no" => $this->request->getPost('proformainvoice_no'),
                "dc_type_id" => $this->request->getPost('dc_type_id'),
                "demo_period" => $this->request->getPost('demo_period'),
                "invoice_for_dc" => $this->request->getPost('invoice_for_dc'),
                "invoice_date" => $this->request->getPost('invoice_date'),
                "note" => $this->request->getPost('estimate_note'),
                "f_name" => $this->request->getPost('first_name'),
                "l_name" => $this->request->getPost('last_name'),
                "address" => $this->request->getPost('address'),
                "phone" => $this->request->getPost('phone'),
                "state" => $this->request->getPost('state'),
                "country" => $this->request->getPost('country'),
                "zip" => $this->request->getPost('zip'),
                "member_type" => $this->request->getPost('member_type'),
                "buyers_order_no" => $this->request->getPost('buyers_order_no'),
                "buyers_order_date" => $this->request->getPost('buyers_order_date'),
                "dispatched_through" => $this->request->getPost('dispatched_through'),
                "lc_no" => $this->request->getPost('lc_no'),
                "lc_date" => $this->request->getPost('lc_date'),
                "dispatch_docket" => $this->request->getPost('dispatch_docket'),
                "dispatch_name" => $this->request->getPost('dispatch_name'),
                "waybill_no" => $this->request->getPost('waybill_no'),
                "invoice_client_id" => $this->request->getPost('invoice_client_id'),
                "dispatch_date" => $this->request->getPost('dispatch_date'),
                "delivery_address_company_name" => $this->request->getPost('delivery_address_company_name'),
                "delivery_address" => $this->request->getPost('delivery_address'),
                "delivery_address_state" => $this->request->getPost('delivery_address_state'),
                "delivery_address_city" => $this->request->getPost('delivery_address_city'),
                "delivery_address_country" => $this->request->getPost('delivery_address_country'),
                "delivery_address_zip" => $this->request->getPost('delivery_address_zip'),
                "invoice_delivery_address" => $this->request->getPost('invoice_delivery_address') ? 1 : 0,
            ];
        } elseif ($member == 'om') {
            $outsource = $this->request->getPost('estimate_client_idss');
            $estimate_data = [
                "client_id" => $outsource,
                "estimate_date" => $this->request->getPost('estimate_date'),
                "valid_until" => $this->request->getPost('valid_until'),
                "import_from" => $this->request->getPost('import_from'),
                "invoice_no" => $this->request->getPost('invoice_no'),
                "proformainvoice_no" => $this->request->getPost('proformainvoice_no'),
                "dc_type_id" => $this->request->getPost('dc_type_id'),
                "demo_period" => $this->request->getPost('demo_period'),
                "note" => $this->request->getPost('estimate_note'),
                "member_type" => $this->request->getPost('member_type'),
                "invoice_for_dc" => $this->request->getPost('invoice_for_dc'),
                "invoice_date" => $this->request->getPost('invoice_date'),
                "buyers_order_no" => $this->request->getPost('buyers_order_no'),
                "buyers_order_date" => $this->request->getPost('buyers_order_date'),
                "dispatched_through" => $this->request->getPost('dispatched_through'),
                "lc_no" => $this->request->getPost('lc_no'),
                "lc_date" => $this->request->getPost('lc_date'),
                "dispatch_docket" => $this->request->getPost('dispatch_docket'),
                "dispatch_name" => $this->request->getPost('dispatch_name'),
                "waybill_no" => $this->request->getPost('waybill_no'),
                "invoice_client_id" => $this->request->getPost('invoice_client_id'),
                "dispatch_date" => $this->request->getPost('dispatch_date'),
                "delivery_address_company_name" => $this->request->getPost('delivery_address_company_name'),
                "delivery_address" => $this->request->getPost('delivery_address'),
                "delivery_address_state" => $this->request->getPost('delivery_address_state'),
                "delivery_address_city" => $this->request->getPost('delivery_address_city'),
                "delivery_address_country" => $this->request->getPost('delivery_address_country'),
                "delivery_address_zip" => $this->request->getPost('delivery_address_zip'),
                "invoice_delivery_address" => $this->request->getPost('invoice_delivery_address') ? 1 : 0,
            ];
        } elseif ($member == 'tm') {
            $estimate_data = [
                "client_id" => $client_id,
                "estimate_date" => $this->request->getPost('estimate_date'),
                "valid_until" => $this->request->getPost('valid_until'),
                "import_from" => $this->request->getPost('import_from'),
                "invoice_no" => $this->request->getPost('invoice_no'),
                "proformainvoice_no" => $this->request->getPost('proformainvoice_no'),
                "dc_type_id" => $this->request->getPost('dc_type_id'),
                "demo_period" => $this->request->getPost('demo_period'),
                "note" => $this->request->getPost('estimate_note'),
                "member_type" => $this->request->getPost('member_type'),
                "invoice_for_dc" => $this->request->getPost('invoice_for_dc'),
                "invoice_date" => $this->request->getPost('invoice_date'),
                "buyers_order_no" => $this->request->getPost('buyers_order_no'),
                "buyers_order_date" => $this->request->getPost('buyers_order_date'),
                "dispatched_through" => $this->request->getPost('dispatched_through'),
                "lc_no" => $this->request->getPost('lc_no'),
                "lc_date" => $this->request->getPost('lc_date'),
                "dispatch_docket" => $this->request->getPost('dispatch_docket'),
                "dispatch_name" => $this->request->getPost('dispatch_name'),
                "waybill_no" => $this->request->getPost('waybill_no'),
                "invoice_client_id" => $this->request->getPost('invoice_client_id'),
                "dispatch_date" => $this->request->getPost('dispatch_date'),
                "delivery_address_company_name" => $this->request->getPost('delivery_address_company_name'),
                "delivery_address" => $this->request->getPost('delivery_address'),
                "delivery_address_state" => $this->request->getPost('delivery_address_state'),
                "delivery_address_city" => $this->request->getPost('delivery_address_city'),
                "delivery_address_country" => $this->request->getPost('delivery_address_country'),
                "delivery_address_zip" => $this->request->getPost('delivery_address_zip'),
                "invoice_delivery_address" => $this->request->getPost('invoice_delivery_address') ? 1 : 0,
            ];
        }

        if ($id) {
            // Check if the invoice no already exists for update
            $estimate_data["dc_no"] = $this->request->getPost('dc_no');
            if ($this->deliverymodel->is_estimate_no_exists($estimate_data["dc_no"], $id)) {
                return $this->response->setJSON(['success' => false, 'message' => lang('dc_no_already')]);
            }
        }

        // Create new invoice no and check if it already exists
        if (!$id) {
            $get_last_estimate_id = $this->deliverymodel->get_last_estimate_id_exists();
            $estimate_no_last_id = ($get_last_estimate_id->id + 1);
            $estimate_prefix = get_delivery_id($estimate_no_last_id);

            if ($this->deliverymodel->is_estimate_no_exists($estimate_prefix)) {
                return $this->response->setJSON(['success' => false, 'message' => $estimate_prefix . " " . lang('dc_no_already')]);
            }
        }

        // Save or update the estimate data
        $estimate_id = $this->deliverymodel->save($estimate_data, $id);

        // Import items based on import_from value
        $import = $this->request->getPost('import_from');

        if ($import == 'inv') {
            $options = ["invoice_id" => $this->request->getPost('invoice_no')];
            $list_data = $this->invoiceitemsmodel->get_details($options)->getResult();

            foreach ($list_data as $key) {
                $estimate_item_data = [
                    "estimate_id" => $estimate_id,
                    "title" => $key->title,
                    "description" => $key->description,
                    "quantity" => $key->quantity,
                    "is_tool" => 1,
                    "category" => $key->category,
                    "make" => $key->make,
                    "unit_type" => $key->unit_type,
                    "rate" => $key->rate
                ];

                $this->deliveryitemsmodel->save($estimate_item_data);
            }
        } elseif ($import == 'pi') {
            $options = ["estimate_id" => $this->request->getPost('proformainvoice_no')];
            $list_data = $this->estimateitemsmodel->get_details($options)->getResult();

            foreach ($list_data as $key) {
                $estimate_item_data = [
                    "estimate_id" => $estimate_id,
                    "title" => $key->title,
                    "description" => $key->description,
                    "quantity" => $key->quantity,
                    "is_tool" => 1,
                    "category" => $key->category,
                    "make" => $key->make,
                    "unit_type" => $key->unit_type,
                    "rate" => $key->rate
                ];

                $this->deliveryitemsmodel->save($estimate_item_data);
            }
        }

        if ($estimate_id) {
            // Save new invoice no if creating new record
            if (!$id) {
                $estimate_prefix = get_delivery_id($estimate_id);
                $estimate_prefix_data = [
                    "dc_no" => $estimate_prefix
                ];
                $this->deliverymodel->save($estimate_prefix_data, $estimate_id);
            }

            // Save custom fields, log notification, and return success response
            save_custom_fields("delivery", $estimate_id, $this->login_user->is_admin, $this->login_user->user_type);
            log_notification("delivery_chellan_submitted", ["dc_id" => $estimate_id]);

            return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($estimate_id), 'id' => $estimate_id, 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }
    public function update_delivery_status($estimate_id, $status)
    {
        $deliveryModel = new DeliveryModel();
        $estimatesModel = new EstimatesModel();

        if ($estimate_id && $status) {
            $estimate_info = $deliveryModel->find($estimate_id);

            if (!$estimate_info) {
                return $this->failNotFound('Estimate not found');
            }

            // Check user type and permissions
            if ($this->currentUser->user_type == 'client') {
                // Updating by client
                // Client can only update the status once to 'accepted' or 'declined'
                if ($estimate_info['status'] == 'sent' && ($status == 'accepted' || $status == 'declined')) {
                    $estimate_data = ['status' => $status];
                    $estimatesModel->update($estimate_id, $estimate_data);

                    // Create notification
                    if ($status == 'accepted') {
                        log_notification('estimate_accepted', ['estimate_id' => $estimate_id]);
                    } elseif ($status == 'declined') {
                        log_notification('estimate_rejected', ['estimate_id' => $estimate_id]);
                    }
                }
            } else {
                // Updating by team members
                $validStatuses = ['draft', 'given', 'received', 'sold', 'ret_sold', 'approve_ret_sold', 'modified'];

                if (in_array($status, $validStatuses)) {
                    $estimate_data = ['status' => $status];

                    // Additional logic for specific statuses
                    if ($status == 'given' || $status == 'received' || $status == 'sold' || $status == 'ret_sold' || $status == 'approve_ret_sold' || $status == 'modified') {
                        $estimate_data['delivered_date'] = date('Y-m-d');
                    }

                    $deliveryModel->update($estimate_id, $estimate_data);

                    // Perform additional database operations based on status
                    if ($status == 'given' || $status == 'received') {
                        $deliveryItems = $deliveryModel->getDeliveryItems($estimate_id);

                        foreach ($deliveryItems as $item) {
                            // Update tools and items quantity or stock
                            // Example: $this->updateToolQuantity($item['title'], $item['quantity']);
                        }
                    }

                    // Create notification based on status
                    if ($status == 'sent') {
                        log_notification('estimate_sent', ['estimate_id' => $estimate_id]);
                    }
                }
            }

            return $this->respondUpdated(['message' => 'Estimate status updated successfully']);
        } else {
            return $this->failValidationError('Estimate ID and status are required');
        }
    }
    function delete() {
        //$this->access_only_allowed_members();
    
        validate_submitted_data(array(
            "id" => "required|numeric"
        ));
    
        $id = $this->input->post('id');
        if ($this->input->post('undo')) {
            if ($this->Delivery_model->delete($id, true)) {
                echo json_encode(array("success" => true, "data" => $this->_row_data($id), "message" => lang('record_undone')));
            } else {
                echo json_encode(array("success" => false, lang('error_occurred')));
            }
        } else {
            if ($this->Delivery_model->delete($id)) {
                echo json_encode(array("success" => true, 'message' => lang('record_deleted')));
            } else {
                echo json_encode(array("success" => false, 'message' => lang('record_cannot_be_deleted')));
            }
        }
    }
    
    /* list of estimates, prepared for datatable  */

    public function list_data()
    {
        //$this->access_only_allowed_members();
    
        helper(['form', 'url']);
    
        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("delivery", $this->login_user->is_admin, $this->login_user->user_type);
    
        $options = [
            "status" => $this->request->getPost("status"),
            "start_date" => $this->request->getPost("start_date"),
            "end_date" => $this->request->getPost("end_date"),
            "custom_fields" => $custom_fields
        ];
    
        $list_data = $this->Delivery_model->get_details($options)->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data, $custom_fields);
        }
    
        return $this->response->setJSON(["data" => $result]);
    }
    
    /* list of estimate of a specific client, prepared for datatable  */
    public function estimate_list_data_of_client($client_id)
    {
        $this->access_only_allowed_members_or_client_contact($client_id);
    
        helper(['form', 'url']);
    
        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("estimates", $this->login_user->is_admin, $this->login_user->user_type);
    
        $options = [
            "client_id" => $client_id,
            "status" => $this->request->getPost("status"),
            "custom_fields" => $custom_fields
        ];
    
        if ($this->login_user->user_type == "client") {
            //don't show draft estimates to clients.
            $options["exclude_draft"] = true;
        }
    
        $list_data = $this->Estimates_model->get_details($options)->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data, $custom_fields);
        }
    
        return $this->response->setJSON(["data" => $result]);
    }
    
    /* return a row of estimate list table */

    private function _row_data($id)
    {
        $custom_fields_model = new CustomFieldsModel();
        $custom_fields = $custom_fields_model->getAvailableFieldsForTable("delivery", $this->loginUser->isAdmin, $this->loginUser->userType);

        $options = ["id" => $id, "custom_fields" => $custom_fields];
        $delivery_model = new DeliveryModel();
        $data = $delivery_model->getDetails($options)->getRow();
        return $this->_make_row($data, $custom_fields);
    }


    /* prepare a row of estimate list table */
    private function _make_row($data, $custom_fields)
    {
        $estimateNoValue = $data->dc_no ? $data->dc_no : getDeliveryId($data->id);
        $estimateNoUrl = "";
        if ($this->loginUser->userType == "staff") {
            $estimateNoUrl = anchor(site_url("delivery/view/" . $data->id), $estimateNoValue);
        } else {
            $estimateNoUrl = anchor(site_url("delivery/preview/" . $data->id), $estimateNoValue);
        }

        $firstName = $data->client_id ? $data->firstName : $data->fName;
        $lastName = $data->client_id ? $data->lastName : $data->lName;

        $row_data = [
            $estimateNoUrl,
            anchor(site_url("team_members/view/" . $data->client_id), $firstName . " " . $lastName),
            $data->estimateDate,
            formatToDate($data->estimateDate, false),
            $data->deliveredDate,
            $data->receivedDate,
            $this->_get_estimate_status_label($data),
        ];

        foreach ($custom_fields as $field) {
            $cfId = "cfv_" . $field->id;
            $row_data[] = view("custom_fields/output_" . $field->fieldType, ["value" => $data->$cfId]);
        }

        $row_data[] = modal_anchor(site_url("delivery/modal_form"), "<i class='fa fa-pencil'></i>", ["class" => "edit", "title" => lang('edit_delivery'), "data-post-id" => $data->id])
            . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_delivery'), "class" => "delete", "data-id" => $data->id, "data-action-url" => site_url("delivery/delete"), "data-action" => "delete-confirmation"]);

        return $row_data;
    }

    //prepare estimate status label 
    private function _get_estimate_status_label($estimate_info, $return_html = true)
    {
        $estimateStatusClass = "label-default";

        if ($this->loginUser->userType == "client") {
            if ($estimate_info->status == "sent") {
                $estimate_info->status = "new";
            } else if ($estimate_info->status == "declined") {
                $estimate_info->status = "rejected";
            }
        }

        switch ($estimate_info->status) {
            case "draft":
                $estimateStatusClass = "label-default";
                break;
            case "received":
            case "returned":
                $estimateStatusClass = "label-success";
                break;
            case "given":
                $estimateStatusClass = "label-danger";
                break;
            case "sold":
                $estimateStatusClass = "label-warning";
                break;
            case "ret_sold":
                $estimateStatusClass = "label-danger";
                break;
            case "approve_ret_sold":
                $estimateStatusClass = "label-primary";
                break;
            case "invoice_created":
                $estimateStatusClass = "label-final";
                break;
            case "modified":
                $estimateStatusClass = "label-warning";
                break;
        }

        $estimateStatus = "<span class='label $estimateStatusClass large'>" . lang($estimate_info->status) . "</span>";
        return $return_html ? $estimateStatus : $estimate_info->status;
    }


    /* load estimate details view */

    public function view($estimate_id = 0)
    {
        if ($estimate_id) {
            $view_data = get_delivery_making_data($estimate_id);

            if ($view_data) {
                $view_data['estimate_status_label'] = $this->_get_estimate_status_label($view_data["estimate_info"]);
                $view_data['estimate_status'] = $this->_get_estimate_status_label($view_data["estimate_info"], false);

                $access_info = $this->get_access_info("delivery");
                $view_data["delivery_access_all"] = $access_info->access_type;
                $view_data["delivery_access"] = $access_info->allowed_members;
                $view_data["can_create_projects"] = $this->can_create_projects();

                return view("delivery/view", $view_data);
            } else {
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
            }
        } else {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
    }

    // Private method to get estimate total view
    private function _get_estimate_total_view($estimate_id = 0)
    {
        return view('estimates/estimate_total_section', $view_data);
    }

    /* load item modal */

      // Modal form for delivery item
      public function item_modal_form()
      {
          validate([
              "id" => "numeric"
          ]);
  
          $estimate_id = $this->request->getPost('estimate_id');
  
          $view_data['model_info'] = $this->DeliveryItemsModel->getOne($this->request->getPost('id'));
          $view_data["unit_type_dropdown"] = $this->_get_unit_type_dropdown_select2_data();
  
          if (!$estimate_id) {
              $estimate_id = $view_data['model_info']->estimate_id;
          }
  
          $view_data['estimate_id'] = $estimate_id;
          $manufacturers = $this->ManufacturerModel->getAllWhere(["deleted" => 0, "status" => "active"], 0, 0, "title")->getResult();
  
          $make_dropdown = [["id" => "", "text" => "-"]];
          foreach ($manufacturers as $manufacturer) {
              $make_dropdown[] = ["id" => $manufacturer->id, "text" => $manufacturer->title];
          }
          $view_data['make_dropdown'] = json_encode($make_dropdown);
  
          $product_categories_dropdowns = $this->ProductCategoriesModel->getAllWhere(["deleted" => 0, "status" => "active"])->getResult();
          $product_categories_dropdown = [["id" => "", "text" => "-"]];
          foreach ($product_categories_dropdowns as $product_category) {
              $product_categories_dropdown[] = ["id" => $product_category->id, "text" => $product_category->title];
          }
          $view_data['product_categories_dropdown'] = json_encode($product_categories_dropdown);
  
          return view('delivery/item_modal_form', $view_data);
      }

      private function _get_unit_type_dropdown_select2_data()
      {
          $unit_types = $this->UnitTypeModel->getAllWhere(["deleted" => 0, "status" => "active"])->getResult();
          $unit_type_dropdown = [];
  
          foreach ($unit_types as $unit_type) {
              $unit_type_dropdown[] = ["id" => $unit_type->title, "text" => $unit_type->title];
          }
          return $unit_type_dropdown;
      }
  

      public function save_item()
      {
          //$this->access_only_allowed_members();
      
          helper(['form', 'url']);
          $validate = \Config\Services::validation();
      
          $validationRules = [
              'id' => 'numeric',
              'estimate_id' => 'required|numeric'
          ];
      
          if (!$this->validate($validationRules)) {
              echo json_encode(['success' => false, 'message' => $validate->getErrors()]);
              return;
          }
      
          $estimate_id = $this->request->getPost('estimate_id');
          $id = $this->request->getPost('id');
      
          $title = $this->request->getPost('estimate_item_title') ?? $this->request->getPost('estimate_item_titles');
          $is_tool = $this->request->getPost('estimate_item_title') ? 0 : 1;
      
          $quantity = unformat_currency($this->request->getPost('estimate_item_quantity'));
      
          $ret_sold = $this->request->getPost('ret_sold_status');
          if ($ret_sold == 'returned') {
              $sold = $quantity - $this->request->getPost('ret_sold');
              $return = $this->request->getPost('ret_sold');
          } elseif ($ret_sold == 'sold') {
              $sold = $this->request->getPost('ret_sold');
              $return = $quantity - $this->request->getPost('ret_sold');
          } else {
              $sold = 0;
              $return = 0;
          }
      
          $estimate_item_data = [
              'estimate_id' => $estimate_id,
              'title' => $title,
              'description' => $this->request->getPost('estimate_item_description'),
              'quantity' => $quantity,
              'is_tool' => $is_tool,
              'category' => $this->request->getPost('category'),
              'make' => $this->request->getPost('make'),
              'unit_type' => $this->request->getPost('estimate_unit_type'),
              'rate' => unformat_currency($this->request->getPost('estimate_item_rate')),
              'ret_sold' => $sold,
              'sold' => $return,
              'ret_sold_status' => $ret_sold,
              'price_visibility' => $this->request->getPost('price_visibility')
          ];
      
          $estimate_item_id = $this->Delivery_items_model->save($estimate_item_data, $id);
      
          if ($estimate_item_id) {
              $add_new_item_to_library = $this->request->getPost('add_new_item_to_library');
              if ($add_new_item_to_library) {
                  $library_item_data = [
                      'title' => $this->request->getPost('estimate_item_title'),
                      'quantity' => $this->request->getPost('estimate_item_quantity'),
                      'description' => $this->request->getPost('estimate_item_description'),
                      'category' => $this->request->getPost('category'),
                      'make' => $this->request->getPost('make'),
                      'unit_type' => $this->request->getPost('estimate_unit_type'),
                      'rate' => unformat_currency($this->request->getPost('estimate_item_rate'))
                  ];
                  $this->Tools_model->save($library_item_data);
              }
      
              $options = ['id' => $estimate_item_id];
              $item_info = $this->Delivery_items_model->getDetails($options)->getRow();
              echo json_encode([
                  'success' => true,
                  'estimate_id' => $item_info->estimate_id,
                  'data' => $this->_make_item_row($item_info),
                  'id' => $estimate_item_id,
                  'message' => lang('record_saved')
              ]);
          } else {
              echo json_encode(['success' => false, 'message' => lang('error_occurred')]);
          }
      }
      

      public function delete_item()
      {
          //$this->access_only_allowed_members();
      
          helper(['form', 'url']);
          $validate = \Config\Services::validation();
      
          $validationRules = [
              'id' => 'required|numeric'
          ];
      
          if (!$this->validate($validationRules)) {
              echo json_encode(['success' => false, 'message' => $validate->getErrors()]);
              return;
          }
      
          $id = $this->request->getPost('id');
      
          if ($this->request->getPost('undo')) {
              if ($this->Delivery_items_model->delete($id, true)) {
                  $options = ['id' => $id];
                  $item_info = $this->Delivery_items_model->getDetails($options)->getRow();
                  echo json_encode([
                      'success' => true,
                      'estimate_id' => $item_info->estimate_id,
                      'data' => $this->_make_item_row($item_info),
                      'message' => lang('record_undone')
                  ]);
              } else {
                  echo json_encode(['success' => false, 'message' => lang('error_occurred')]);
              }
          } else {
              if ($this->Delivery_items_model->delete($id)) {
                  $item_info = $this->Delivery_items_model->getOne($id);
                  echo json_encode(['success' => true, 'estimate_id' => $item_info->estimate_id, 'message' => lang('record_deleted')]);
              } else {
                  echo json_encode(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
              }
          }
      }
      
      public function item_list_data($estimate_id = 0)
      {
          //$this->access_only_allowed_members(); // Uncomment if needed in your application
      
          $list_data = $this->Delivery_items_model->getDetails(["estimate_id" => $estimate_id]); // Assuming CI4 model method
          $result = [];
          foreach ($list_data as $data) {
              $result[] = $this->_make_item_row($data);
          }
          echo json_encode(["data" => $result]);
      }
      

    private function _make_item_row($data)
    {
        $list_data = $this->Delivery_model->getDetails(["id" => $data->estimate_id])->getRow(); // Assuming CI4 model method
        $item = "<b>{$data->title}</b>";
        if ($data->description) {
            $item .= "<br /><span>" . nl2br($data->description) . "</span>";
        }
        $type = $data->unit_type ?: "";
        $make_name = $this->Manufacturer_model->getOne($data->make); // Assuming CI4 model method
        $category_name = $this->Product_categories_model->getOne($data->category); // Assuming CI4 model method
    
        if ($list_data && $list_data->status == 'ret_sold') {
            $return = $data->ret_sold_status ? $data->ret_sold : '0';
            $sold = $data->ret_sold_status ? $data->sold : '0';
        } else {
            $return = '0';
            $sold = '0';
        }
    
        return [
            $item,
            $category_name ? $category_name->title : "-",
            $make_name ? $make_name->title : "-",
            toDecimalFormat($data->quantity) . " " . $type,
            toCurrency($data->rate, $data->currency_symbol),
            toCurrency(($data->rate * $data->quantity), $data->currency_symbol),
            $sold,
            $return,
            modalAnchor("delivery/item_modal_form", "<i class='fa fa-pencil'></i>", ["class" => "edit", "title" => lang('edit_delivery'), "data-post-id" => $data->id])
                . jsAnchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete'), "class" => "delete", "data-id" => $data->id, "data-action-url" => "delivery/delete_item", "data-action" => "delete"])
        ];
    }
    
    /* prepare suggestion of estimate item */
    public function get_item_suggestion()
{
    $key = $this->request->getVar("q");
    $estimateId = $this->request->getVar("s");

    $listData = $this->Delivery_items_model->get_details(["estimate_id" => $estimateId])->getResult();
    $deliveryItems = [];

    if ($listData) {
        foreach ($listData as $code) {
            $deliveryItems[] = $code->title;
        }
    } else {
        $deliveryItems = ['empty'];
    }

    $items = $this->Invoice_items_model->get_item_suggestions($key, $deliveryItems);

    $suggestion = [];
    foreach ($items as $item) {
        $suggestion[] = ["id" => $item->title, "text" => $item->title];
    }

    return $this->response->setJSON($suggestion);
}


public function get_item_info_suggestion()
{
    $itemName = $this->request->getPost("item_name");
    $item = $this->Invoice_items_model->get_item_info_suggestion($itemName);

    if ($item) {
        return $this->response->setJSON(["success" => true, "item_info" => $item]);
    } else {
        return $this->response->setJSON(["success" => false]);
    }
}

public function preview($estimateId = 0, $showClosePreview = false)
{
    $viewData = [];

    if ($estimateId) {
        $estimateData = get_delivery_making_data($estimateId);
        $this->_check_estimate_access_permission($estimateData);

        $estimateInfo = get_array_value($estimateData, "estimate_info");
        $estimateData['estimate_status_label'] = $this->_get_estimate_status_label($estimateInfo);

        $viewData['estimate_preview'] = prepare_delivery_pdf($estimateData, "html");

        $viewData['show_close_preview'] = $showClosePreview && $this->login_user->user_type === "staff";

        $viewData['estimate_id'] = $estimateId;

        return view('delivery/delivery_preview', $viewData);
    } else {
        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }
}

public function download_pdf($estimateId = 0)
{
    if ($estimateId) {
        $estimateData = get_delivery_making_data($estimateId);

        prepare_delivery_pdf($estimateData, "download");
    } else {
        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }
}

private function _check_estimate_access_permission($estimateData)
{
    if (!$estimateData) {
        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }

    $estimateInfo = get_array_value($estimateData, "estimate_info");

    if ($this->login_user->user_type == "client") {
        if ($this->login_user->client_id != $estimateInfo->client_id) {
            return redirect()->to('forbidden');
        }
    } else {
        // $this->access_only_allowed_members();
    }
}
public function get_delivery_status_bar($estimateId = 0)
{
    $estimateInfo = $this->Delivery_model->get_details(["id" => $estimateId])->getRow();
    $viewData["estimate_info"] = $estimateInfo;
    $viewData['estimate_status_label'] = $this->_get_estimate_status_label($estimateInfo);

    return view('delivery/delivery_status_bar', $viewData);
}

public function create_dc_from_invoice($invoiceId = 0)
{
    $invoice = $this->Invoices_model->get_details(["id" => $invoiceId])->getRow();
    $clientDetails = $this->Clients_model->get_details(["id" => $invoice->client_id])->getRow();
    $clientState = $this->States_model->get_details(["id" => $clientDetails->state])->getRow();
    $userDetails = $this->Users_model->get_details(["id" => $invoice->dispatch_user_id])->getRow();

    $dispatchBy = ($invoice->member_type == "others") ? 0 : $invoice->dispatch_user_id;

    $deliveryData = [
        "client_id" => $dispatchBy,
        "invoice_client_id" => $invoice->client_id,
        "estimate_date" => $invoice->bill_date,
        "valid_until" => $invoice->bill_date,
        "import_from" => '-',
        "invoice_no" => 0,
        "proformainvoice_no" => 0,
        "invoice_for_dc" => $invoiceId,
        "invoice_date" => $invoice->bill_date,
        "note" => $invoice->note,
        "buyers_order_no" => $invoice->buyers_order_no,
        "buyers_order_date" => $invoice->buyers_order_date,
        "dispatch_date" => $invoice->delivery_note_date,
        "dc_type_id" => 1,
        "dispatched_through" => $invoice->dispatched_through,
        "invoice_delivery_address" => $invoice->invoice_delivery_address,
        "f_name" => $invoice->f_name,
        "l_name" => $invoice->l_name,
        "lc_no" => $invoice->lc_no,
        "lc_date" => $invoice->lc_date,
        "dispatch_docket" => $invoice->dispatch_docket,
        "dispatch_name" => $invoice->dispatch_name,
        "waybill_no" => $invoice->waybill_no,
        "delivery_address_company_name" => ($invoice->invoice_delivery_address == "1") ? $invoice->delivery_address_company_name : $clientDetails->company_name,
        "delivery_address_phone" => ($invoice->invoice_delivery_address == "1") ? $invoice->delivery_address_phone : $clientDetails->phone,
        "delivery_address" => ($invoice->invoice_delivery_address == "1") ? $invoice->delivery_address : $clientDetails->address,
        "delivery_address_country" => ($invoice->invoice_delivery_address == "1") ? $invoice->delivery_address_country : $clientDetails->country,
        "delivery_address_state" => ($invoice->invoice_delivery_address == "1") ? $invoice->delivery_address_state : $clientState->title,
        "delivery_address_city" => ($invoice->invoice_delivery_address == "1") ? $invoice->delivery_address_city : $clientDetails->city,
        "delivery_address_zip" => ($invoice->invoice_delivery_address == "1") ? $invoice->delivery_address_zip : $clientDetails->zip,
        "member_type" => $invoice->member_type,
        "status" => 'draft'
    ];

    $deliveryId = $this->Delivery_model->save($deliveryData);

    if ($deliveryId) {
        $invoiceItems = $this->Invoice_items_model->get_details(["invoice_id" => $invoiceId])->getResult();

        foreach ($invoiceItems as $key) {
            $deliveryItemData = [
                "estimate_id" => $deliveryId,
                "title" => $key->title,
                "description" => $key->description,
                "quantity" => $key->quantity,
                "is_tool" => 1,
                "category" => $key->category,
                "make" => $key->make,
                "unit_type" => $key->unit_type,
                "rate" => $key->rate
            ];

            $this->Delivery_items_model->save($deliveryItemData);
        }
    }

    if ($deliveryId) {
        return redirect()->to('/delivery/view/' . $deliveryId)->refresh();
    }
}

public function assoc_details()
{
    $rate = $this->request->getPost("item_name");
    $groupList = "";

    if ($rate) {
        $groups = explode(",", $rate);
        foreach ($groups as $group) {
            if ($group) {
                $options = ["id" => $group];
                $listGroup = $this->Part_no_generation_model->get_details($options)->getRow();
                $groupList += $listGroup->rate;
            }
        }
    }

    if ($groupList) {
        return $this->response->setJSON(["success" => true, "assoc_rate" => $groupList]);
    } else {
        return $this->response->setJSON(["success" => false]);
    }
}
}

  