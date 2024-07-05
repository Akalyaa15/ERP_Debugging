<?php
namespace App\Controllers;

use App\Models\VendorsInvoiceListModel;
use App\Models\VendorsModel;
use App\Models\PaymentMethodsModel;
use App\Models\GstStateCodeModel;
use App\Models\PurchaseOrderPaymentsModel;
use App\Models\VendorsInvoicePaymentsListModel;
use App\Models\PurchaseOrdersModel;
use App\Models\TasksModel;
use CodeIgniter\API\ResponseTrait;

class VendorsInvoiceList extends BaseController {
    use ResponseTrait;

    protected $vendorsInvoiceStatusModel;
    protected $vendorsModel;
    protected $vendorsInvoiceListModel;
    protected $paymentMethodsModel;
    protected $gstStateCodeModel;
    protected $purchaseOrderPaymentsModel;
    protected $vendorsInvoicePaymentsListModel;
    protected $purchaseOrdersModel;
    protected $tasksModel;

    public function __construct() {
        $this->vendorsInvoiceStatusModel = new \App\Models\VendorsInvoiceStatusModel(); // Adjust as per your actual model name
        $this->vendorsModel = new VendorsModel();
        $this->vendorsInvoiceListModel = new VendorsInvoiceListModel();
        $this->paymentMethodsModel = new PaymentMethodsModel();
        $this->gstStateCodeModel = new GstStateCodeModel();
        $this->purchaseOrderPaymentsModel = new PurchaseOrderPaymentsModel();
        $this->vendorsInvoicePaymentsListModel = new VendorsInvoicePaymentsListModel();
        $this->purchaseOrdersModel = new PurchaseOrdersModel();
        $this->tasksModel = new TasksModel();
        
        $this->session = session();
        $this->helpers = helper(['form', 'url']);
        helper(['form', 'url']);
    }

    public function index() {
        $this->checkModuleAvailability("module_purchase_order");

        $viewData['vendorsDropdown'] = json_encode($this->_getVendorsDropdown());

        if ($this->login_user->is_admin == "1") {
            return view('vendors_invoice_list/index', $viewData);
        } else if ($this->login_user->user_type == "staff") {
            if ($this->access_type != "all" && !in_array($this->login_user->id, $this->allowed_members)) {
                return redirect()->to('forbidden');
            }
            return view('vendors_invoice_list/index', $viewData);
        } else {
            return view('vendors_invoice_list/index', $viewData);
        }
    }

    private function _getVendorsDropdown() {
        $vendorsDropdown = [['id' => '', 'text' => '- ' . lang('vendor') . ' -']];
        $vendors = $this->vendorsModel->getDropdownList(['company_name']);
        foreach ($vendors as $key => $value) {
            $vendorsDropdown[] = ['id' => $key, 'text' => $value];
        }
        return $vendorsDropdown;
    }

    public function yearly() {
        return view('vendors_invoice_list/yearly_vendors_invoice_list');
    }

    public function modalForm() {
        validate([
            'id' => 'required|numeric'
        ]);

        $modelInfo = $this->vendorsInvoiceListModel->getOne($this->request->getPost('id'));
        $vendorId = $this->request->getPost('vendor_id');

        $viewData = [
            'vendor_id' => $vendorId,
            'model_info' => $modelInfo,
            'vendors_dropdown' => ['' => '-'] + $this->vendorsModel->getDropdownList(['company_name']),
            'payment_methods_dropdown' => $this->paymentMethodsModel->getDropdownList(['title'], 'id', ['online_payable' => 0, 'deleted' => 0]),
            'gst_code_dropdown' => $this->_getGstCodeDropdownSelect2Data()
        ];

        $poInfo = $this->purchaseOrdersModel->getOne($modelInfo->purchase_order_id);
        $purchaseIdDropdown = [['id' => '', 'text' => '-']];
        $purchaseIdDropdown[] = ['id' => $modelInfo->purchase_order_id, 'text' => $poInfo->purchase_no ? $poInfo->purchase_no : get_purchase_order_id($modelInfo->purchase_order_id)];
        $viewData['purchase_id_dropdown'] = $purchaseIdDropdown;

        return view('vendors_invoice_list/modal_form', $viewData);
    }

