<?php

namespace App\Controllers;

use App\Models\EmailTemplatesModel;
use CodeIgniter\Controller;

class Email_templates extends Controller
{
    protected $emailTemplatesModel;

    public function __construct()
    {
        $this->emailTemplatesModel = new EmailTemplatesModel(); // Load the model
        $this->accessOnlyAdmin(); // Updated method name to follow CI4 conventions
    }

    private function _templates()
    {
        return [
            "login_info" => ["USER_FIRST_NAME", "USER_LAST_NAME", "DASHBOARD_URL", "USER_LOGIN_EMAIL", "USER_LOGIN_PASSWORD", "LOGO_URL", "SIGNATURE"],
            "reset_password" => ["ACCOUNT_HOLDER_NAME", "RESET_PASSWORD_URL", "SITE_URL", "LOGO_URL", "SIGNATURE"],
            "team_member_invitation" => ["INVITATION_SENT_BY", "INVITATION_URL", "SITE_URL", "LOGO_URL", "SIGNATURE"],
            "client_contact_invitation" => ["INVITATION_SENT_BY", "INVITATION_URL", "SITE_URL", "LOGO_URL", "SIGNATURE"],
            "vendor_contact_invitation" => ["INVITATION_SENT_BY", "INVITATION_URL", "SITE_URL", "LOGO_URL", "SIGNATURE"],
            "send_invoice" => ["INVOICE_ID", "CONTACT_FIRST_NAME", "CONTACT_LAST_NAME", "PROJECT_TITLE", "BALANCE_DUE", "DUE_DATE", "SIGNATURE", "INVOICE_URL", "LOGO_URL"],
            "invoice_payment_confirmation" => ["INVOICE_ID", "PAYMENT_AMOUNT", "INVOICE_URL", "LOGO_URL", "SIGNATURE"],
            "invoice_due_reminder_before_due_date" => ["INVOICE_ID", "CONTACT_FIRST_NAME", "CONTACT_LAST_NAME", "PROJECT_TITLE", "BALANCE_DUE", "DUE_DATE", "SIGNATURE", "INVOICE_URL", "LOGO_URL"],
            "invoice_overdue_reminder" => ["INVOICE_ID", "CONTACT_FIRST_NAME", "CONTACT_LAST_NAME", "PROJECT_TITLE", "BALANCE_DUE", "DUE_DATE", "SIGNATURE", "INVOICE_URL", "LOGO_URL"],
            "recurring_invoice_creation_reminder" => ["CONTACT_FIRST_NAME", "CONTACT_LAST_NAME", "APP_TITLE", "INVOICE_URL", "NEXT_RECURRING_DATE", "LOGO_URL", "SIGNATURE"],
            "ticket_created" => ["TICKET_ID", "TICKET_TITLE", "USER_NAME", "TICKET_CONTENT", "TICKET_URL", "LOGO_URL", "SIGNATURE"],
            "ticket_commented" => ["TICKET_ID", "TICKET_TITLE", "USER_NAME", "TICKET_CONTENT", "TICKET_URL", "LOGO_URL", "SIGNATURE"],
            "ticket_closed" => ["TICKET_ID", "TICKET_TITLE", "USER_NAME", "TICKET_URL", "LOGO_URL", "SIGNATURE"],
            "ticket_reopened" => ["TICKET_ID", "TICKET_TITLE", "USER_NAME", "TICKET_URL", "SIGNATURE", "LOGO_URL"],
            "general_notification" => ["EVENT_TITLE", "EVENT_DETAILS", "APP_TITLE", "COMPANY_NAME", "NOTIFICATION_URL", "LOGO_URL", "SIGNATURE"],
            "message_received" => ["SUBJECT", "USER_NAME", "MESSAGE_CONTENT", "MESSAGE_URL", "APP_TITLE", "LOGO_URL", "SIGNATURE"],
            "purchase_order_due_reminder_before_due_date" => ["PURCHASE_ORDER_ID", "DUE_DATE", "SIGNATURE", "PURCHASE_ORDER_URL", "LOGO_URL"],
            "purchase_order_overdue_reminder" => ["PURCHASE_ORDER_ID", "DUE_DATE", "SIGNATURE", "PURCHASE_ORDER_URL", "LOGO_URL"],
            "company_contact_invitation" => ["INVITATION_SENT_BY", "INVITATION_URL", "SITE_URL", "LOGO_URL", "SIGNATURE"],
            "signature" => []
        ];
    }

    public function index()
    {
        return view('email_templates/index'); // Updated view method for rendering views
    }

    public function save()
    {
        // Validate incoming data (if not using form validation service)
        $this->validate([
            'id' => 'required|numeric'
        ]);

        $id = $this->request->getPost('id');

        $data = [
            "email_subject" => $this->request->getPost('email_subject'),
            "custom_message" => decode_ajax_post_data($this->request->getPost('custom_message'))
        ];

        $save_id = $this->emailTemplatesModel->save($data, $id);
        if ($save_id) {
            return $this->response->setJSON(['success' => true, 'id' => $save_id, 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function restore_to_default()
    {
        // Validate incoming data (if not using form validation service)
        $this->validate([
            'id' => 'required|numeric'
        ]);

        $template_id = $this->request->getPost('id');

        $data = [
            "custom_message" => ""
        ];

        $save_id = $this->emailTemplatesModel->save($data, $template_id);
        if ($save_id) {
            $default_message = $this->emailTemplatesModel->getOne($save_id)->default_message;
            return $this->response->setJSON(['success' => true, 'data' => $default_message, 'message' => lang('template_restored')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function list_data()
    {
        $list = [];
        foreach ($this->_templates() as $template_name => $variables) {
            $list[] = ["<span class='template-row' data-name='$template_name'>" . lang($template_name) . "</span>"];
        }
        return $this->response->setJSON(['data' => $list]);
    }

    public function form($template_name = "")
    {
        $view_data['model_info'] = $this->emailTemplatesModel->getOneWhere(["template_name" => $template_name]);
        $variables = array_key_exists($template_name, $this->_templates()) ? $this->_templates()[$template_name] : [];
        $view_data['variables'] = $variables;
        return view('email_templates/form', $view_data);
    }
}
