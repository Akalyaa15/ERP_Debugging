<?php

namespace App\Models;

use CodeIgniter\Model;

class PayslipEarningsAddModel extends Model
{
    protected $table = 'payslip_earningsadd';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    public function __construct()
    {
        parent::__construct();
    }

    public function getDetails($options = [])
    {
        $builder = $this->db->table($this->table);
        $builder->select("$this->table.*");
        $builder->join('payslip', "payslip.id = $this->table.payslip_id", 'left');

        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where("$this->table.id", $id);
        }

        $payslip_id = $options['payslip_id'] ?? null;
        if ($payslip_id) {
            $builder->where("$this->table.payslip_id", $payslip_id);
        }

        $builder->where("$this->table.deleted", 0);

        return $builder->get()->getResult();
    }
}
