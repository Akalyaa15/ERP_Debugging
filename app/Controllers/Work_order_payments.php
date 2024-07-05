<?php

namespace App\Controllers;

use App\Models\VendorsModel;
use App\Models\PaymentMethodsModel;
use App\Models\WorkOrderPaymentsModel;
use App\Models\WorkOrdersModel;
use App\Models\InvoicePaymentsModel;
use App\Models\InvoicesModel;

class WorkOrderPayments extends BaseController
{
    protected $vendorsModel;
    protected $paymentMethodsModel;
    protected $workOrderPaymentsModel;
    protected $workOrdersModel;
    protected $invoicePaymentsModel;
    protected $invoicesModel;

    public function __construct()
    {
        $this->vendorsModel = new VendorsModel();
        $this->paymentMethodsModel = new PaymentMethodsModel();
        $this->workOrderPaymentsModel = new WorkOrderPaymentsModel();
        $this->workOrdersModel = new WorkOrdersModel();
        $this->invoicePaymentsModel = new InvoicePaymentsModel();
        $this->invoicesModel = new InvoicesModel();
        $this->initPermissionChecker("work_order");
        helper(['form','url']);
    }
    public function index()
    {
        if ($this->loginUser->user_type === "staff") {
            $viewData['paymentMethodDropdown'] = $this->getPaymentMethodDropdown();
            return view("work_orders/payment_received", $viewData);
        } else {
            $viewData["vendorInfo"] = $this->vendorsModel->find($this->loginUser->vendor_id);
            $viewData['vendorId'] = $this->loginUser->vendor_id;
            $viewData['pageType'] = "full";
            return view("vendors/wo_payments/index", $viewData);
        }
    }

    public function getPaymentMethodDropdown()
    {
        $this->accessOnlyTeamMembers();

        $paymentMethods = $this->paymentMethodsModel->where("deleted", 0)->findAll();
        $paymentMethodDropdown = [["id" => "", "text" => "- " . lang("payment_methods") . " -"]];

        foreach ($paymentMethods as $method) {
            $paymentMethodDropdown[] = ["id" => $method['id'], "text" => $method['title']];
        }

        return json_encode($paymentMethodDropdown);
    }

    public function yearly()
    {
        return view("work_orders/yearly_payments");
    }

    public function custom()
    {
        return view("work_orders/custom_payments_list");
    }

    public function paymentModalForm()
    {
        $this->accessOnlyAllowedMembers();

        $rules = [
            'id' => 'numeric',
            'work_order_id' => 'numeric'
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON(['success' => false, 'message' => $this->validator->getErrors()]);
        }

        $workOrderId = $this->request->getPost('work_order_id');
        $viewData['modelInfo'] = $this->workOrderPaymentsModel->find($this->request->getPost('id'));

        if (!$workOrderId) {
            $workOrderId = $viewData['modelInfo']->work_order_id;
        }

        $viewData['paymentMethodsDropdown'] = $this->paymentMethodsModel->getDropdownList(['title'], 'id', ['online_payable' => 0, 'deleted' => 0]);
        $viewData['workOrderId'] = $workOrderId;
        $viewData["workOrderTotalSummary"] = $this->workOrdersModel->getWorkOrderTotalSummary($workOrderId);
        
        return view('work_orders/payment_modal_form', $viewData);
    }

