<?php

namespace App\Models;

use CodeIgniter\Model;

class Payslip_attendance_model extends Crud_model
{
    protected $table = 'payslip_attendance';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['user_id', 'payslip_id', 'attendance'];

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $payslip_attendance_table = $this->table;
        $payslip_table = 'payslip'; // adjust table names as per your database
        $users_table = 'users'; // adjust table names as per your database
        $leave_applications_table = 'leave_applications'; // adjust table names as per your database
        $leave_types_table = 'leave_types'; // adjust table names as per your database
        $where = "";

        $id = $options['id'] ?? null;
        $payslip_id = $options['payslip_id'] ?? null;
        $user_id = $options['user_id'] ?? null;

        if ($id) {
            $where .= " AND $payslip_attendance_table.id=$id";
        }

        if ($payslip_id) {
            $where .= " AND $payslip_attendance_table.payslip_id=$payslip_id";
        }

        if ($user_id) {
            $where .= " AND $payslip_attendance_table.user_id=$user_id";
        }

        // Fetch payslip date from payslip table
        $pd = $options['payslip_id'] ?? null;
        if ($pd) {
            $item_sql = "SELECT payslip_date FROM $payslip_table WHERE id = ?";
            $items = $this->db->query($item_sql, [$pd])->getRow();

            if ($items) {
                $date = $items->payslip_date;
                $currentMonth = date('Y-m-d', strtotime("$date first day of previous month"));
                $last = date('m', strtotime($currentMonth));
                $year = date('Y', strtotime($currentMonth));

                $start_date = $this->db->protect($leave_applications_table . '.start_date');
                $end_date = $this->db->protect($leave_applications_table . '.end_date');

                $where .= " AND $leave_applications_table.status='approved'";
                $where .= " AND $leave_applications_table.deleted=0";
                $where .= " AND ($start_date <= '$year-$last-31' AND $end_date >= '$year-$last-01')";
            }
        }

        $sql = "SELECT $payslip_attendance_table.*, 
                       $leave_applications_table.total_days AS attendance_user_name,
                       $leave_applications_table.start_date AS leave_start_user_name,
                       $leave_applications_table.end_date AS leave_end_user_name,
                       $leave_types_table.title AS leave_type_id_name
                FROM $payslip_attendance_table
                LEFT JOIN $payslip_table ON $payslip_table.id = $payslip_attendance_table.payslip_id
                LEFT JOIN $leave_applications_table ON $leave_applications_table.applicant_id = $payslip_attendance_table.user_id
                LEFT JOIN $leave_types_table ON $leave_types_table.id = $leave_applications_table.leave_type_id
                WHERE $payslip_attendance_table.deleted=0 $where";

        return $this->db->query($sql)->getResult();
    }

    public function insertAttendance($data)
    {
        $this->insertBatch($data);
    }
}