    private function _getGstCodeDropdownSelect2Data($showHeader = false) {
        $gstCode = $this->gstStateCodeModel->findAll();
        $gstCodeDropdown = [];

        foreach ($gstCode as $code) {
            $gstCodeDropdown[] = ['id' => $code->gstin_number_first_two_digits, 'text' => $code->title];
        }
        return $gstCodeDropdown;
    }

    public function save() {
        validate([
            'id' => 'numeric'
        ]);

        $id = $this->request->getPost('id');
        $targetPath = get_setting("timeline_file_path");
        $filesData = moveFilesFromTempDirToPermanentDir($targetPath, "note");
        $newFiles = unserialize($filesData);
        $purchaseOrderId = $this->request->getPost('purchase_order_id');

        $data = [
            "vendor_id" => $this->request->getPost('vendor_id'),
            "invoice_no" => $this->request->getPost('invoice_no'),
            "invoice_date" => $this->request->getPost('invoice_date'),
            "amount" => $this->request->getPost('amount'),
            "igst_tax" => $this->request->getPost('igst_tax'),
            "cgst_tax" => $this->request->getPost('cgst_tax'),
            "sgst_tax" => $this->request->getPost('sgst_tax'),
            "description" => $this->request->getPost('description'),
            "total" => $this->request->getPost('total'),
            "state_tax" => $this->request->getPost('state_tax'),
            "gst_number" => $this->request->getPost('gst_number'),
            "gstin_number_first_two_digits" => $this->request->getPost('gstin_number_first_two_digits'),
            "purchase_order_id" => $purchaseOrderId
        ];

        if ($id) {
            $noteInfo = $this->vendorsInvoiceListModel->getOne($id);
            $timelineFilePath = get_setting("timeline_file_path");
            $newFiles = updateSavedFiles($timelineFilePath, $noteInfo->files, $newFiles);
        }

        if (!$id) {
            $data["invoice_no"] = $this->request->getPost('invoice_no');
            if ($this->vendorsInvoiceListModel->isVendorsInvoiceExists($data["invoice_no"])) {
                return $this->fail(lang('vendors_invoice_already'));
            }
        }

        if ($id) {
            $data["invoice_no"] = $this->request->getPost('invoice_no');
            $data["id"] = $this->request->getPost('id');
            if ($this->vendorsInvoiceListModel->isVendorsInvoiceExists($data["invoice_no"], $id)) {
                return $this->fail(lang('vendors_invoice_already'));
            }
        }

        $data["files"] = serialize($newFiles);

        if ($data["files"] == 'a:0:{}') {
            return $this->fail('*Uploading files are required');
        }

        $saveId = $this->vendorsInvoiceListModel->save($data, $id);

        if ($saveId) {
            if (!$id) {
                if ($purchaseOrderId) {
                    $purchaseOptions = ["purchase_order_id" => $purchaseOrderId];
                    $purchasePaymentList = $this->purchaseOrderPaymentsModel->getDetails($purchaseOptions)->getResult();

                    if ($purchasePaymentList) {
                        foreach ($purchasePaymentList as $purchasePaymentListData) {
                            $paymentData = [
                                "task_id" => $saveId,
                                "title" => $purchasePaymentListData->amount,
                                "payment_date" => $purchasePaymentListData->payment_date,
                                "payment_method_id" => $purchasePaymentListData->payment_method_id,
                                "description" => $purchasePaymentListData->note,
                                "reference_number" => $purchasePaymentListData->reference_number,
                                "po_payment_id" => $purchasePaymentListData->id,
                                "purchase_order_id" => $purchasePaymentListData->purchase_order_id,
                                "files" => $purchasePaymentListData->files
                            ];
                            $vendorInvoicePaymentSaveId = $this->vendorsInvoicePaymentsListModel->save($paymentData);
                        }
                    }
                }
            }

            return $this->respondCreated(['success' => true, 'data' => $this->_rowData($saveId), 'id' => $saveId, 'message' => lang('record_saved')]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

     public function list_data()
        {
            //$this->access_only_allowed_members();
    
            $status = $this->request->getPost('status');
            $start_date = $this->request->getPost('start_date');
            $end_date = $this->request->getPost('end_date');
            $vendor_id = $this->request->getPost('vendor_id');
    
            $options = [
                "start_date" => $start_date,
                "status" => $status,
                "end_date" => $end_date,
                "login_user_id" => $this->login_user->id,
                "access_type" => $this->access_type,
                "allowed_members" => $this->allowed_members,
                "vendor_id" => $vendor_id
            ];
    
            $list_data = $this->Vendors_invoice_list_model->getDetails($options)->getResult();
    
            $result = [];
            foreach ($list_data as $data) {
                $result[] = $this->_make_row($data);
            }
    
            return $this->response->setJSON(["data" => $result]);
        }
    
        public function vendors_invoice_list_data_of_vendor($vendor_id)
        {
            $this->access_only_allowed_members_or_client_contact($vendor_id);
    
            $status = $this->request->getPost('status');
    
            $options = [
                "vendor_id" => $vendor_id,
                "status" => $status
            ];
    
            $list_data = $this->Vendors_invoice_list_model->getDetails($options)->getResult();
    
            $result = [];
            foreach ($list_data as $data) {
                $result[] = $this->_make_row($data);
            }
    
            return $this->response->setJSON(["data" => $result]);
        }
    
        private function _make_row($data)
        {
            $files_link = "";
            if ($data->files) {
                $files = unserialize($data->files);
                if (count($files)) {
                    foreach ($files as $file) {
                        $file_name = get_array_value($file, "file_name");
                        $link = " fa fa-" . get_file_icon(strtolower(pathinfo($file_name, PATHINFO_EXTENSION)));
                        $files_link .= view('notes/file_preview', ['file_name' => $file_name, 'link' => $link]); // Assuming 'notes/file_preview' is a view file
                    }
                }
            }
    
            $purchase_order_url = "-";
            if ($data->purchase_order_id) {
                $purchase_info = $this->Purchase_orders_model->getOne($data->purchase_order_id);
                $purchase_order_url = anchor('purchase_orders/view/' . $data->purchase_order_id, $purchase_info->purchase_no ? $purchase_info->purchase_no : get_purchase_order_id($data->purchase_order_id));
            }
    
            $due = ignor_minor_value($data->total - $data->paid_amount);
    
            return [
                $data->invoice_no,
                $data->invoice_date,
                anchor('vendors/view/' . $data->vendor_id, $data->vendor_name),
                $purchase_order_url,
                to_currency($data->amount, $data->currency_symbol),
                to_currency($data->igst_tax, $data->currency_symbol),
                to_currency($data->cgst_tax, $data->currency_symbol),
                to_currency($data->sgst_tax, $data->currency_symbol),
                to_currency($data->total, $data->currency_symbol),
                to_currency($data->paid_amount, $data->currency_symbol),
                to_currency($due, $data->currency_symbol),
                $files_link,
                $this->_get_vendor_invoice_status_label($data),
                modal_anchor('vendors_invoice_list/task_view', '<i class="fa fa-pencil"></i>', ['class' => 'add_payment', 'title' => lang('add_payment'), 'data-post-id' => $data->id]) .
                js_anchor('<i class="fa fa-times fa-fw"></i>', ['title' => lang('delete_tax'), 'class' => 'delete', 'data-id' => $data->id, 'data-action-url' => 'vendors_invoice_list/delete'])
            ];
        }
    
        private function _get_vendor_invoice_status_label($data)
        {
            // Implement logic for status label
        }
    
        public function task_view()
        {
            $task_id = $this->request->getPost('id');
            $model_info = $this->Vendors_invoice_list_model->getDetails(["id" => $task_id])->getRow();
    
            if (!$model_info) {
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
            }
    
            $view_data = [
                'model_info' => $model_info,
                'task_id' => $task_id,
                'payment_methods_dropdown' => $this->Payment_methods_model->getDropdownList(["title"], "id", ["online_payable" => 0, "deleted" => 0])
            ];
    
            return view('vendors_invoice_list/view', $view_data);
        }
    
        public function get_vendors_invoice_no_suggestion()
        {
            $item = $this->Vendors_invoice_list_model->getInvoiceNoSuggestion($this->request->getPost("item_name"));
            if ($item) {
                return $this->response->setJSON(["success" => true, "item_info" => $item]);
            } else {
                return $this->response->setJSON(["success" => false]);
            }
        }
    
        public function save_checklist_item()
        {
            $task_id = $this->request->getPost("task_id");
    
            $validation = \Config\Services::validation();
            $validation->setRule('task_id', 'Task ID', 'required|numeric');
            
            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON(["success" => false, "message" => $validation->getErrors()]);
            }
    
            $target_path = get_setting("timeline_file_path");
            $files_data = move_files_from_temp_dir_to_permanent_dir($target_path, "vendor_invoice_create");
            $new_files = unserialize($files_data);
    
            $data = [
                "task_id" => $task_id,
                "title" => $this->request->getPost("checklist-add-item"),
                "payment_date" => $this->request->getPost("checklist-add-item-date"),
                "payment_method_id" => $this->request->getPost("payment_method_id"),
                "description" => $this->request->getPost("description"),
                "reference_number" => $this->request->getPost("reference_number"),
                "files" => serialize($new_files)
            ];
    
            if (empty($new_files)) {
                return $this->response->setJSON(["success" => false, "message" => "*Uploading files are required"]);
            }
    
            $save_id = $this->Vendors_invoice_payments_list_model->save($data);
    
            if ($save_id) {
                $item_info = $this->Vendors_invoice_payments_list_model->getOne($save_id);
                return $this->response->setJSON(["success" => true, "data" => $this->_make_checklist_item_row($item_info), 'id' => $save_id]);
            } else {
                return $this->response->setJSON(["success" => false]);
            }
        }
    
        private function _make_checklist_item_row($data)
        {
            $files_link = "";
            if ($data->files) {
                $files = unserialize($data->files);
                if (count($files)) {
                    foreach ($files as $file) {
                        $file_name = get_array_value($file, "file_name");
                        $link = " fa fa-" . get_file_icon(strtolower(pathinfo($file_name, PATHINFO_EXTENSION)));
                        $files_link .= view('notes/file_preview', ['file_name' => $file_name, 'link' => $link]); // Assuming 'notes/file_preview' is a view file
                    }
                }
            }
    
            $checkbox_class = $data->is_checked == 1 ? "checkbox-checked" : "checkbox-blank";
            $title_class = $data->is_checked == 1 ? "<span style='color:green;'> &nbsp Verified</span>" : "";
    
            $payment_title_info = $this->Payment_methods_model->getOne($data->payment_method_id);
            $vendors_invoice_info = $this->Vendors_invoice_list_model->getOne($data->task_id);
            $vendor_info = $this->Vendors_model->getOne($vendors_invoice_info->vendor_id);
    
            $status = js_anchor("<span class='$checkbox_class'></span>", ['title' => "", "data-id" => $data->id, "data-value" => $data->is_checked == 1 ? 0 : 1, "data-act" => "update-checklist-item-status-checkbox"]);
    
            $title = "<span class='font-13'>" . to_currency($data->title, $vendor_info->currency_symbol) . " , " . "Payment Date:" . $data->payment_date . " " . $files_link . " , " . $payment_title_info->title . " No:" . $data->reference_number . "," . "Payment Mode:" . $payment_title_info->title . " , " . "Description:" . $data->description . " " . $title_class . "</span>";
    
            $delete = js_anchor("<i class='fa fa-times pull-right p3'></i>", ['title' => "Delete Item", "class" => "delete", "data-id" => $data->id, "data-action-url" => "vendors_invoice_list/delete_checklist_item"]);
    
            return [
                $status,
                $title,
                $delete
            ];
        }
    
        private function _make_checklist_item_row($data)
        {
            $files_link = "";
            if ($data->files) {
                $files = unserialize($data->files);
                if (count($files)) {
                    foreach ($files as $file) {
                        $file_name = get_array_value($file, "file_name");
                        $link = " fa fa-" . get_file_icon(strtolower(pathinfo($file_name, PATHINFO_EXTENSION)));
                        $files_link .= view('notes/file_preview', ['file_name' => $file_name, 'link' => $link]); // Assuming 'notes/file_preview' is a view file
                    }
                }
            }
    
            $checkbox_class = $data->is_checked == 1 ? "checkbox-checked" : "checkbox-blank";
            $title_class = $data->is_checked == 1 ? "<span style='color:green;'> &nbsp Verified</span>" : "";
    
            $payment_title_info = $this->Payment_methods_model->getOne($data->payment_method_id);
            $vendors_invoice_info = $this->Vendors_invoice_list_model->getOne($data->task_id);
            $vendor_info = $this->Vendors_model->getOne($vendors_invoice_info->vendor_id);
    
            $status = js_anchor("<span class='$checkbox_class'></span>", ['title' => "", "data-id" => $data->id, "data-value" => $data->is_checked == 1 ? 0 : 1, "data-act" => "update-checklist-item-status-checkbox"]);
    
            $title = "<span class='font-13'>" . to_currency($data->title, $vendor_info->currency_symbol) . " , " . "Payment Date:" . $data->payment_date . " " . $files_link . " , " . $payment_title_info->title . " No:" . $data->reference_number . "," . "Payment Mode:" . $payment_title_info->title . " , " . "Description:" . $data->description . " " . $title_class . "</span>";
    
            $delete = js_anchor("<i class='fa fa-times pull-right p3'></i>", ['title' => "Delete Item", "class" => "delete", "data-id" => $data->id, "data-action-url" => "vendors_invoice_list/delete_checklist_item"]);
    
            return [
                $status,
                $title,
                $delete
            ];
        }
    
        public function update_checklist_item_status_checkbox()
        {
            $task_id = $this->request->getPost("task_id");
            $validation = \Config\Services::validation();
            $validation->setRule('task_id', 'Task ID', 'required|numeric');
            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON(["success" => false, "message" => $validation->getErrors()]);
            }
            $data = ["is_checked" => $this->request->getPost("value")];
            $save_id = $this->Vendors_invoice_payments_list_model->save($data, $this->request->getPost("id"));
            if ($save_id) {
                $item_info = $this->Vendors_invoice_payments_list_model->getOne($save_id);
                return $this->response->setJSON(["success" => true, "data" => $this->_make_checklist_item_row($item_info), 'id' => $save_id]);
            } else {
                return $this->response->setJSON(["success" => false]);
            }
        }
    
        public function delete_checklist_item()
        {
            $id = $this->request->getPost("id");
            if ($this->Vendors_invoice_payments_list_model->delete($id)) {
                return $this->response->setJSON(["success" => true, "id" => $id]);
            }
        }

        public function get_purchase_orderid()
        {
            $vendor_member = $this->request->getPost("vendor_member");
    
            $options = ["vendor_id" => $vendor_member];
            $list_data = $this->Vendors_invoice_list_model->get_details($options)->getResult();
    
            if ($list_data) {
                $loan_items = [];
                foreach ($list_data as $code) {
                    $loan_items[] = $code->purchase_order_id;
                }
                $loan_voucher_no = json_encode($loan_items);
                $loan_voucher_no = str_replace("[", "(", $loan_voucher_no);
                $loan_voucher_no = str_replace("]", ")", $loan_voucher_no);
            } else {
                $loan_voucher_no = "('empty')";
            }
    
            $itemss = $this->Vendors_invoice_list_model->get_purchase_orderid($vendor_member, $loan_voucher_no);
            $suggestions = [];
            foreach ($itemss as $items) {
                $suggestions[] = [
                    "id" => $items->id,
                    "text" => $items->purchase_no ? $items->purchase_no : get_purchase_order_id($items->id)
                ];
            }
    
            return $this->response->setJSON($suggestions);
        }
    
        public function upload_file()
        {
            // Assuming upload_file_to_temp() function is defined elsewhere
            upload_file_to_temp();
        }
    
        public function validate_vendor_file()
        {
            $file_name = $this->request->getPost("file_name");
            return validate_post_file($file_name);
        }
    }