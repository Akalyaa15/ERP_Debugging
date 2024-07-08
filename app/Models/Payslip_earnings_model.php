<?php

namespace App\Models;

use CodeIgniter\Model;

class Payslip_earnings_model extends Crud_model
{
    protected $table = 'payslip_earnings';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useSoftDeletes = true;

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $payslip_earnings_table = $this->table;
        $payslip_table = 'payslip'; // adjust table names as per your database
        $users_table = 'users';
        $team_member_job_info_table = 'team_member_job_info';

        $where = "";
        $id = $options['id'] ?? null;
        $user_id = $options['user_id'] ?? null;
        $payslip_id = $options['payslip_id'] ?? null;

        if ($id) {
            $where .= " AND $payslip_earnings_table.id=$id";
        }

        if ($user_id) {
            $where .= " AND $payslip_earnings_table.user_id=$user_id";
        }

        if ($payslip_id) {
            $where .= " AND $payslip_earnings_table.payslip_id=$payslip_id";
        }

        $sql = "SELECT $payslip_earnings_table.*, CONCAT($users_table.first_name, ' ', $users_table.last_name) AS user_name, $team_member_job_info_table.salary AS linked_user_name, $payslip_table.salary AS payslip_salary, $users_table.id AS payslip_user_id
                FROM $payslip_earnings_table
                LEFT JOIN $users_table ON $users_table.id = $payslip_earnings_table.user_id
                LEFT JOIN $payslip_table ON $payslip_table.id = $payslip_earnings_table.payslip_id
                LEFT JOIN $team_member_job_info_table ON $team_member_job_info_table.user_id = $payslip_earnings_table.user_id
                WHERE $payslip_earnings_table.deleted=0 $where";

        return $this->db->query($sql)->getResult();
    }

    public function insert($data)
    {
        $this->db->table('payslip_earnings')->insertBatch($data);
    }
}
