<?php

namespace App\Controllers;

class Paypal_ipn extends BaseController {

    protected $invoicePaymentsModel;
    protected $invoicesModel;
    
    public function __construct() {
        // No need to call parent::__construct() if not extending Pre_loader
        $this->invoicePaymentsModel = new \App\Models\InvoicePaymentsModel(); // Adjust namespace as per your model
        $this->invoicesModel = new \App\Models\InvoicesModel(); // Adjust namespace as per your model
    }

    public function index() {
        helper(['array', 'date']);

        $paypal = new \App\Libraries\Paypal(); // Adjust namespace as per your library

        // Process IPN
        if ($paypal->isValidIpn()) {
            // IPN is valid. Update the invoice payment in the database

            $custom = $this->request->getPost('custom');

            $customArray = [];
            foreach (explode(";", $custom) as $subValues) {
                $subValue = explode(":", $subValues);
                if (count($subValue) == 2) {
                    $customArray[$subValue[0]] = $subValue[1];
                }
            }

            // Set login user id = contact id for future processing
            $loginUser = new \stdClass();
            $loginUser->id = get_array_value($customArray, "contact_user_id");
            $loginUser->user_type = "client"; // Assuming user_type is set

            $invoiceId = get_array_value($customArray, "invoice_id");

            // Payment complete, insert payment record
            $invoicePaymentData = [
                "invoice_id" => $invoiceId,
                "payment_date" => date('Y-m-d H:i:s'), // Adjust date format as per your requirement
                "payment_method_id" => get_array_value($customArray, "payment_method_id"),
                "note" => "",
                "amount" => $this->request->getPost('mc_gross'),
                "transaction_id" => $this->request->getPost('txn_id'),
                "created_at" => date('Y-m-d H:i:s'), // Adjust date format as per your requirement
                "created_by" => $loginUser->id,
            ];

            $invoicePaymentId = $this->invoicePaymentsModel->save($invoicePaymentData);

            if ($invoicePaymentId) {
                // Update invoice status
                $this->invoicesModel->setInvoiceStatusToNotPaid($invoiceId);

                log_notification("invoice_payment_confirmation", ["invoice_payment_id" => $invoicePaymentId, "invoice_id" => $invoiceId], "0");
                log_notification("invoice_online_payment_received", ["invoice_payment_id" => $invoicePaymentId, "invoice_id" => $invoiceId], $loginUser->id);
                // Database updated successfully
            }
        }
    }
}

