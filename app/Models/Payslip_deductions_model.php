<?php

namespace App\Models;

use CodeIgniter\Model;

class Payslip_deductions_model extends Crud_model
{
    protected $table = 'payslip_deductions';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useSoftDeletes = true;

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $payslip_deductions_table = $this->table;
        $payslip_table = 'payslip';

        $where = "";
        $id = $options['id'] ?? null;
        $payslip_id = $options['payslip_id'] ?? null;

        if ($id) {
            $where .= " AND $payslip_deductions_table.id=$id";
        }

        if ($payslip_id) {
            $where .= " AND $payslip_deductions_table.payslip_id=$payslip_id";
        }

        $sql = "SELECT $payslip_deductions_table.*
                FROM $payslip_deductions_table
                LEFT JOIN $payslip_table ON $payslip_table.id=$payslip_deductions_table.payslip_id
                WHERE $payslip_deductions_table.deleted=0 $where";

        return $this->db->query($sql)->getResult();
    }
}
