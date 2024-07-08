<?php

namespace App\Models;

use CodeIgniter\Model;

class Notification_settings_model extends Model
{
    protected $table = 'notification_settings';

    public function __construct()
    {
        parent::__construct();
    }

    public function notifyToTerms()
    {
        return [
            "team_members", "team", "project_members", "client_primary_contact", "client_all_contacts", "task_assignee", "task_collaborators", "comment_creator", "cusomer_feedback_creator", "leave_applicant", "ticket_creator", "ticket_assignee", "estimate_request_assignee", "recipient", "mentioned_members", "employee_payslip_applicant", "group_members", "group_comment_creator", "voucher_application_approved_by_manager", "voucher_application_submitted", "voucher_application_approved_by_accounts", "voucher_application_rejected_by_manager", "voucher_application_rejected_by_accounts", "account_department", "voucher_application_paid", "voucher_application_resubmitted", "line_manager", "voucher_creater", "leave_manager", "leave_alternate"
        ];
    }

    public function getDetails($options = [])
    {
        $notification_settings_table = $this->table;
        $users_table = 'users';
        $team_table = 'team';

        $where = [];
        $id = $options['id'] ?? null;
        if ($id) {
            $where[] = "$notification_settings_table.id = $id";
        }

        $category = $options['category'] ?? null;
        if ($category) {
            $where[] = "$notification_settings_table.category = '$category'";
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT $notification_settings_table.*, 
                (SELECT GROUP_CONCAT(' ', $users_table.first_name, ' ', $users_table.last_name) FROM $users_table WHERE FIND_IN_SET($users_table.id, $notification_settings_table.notify_to_team_members)) as team_members_list,
                (SELECT GROUP_CONCAT(' ', $team_table.title) FROM $team_table WHERE FIND_IN_SET($team_table.id, $notification_settings_table.notify_to_team)) as team_list
                FROM $notification_settings_table
                WHERE $notification_settings_table.deleted = 0 AND $whereClause
                ORDER BY $notification_settings_table.sort ASC";

        return $this->db->query($sql)->getResultArray();
    }
}
