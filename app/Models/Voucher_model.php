=<?php

namespace App\Models;

use CodeIgniter\Model;

class Voucher_model extends Model
{
    protected $table = 'voucher';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    protected $allowedFields = ['voucher_no', 'created_user_id', 'line_manager', 'estimate_date', 'status', 'payment_method_id', 'voucher_type_id'];

    public function get_details($options = [])
    {
        $estimates_table = $this->table;
        $clients_table = $this->db->prefixTable('users');
        $payment_method_table = $this->db->prefixTable('payment_methods');
        $voucher_types_table = $this->db->prefixTable('voucher_types');
        $expenses_table = $this->db->prefixTable('voucher_expenses');

        $where = "";
        $id = $options['id'] ?? null;
        if ($id) {
            $where .= " AND $estimates_table.id=$id";
        }

        $created_user_id = $options['created_user_id'] ?? null;
        if ($created_user_id) {
            $where .= " AND $estimates_table.created_user_id=$created_user_id";
        }

        $created_by = $options['created_by'] ?? null;
        if ($created_by) {
            $where .= " AND $estimates_table.created_user_id!=$created_by";
        }

        $line_manager = $options['line_manager'] ?? null;
        if ($line_manager) {
            $where .= " AND $estimates_table.line_manager=$line_manager";
        }

        $line_manager_admin = $options['line_manager_admin'] ?? null;
        if ($line_manager_admin) {
            $where .= " AND ($estimates_table.line_manager=$line_manager_admin OR $estimates_table.line_manager='admin' )AND $estimates_table.created_user_id!=$line_manager_admin";
        }

        $is_accountant = $options['is_accountant'] ?? null;
        if ($is_accountant) {
            $where .= " AND FIND_IN_SET($estimates_table.status, 'verified_by_manager,approved_by_accounts,rejected_by_accounts')";
        }

        $start_date = $options['start_date'] ?? null;
        $end_date = $options['end_date'] ?? null;
        if ($start_date && $end_date) {
            $where .= " AND ($estimates_table.estimate_date BETWEEN '$start_date' AND '$end_date') ";
        }

        $team_member = $options['team_member'] ?? null;
        if ($team_member) {
            $where .= " AND ($expenses_table.user_id=$team_member OR FIND_IN_SET('$team_member', $expenses_table.r_user_id))";
        }

        $line_manager_dropdown = $options['line_manager_dropdown'] ?? null;
        if ($line_manager_dropdown) {
            $where .= " AND ($estimates_table.line_manager=$line_manager_dropdown)";
        }

        $status = $options['status'] ?? null;
        if ($status) {
            $where .= " AND $estimates_table.status='$status'";
        }

        $exclude_draft = $options['exclude_draft'] ?? null;
        if ($exclude_draft) {
            $where .= " AND $estimates_table.status!='draft' ";
        }

        // Prepare custom field binding query
        $custom_fields = $options['custom_fields'] ?? null;
        $custom_field_query_info = $this->prepareCustomFieldQueryString("delivery", $custom_fields, $estimates_table);
        $select_custom_fields = $custom_field_query_info['select_string'];
        $join_custom_fields = $custom_field_query_info['join_string'];

        $sql = "SELECT $estimates_table.*, $payment_method_table.title as title, $voucher_types_table.title as type_title          
        FROM $estimates_table
        LEFT JOIN $payment_method_table ON $payment_method_table.id= $estimates_table.payment_method_id
        LEFT JOIN $voucher_types_table ON $voucher_types_table.id= $estimates_table.voucher_type_id
        LEFT JOIN $expenses_table ON $expenses_table.estimate_id= $estimates_table.id
        WHERE $estimates_table.deleted=0 $where";

        return $this->db->query($sql)->getResult();
    }

    public function is_estimate_no_exists($voucher_no, $id = 0)
    {
        $query = $this->where(['voucher_no' => $voucher_no, 'deleted' => 0])->findAll();
        if (!empty($query) && $query[0]->id != $id) {
            return $query[0];
        } else {
            return false;
        }
    }

    public function get_last_estimate_id_exists()
    {
        return $this->orderBy('id', 'DESC')->first();
    }
}