    public function savePayment()
    {
        $this->accessOnlyAllowedMembers();
        $rules = [
            'id' => 'numeric',
            'work_order_id' => 'required|numeric',
            'work_order_payment_method_id' => 'required|numeric',
            'work_order_payment_date' => 'required',
            'work_order_payment_amount' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON(['success' => false, 'message' => $this->validator->getErrors()]);
        }

        $id = $this->request->getPost('id');
        $workOrderId = $this->request->getPost('work_order_id');
        $targetPath = get_setting("timeline_file_path");
        $filesData = move_files_from_temp_dir_to_permanent_dir($targetPath, "work_order_payment");
        $newFiles = unserialize($filesData);

        $workOrderPaymentData = [
            'work_order_id' => $workOrderId,
            'payment_date' => $this->request->getPost('work_order_payment_date'),
            'payment_method_id' => $this->request->getPost('work_order_payment_method_id'),
            'note' => $this->request->getPost('work_order_payment_note'),
            'amount' => unformat_currency($this->request->getPost('work_order_payment_amount')),
            'reference_number' => $this->request->getPost('reference_number'),
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $this->loginUser->id,
        ];

        if ($id) {
            $paymentInfo = $this->workOrderPaymentsModel->find($id);
            $timelineFilePath = get_setting("timeline_file_path");
            $newFiles = update_saved_files($timelineFilePath, $paymentInfo->files, $newFiles);
        }

        $workOrderPaymentData["files"] = serialize($newFiles);
        
        if ($workOrderPaymentData["files"] == 'a:0:{}') {
            return $this->response->setJSON(['success' => false, 'message' => '*Uploading files are required']);
        }

        $workOrderPaymentId = $this->workOrderPaymentsModel->save($workOrderPaymentData);

        if ($workOrderPaymentId) {
            $this->workOrdersModel->setWorkOrderStatusToNotPaid($workOrderId);

            if (!$id) {
                log_notification("work_order_payment_confirmation", ["work_order_payment_id" => $workOrderPaymentId, "work_order_id" => $workOrderId], "0");
            }

            $options = ["id" => $workOrderPaymentId];
            $itemInfo = $this->workOrderPaymentsModel->getDetails($options)->getRow();
            
            return $this->response->setJSON([
                'success' => true,
                'work_order_id' => $itemInfo->work_order_id,
                'data' => $this->_makePaymentRow($itemInfo),
                'work_order_total_view' => $this->_getWorkOrderTotalView($itemInfo->work_order_id),
                'id' => $workOrderPaymentId,
                'message' => lang('record_saved')
            ]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function deletePayment()
    {
        $this->accessOnlyAllowedMembers();

        $rules = [
            'id' => 'required|numeric'
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON(['success' => false, 'message' => $this->validator->getErrors()]);
        }

        $id = $this->request->getPost('id');
        
        if ($this->request->getPost('undo')) {
            if ($this->workOrderPaymentsModel->delete($id, true)) {
                $options = ["id" => $id];
                $itemInfo = $this->workOrderPaymentsModel->getDetails($options)->getRow();
                
                return $this->response->setJSON([
                    'success' => true,
                    'work_order_id' => $itemInfo->work_order_id,
                    'data' => $this->_makePaymentRow($itemInfo),
                    'work_order_total_view' => $this->_getWorkOrderTotalView($itemInfo->work_order_id),
                    'message' => lang('record_undone')
                ]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($this->workOrderPaymentsModel->delete($id)) {
                $itemInfo = $this->workOrderPaymentsModel->find($id);
                
                return $this->response->setJSON([
                    'success' => true,
                    'work_order_id' => $itemInfo->work_order_id,
                    'work_order_total_view' => $this->_getWorkOrderTotalView($itemInfo->work_order_id),
                    'message' => lang('record_deleted')
                ]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }

    public function paymentListData($workOrderId = 0)
    {
        $this->accessOnlyAllowedMembers();

        $startDate = $this->request->getPost('start_date');
        $endDate = $this->request->getPost('end_date');
        $paymentMethodId = $this->request->getPost('payment_method_id');
        $options = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'work_order_id' => $workOrderId,
            'payment_method_id' => $paymentMethodId
        ];

        $listData = $this->workOrderPaymentsModel->getDetails($options)->getResult();
        $result = [];

        foreach ($listData as $data) {
            $result[] = $this->_makePaymentRow($data);
        }

        return $this->response->setJSON(['data' => $result]);
    }

    public function paymentListDataOfVendor($vendorId = 0)
    {
        $this->accessOnlyAllowedMembersOrVendorContact($vendorId);

        $options = ['vendor_id' => $vendorId];
        $listData = $this->workOrderPaymentsModel->getDetails($options)->getResult();
        $result = [];

        foreach ($listData as $data) {
            $result[] = $this->_makePaymentRow($data);
        }

        return $this->response->setJSON(['data' => $result]);
    }

    public function paymentListDataOfProject($projectId = 0)
    {
        $options = ['project_id' => $projectId];
        $listData = $this->invoicePaymentsModel->getDetails($options)->getResult();
        $result = [];

        foreach ($listData as $data) {
            $result[] = $this->_makePaymentRow($data);
        }

        return $this->response->setJSON(['data' => $result]);
    }

    private function _makePaymentRow($data)
    {
        $this->accessOnlyAllowedMembersOrVendorContact($data->vendor_id);

        $workOrder = $this->workOrdersModel->find($data->work_order_id);
        $workOrderNoValue = $workOrder['work_no'] ? $workOrder['work_no'] : get_work_order_id($data->work_order_id);
        $workOrderNoUrl = $this->loginUser->user_type == "staff" ? 
            anchor(get_uri("work_orders/view/" . $data->work_order_id), $workOrderNoValue) : 
            anchor(get_uri("work_orders/preview/" . $data->work_order_id), $workOrderNoValue);

        $filesLink = "";
        if ($data->files) {
            $files = unserialize($data->files);
            if (count($files)) {
                foreach ($files as $file) {
                    $fileName = $file['file_name'];
                    $link = " fa fa-" . get_file_icon(strtolower(pathinfo($fileName, PATHINFO_EXTENSION)));
                    $filesLink .= anchor("#", " ", ['title' => remove_file_prefix($fileName), "data-toggle" => "modal", "data-target" => "#app-modal", "class" => "pull-left font-22 mr10 $link", "data-url" => get_uri("work_order_payments/file_preview/" . $fileName)]);
                }
            }
        }

        return [
            $workOrderNoUrl,
            $data->payment_date,
            format_to_date($data->payment_date, false),
            $data->payment_method_title,
            $data->reference_number,
            to_currency($data->amount, $data->currency_symbol),
            $filesLink,
            $data->note,
            anchor("#", "<i class='fa fa-pencil'></i>", ["class" => "edit", "title" => lang('edit_payment'), "data-id" => $data->id, "data-work_order_id" => $data->work_order_id])
            . anchor("#", "<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete'), "class" => "delete", "data-id" => $data->id, "data-url" => get_uri("work_order_payments/delete_payment"), "data-action" => "delete-confirmation"])
        ];
    }


    private function _getWorkOrderTotalView($workOrderId = 0)
    {
        $viewData['work_order_total_summary'] = $this->workOrderPaymentsModel->getWorkOrderTotalSummary($workOrderId);
        $viewData['work_order_id'] = $workOrderId;
        return view('work_orders/work_order_total_section', $viewData);
    }

    public function payInvoiceViaStripe()
    {
        $this->validate([
            'stripe_token' => 'required',
            'invoice_id' => 'required|numeric'
        ]);

        $this->accessOnlyClients();

        $invoiceId = $this->request->getPost('invoice_id');
        $methodInfo = $this->paymentMethodsModel->getOnlinePaymentMethod('stripe');

        if (!$invoiceId) {
            return redirect()->to('forbidden');
        }

        $redirectTo = "invoices/preview/$invoiceId";

        try {
            // Load Stripe library
            \Stripe\Stripe::setApiKey($methodInfo->secret_key);

            // Check payment token
            $card = $this->request->getPost('stripe_token');
            $invoiceData = (object) getInvoiceMakingData($invoiceId);
            $currency = $invoiceData->invoice_total_summary->currency;

            // Check if partial payment allowed or not
            if (get_setting("allow_partial_invoice_payment_from_clients")) {
                $paymentAmount = unformat_currency($this->request->getPost('payment_amount'));
            } else {
                $paymentAmount = $invoiceData->invoice_total_summary->balance_due;
            }

            // Validate payment amount
            if ($paymentAmount < $methodInfo->minimum_payment_amount * 1) {
                $errorMessage = lang('minimum_payment_validation_message') . " " . to_currency($methodInfo->minimum_payment_amount, $currency . " ");
                return redirect()->to($redirectTo)->with('error_message', $errorMessage);
            }

            // Prepare Stripe payment data
            $metadata = [
                "invoice_id" => $invoiceId,
                "contact_user_id" => $this->login_user->id,
                "client_id" => $invoiceData->client_info->id
            ];

            $stripeData = [
                "amount" => $paymentAmount * 100, // Convert to cents
                "currency" => $currency,
                "card" => $card,
                "metadata" => $metadata,
                "description" => get_invoice_id($invoiceId) . ", " . lang('amount') . ": " . to_currency($paymentAmount, $currency . " ")
            ];

            $charge = \Stripe\Charge::create($stripeData);

            if ($charge->paid) {
                // Payment complete, insert payment record
                $invoicePaymentData = [
                    "invoice_id" => $invoiceId,
                    "payment_date" => get_my_local_time(),
                    "payment_method_id" => $methodInfo->id,
                    "note" => $this->request->getPost('invoice_payment_note'),
                    "amount" => $paymentAmount,
                    "transaction_id" => $charge->id,
                    "created_at" => get_current_utc_time(),
                    "created_by" => $this->login_user->id,
                ];

                $invoicePaymentId = $this->invoicePaymentsModel->save($invoicePaymentData);

                if ($invoicePaymentId) {
                    // Update invoice status
                    $this->invoicesModel->setInvoiceStatusToNotPaid($invoiceId);

                    log_notification("invoice_payment_confirmation", ["invoice_payment_id" => $invoicePaymentId, "invoice_id" => $invoiceId], "0");
                    log_notification("invoice_online_payment_received", ["invoice_payment_id" => $invoicePaymentId, "invoice_id" => $invoiceId]);

                    return redirect()->to($redirectTo)->with('success_message', lang('payment_success_message'));
                } else {
                    return redirect()->to($redirectTo)->with('error_message', lang('payment_card_charged_but_system_error_message'));
                }
            } else {
                return redirect()->to($redirectTo)->with('error_message', lang('card_payment_failed_error_message'));
            }
        } catch (\Stripe\Exception\CardException | \Stripe\Exception\InvalidRequestException | \Stripe\Exception\AuthenticationException | \Stripe\Exception\ApiConnectionException | \Stripe\Exception\ApiErrorException | Exception $e) {
            $errorMessage = $e->getMessage();
            return redirect()->to($redirectTo)->with('error_message', $errorMessage);
        }
    }

    public function yearlyChart()
    {
        return view('work_orders/yearly_payments_chart');
    }

    public function yearlyChartData()
    {
        $months = ["january", "february", "march", "april", "may", "june", "july", "august", "september", "october", "november", "december"];
        $result = [];

        $year = $this->request->getPost("year");

        if ($year) {
            $payments = $this->workOrderPaymentsModel->getYearlyPaymentsChart($year);
            $values = [];

            foreach ($payments as $value) {
                $values[$value->month - 1] = $value->total; // in array the month january(1) = index(0)
            }

            foreach ($months as $key => $month) {
                $value = getArrayValue($values, $key);
                $result[] = [lang("short_" . $month), $value ? $value : 0];
            }

            return $this->response->setJSON(['data' => $result]);
        }
    }

    public function filePreview($fileName = "")
    {
        if ($fileName) {
            $viewData["file_url"] = getFileUri(get_setting("timeline_file_path") . $fileName);
            $viewData["is_image_file"] = is_image_file($fileName);
            $viewData["is_google_preview_available"] = is_google_preview_available($fileName);

            return view("notes/file_preview", $viewData);
        } else {
            return $this->response->setStatusCode(404)->setBody('File not found');
        }
    }

    public function uploadFile()
    {
        uploadFileToTemp();
    }

    public function validateWorkFile()
    {
        return validatePostFile($this->request->getPost("file_name"));
    }
}

/* End of file payments.php */
/* Location: ./application/controllers/payments.php */