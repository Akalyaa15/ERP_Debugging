<?php

namespace App\Controllers;

use App\Models\NotificationsModel;
use App\Models\ActivityLogsModel;
use App\Models\InvoicesModel;
use App\Models\PurchaseOrdersModel;

/*
 * To process the notifications we'll use this.
 * This controller will be called via curl 
 * 
 * Purpose of this process is to reduce the processing time in main thread.
 * 
 */

class NotificationProcessor extends BaseController {
    protected $notificationsModel;
    protected $activityLogsModel;
    protected $invoicesModel;
    protected $purchaseOrdersModel;

    public function __construct() {
        $this->notificationsModel = new NotificationsModel();
        $this->activityLogsModel = new ActivityLogsModel();
        $this->invoicesModel = new InvoicesModel();
        $this->purchaseOrdersModel = new PurchaseOrdersModel();
        helper('notifications');
    }

    // Don't show anything here
    public function index() {
        return redirect()->to('forbidden');
    }

    public function create_notification() {
        ini_set('max_execution_time', 300); // 300 seconds
        // error_log(date('[Y-m-d H:i:s e] ') . " Process Notification: " . serialize($_POST) . PHP_EOL, 3, "error.log");
        
        // Validate notification request
        $event = decode_id($this->request->getPost('event'), 'notification');

        if (!$event) {
            die('Access Denied!');
        }

        $notification_data = get_notification_config($event);

        if (!is_array($notification_data)) {
            die('Access Denied!!');
        }

        $user_id = $this->request->getPost('user_id');
        $activity_log_id = $this->request->getPost('activity_log_id');

        $options = [
            'project_id' => $this->request->getPost('project_id'),
            'task_id' => $this->request->getPost('task_id'),
            'project_comment_id' => $this->request->getPost('project_comment_id'),
            'ticket_id' => $this->request->getPost('ticket_id'),
            'ticket_comment_id' => $this->request->getPost('ticket_comment_id'),
            'project_file_id' => $this->request->getPost('project_file_id'),
            'leave_id' => $this->request->getPost('leave_id'),
            'post_id' => $this->request->getPost('post_id'),
            'to_user_id' => $this->request->getPost('to_user_id'),
            'activity_log_id' => $this->request->getPost('activity_log_id'),
            'client_id' => $this->request->getPost('client_id'),
            'invoice_payment_id' => $this->request->getPost('invoice_payment_id'),
            'invoice_id' => $this->request->getPost('invoice_id'),
            'estimate_id' => $this->request->getPost('estimate_id'),
            'estimate_request_id' => $this->request->getPost('estimate_request_id'),
            'actual_message_id' => $this->request->getPost('actual_message_id'),
            'parent_message_id' => $this->request->getPost('parent_message_id'),
            'event_id' => $this->request->getPost('event_id'),
            'announcement_id' => $this->request->getPost('announcement_id'),
            'payslip_id' => $this->request->getPost('payslip_id'),
            'voucher_id' => $this->request->getPost('voucher_id'),
            'dc_id' => $this->request->getPost('dc_id'),
            'purchase_order_id' => $this->request->getPost('purchase_order_id'),
            'group_id' => $this->request->getPost('group_id'),
            'group_comment_id' => $this->request->getPost('group_comment_id')
        ];

        // Classify the task modification parts
        if ($event == 'project_task_updated') {
            $this->_classified_task_modification($event, $options, $activity_log_id); // Overwrite event and options
        }

        // Save reminder date
        $this->_save_reminder_date($event, $options);

        // Save purchase order reminder date
        $this->_save_purchase_order_reminder_date($event, $options);
        
        // error_log("announcement_id: " . $options["announcement_id"] . PHP_EOL, 3, "notification.txt");
        // error_log("announcement_share_with: " . $options["announcement_share_with"] . PHP_EOL, 3, "notification.txt");

        $this->notificationsModel->create_notification($event, $user_id, $options);
    }

    private function _classified_task_modification(&$event, &$options, $activity_log_id = 0) {
        // Find out what types of changes have been made
        if ($activity_log_id) {
            $activity = $this->activityLogsModel->find($activity_log_id);
            if ($activity && $activity->changes) {
                $changes = unserialize($activity->changes);

                // Only changed assigned_to field?
                if (is_array($changes) && count($changes) == 1 && isset($changes['assigned_to'])) {
                    $event = 'project_task_assigned';
                    $assigned_to = $changes['assigned_to'];
                    $new_assigned_to = $assigned_to['to'];

                    $options['to_user_id'] = $new_assigned_to;
                    $options['activity_log_id'] = ''; // Remove activity log id
                }

                // Only changed status field? Find out the change event
                if (is_array($changes) && count($changes) == 1 && isset($changes['status'])) {
                    $status = $changes['status'];
                    $new_status = $status['to'];

                    if ($new_status == '1') {
                        $event = 'project_task_reopened';
                    } else if ($new_status == '3') {
                        $event = 'project_task_finished';
                    } else {
                        $event = 'project_task_started';
                    }
                    $options['activity_log_id'] = ''; // Remove activity log id
                }
            }
        }
    }

    // To prevent multiple reminders, we'll save the reminder date
    private function _save_reminder_date(&$event, &$options) {
        // Save invoices reminder dates 
        $invoice_id = $options['invoice_id'];
        if ($invoice_id) {
            $invoice_reminder_date = [];
            if ($event == 'invoice_due_reminder_before_due_date' || $event == 'invoice_overdue_reminder') {
                $invoice_reminder_date['due_reminder_date'] = get_my_local_time();
            }
            if ($event == 'recurring_invoice_creation_reminder') {
                $invoice_reminder_date['recurring_reminder_date'] = get_my_local_time();
            }
            if (count($invoice_reminder_date)) {
                $this->invoicesModel->update($invoice_id, $invoice_reminder_date);
            }
        }
    }

    // To prevent multiple reminders, we'll save the reminder date
    private function _save_purchase_order_reminder_date(&$event, &$options) {
        // Save purchase orders reminder dates 
        $purchase_order_id = $options['purchase_order_id'];
        if ($purchase_order_id) {
            $purchase_order_reminder_date = [];
            if ($event == 'purchase_order_due_reminder_before_due_date' || $event == 'purchase_order_overdue_reminder') {
                $purchase_order_reminder_date['due_reminder_date'] = get_my_local_time();
            }
            
            if (count($purchase_order_reminder_date)) {
                $this->purchaseOrdersModel->update($purchase_order_id, $purchase_order_reminder_date);
            }
        }
    }
}

/* End of file NotificationProcessor.php */
/* Location: ./app/Controllers/NotificationProcessor.php */
