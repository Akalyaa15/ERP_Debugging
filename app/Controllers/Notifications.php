<?php

namespace App\Controllers;

use App\Models\NotificationsModel;
use App\Models\UsersModel;

class Notifications extends BaseController {
    protected $notificationsModel;
    protected $usersModel;

    public function __construct() {
        $this->notificationsModel = new NotificationsModel();
        $this->usersModel = new UsersModel();
        helper('notifications');
    }

    // Load notifications view
    public function index() {
        $view_data = $this->_prepare_notification_list();
        return view('notifications/index', $view_data);
    }

    public function load_more($offset = 0) {
        $view_data = $this->_prepare_notification_list($offset);
        return view('notifications/list_data', $view_data);
    }

    public function count_notifications() {
        $notifications = $this->notificationsModel->count_notifications($this->session->get('user_id'), $this->session->get('notification_checked_at'));
        echo json_encode(array('success' => true, 'total_notifications' => $notifications));
    }

    public function get_notifications() {
        $view_data = $this->_prepare_notification_list();
        $view_data['result_remaining'] = false; // Don't show load more option in notification pop-up
        echo json_encode(array('success' => true, 'notification_list' => view('notifications/list', $view_data)));
    }

    public function update_notification_checking_status() {
        $now = date('Y-m-d H:i:s');
        $data = array('notification_checked_at' => $now);
        $this->usersModel->update($this->session->get('user_id'), $data);
    }

    public function set_notification_status_as_read($notification_id = 0) {
        if ($notification_id) {
            $this->notificationsModel->set_notification_status_as_read($notification_id, $this->session->get('user_id'));
        }
    }

    private function _prepare_notification_list($offset = 0) {
        $notifications = $this->notificationsModel->get_notifications($this->session->get('user_id'), $offset);
        $view_data['notifications'] = $notifications['result'];
        $view_data['found_rows'] = $notifications['found_rows'];
        $next_page_offset = $offset + 20;
        $view_data['next_page_offset'] = $next_page_offset;
        $view_data['result_remaining'] = $notifications['found_rows'] > $next_page_offset;
        return $view_data;
    }
}

/* End of file Notifications.php */
/* Location: ./app/Controllers/Notifications.php */
