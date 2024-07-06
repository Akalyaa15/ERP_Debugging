<?php

namespace App\Models;

use CodeIgniter\Model;

class PayslipPaymentsModel extends Model
{
    protected $table = 'payslip_payments';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    public function __construct()
    {
        parent::__construct();
    }

    public function getDetails($options = [])
    {
        $builder = $this->db->table($this->table);
        $builder->select("$this->table.*, payslip.user_id, payment_methods.title AS payment_method_title, CONCAT(users.first_name, ' ', users.last_name) AS linked_user_name, users.image AS user_id_avatar");
        $builder->join('payslip', "payslip.id = $this->table.payslip_id AND payslip.deleted = 0", 'left');
        $builder->join('payment_methods', "payment_methods.id = $this->table.payment_method_id", 'left');
        $builder->join('users', "users.id = payslip.user_id", 'left');

        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where("$this->table.id", $id);
        }

        $voucher_no = $options['voucher_no'] ?? null;
        if ($voucher_no) {
            $builder->where("$this->table.voucher_no", $voucher_no);
        }

        $payslip_id = $options['payslip_id'] ?? null;
        if ($payslip_id) {
            $builder->where("$this->table.payslip_id", $payslip_id);
        }

        $user_id = $options['user_id'] ?? null;
        if ($user_id) {
            $builder->where("payslip.user_id", $user_id);
        }

        $payment_method_id = $options['payment_method_id'] ?? null;
        if ($payment_method_id) {
            $builder->where("$this->table.payment_method_id", $payment_method_id);
        }

        $start_date = $options['start_date'] ?? null;
        $end_date = $options['end_date'] ?? null;
        if ($start_date && $end_date) {
            $builder->where("$this->table.payment_date BETWEEN '$start_date' AND '$end_date'");
        }

        $builder->where("$this->table.deleted", 0);
        $builder->where("payslip.deleted", 0);

        return $builder->get()->getResult();
    }

    public function getYearlyPaymentsChart($year)
    {
        $builder = $this->db->table($this->table);
        $builder->select("SUM($this->table.amount) AS total, MONTH($this->table.payment_date) AS month");
        $builder->join('payslip', "payslip.id = $this->table.payslip_id", 'left');
        $builder->where("$this->table.deleted", 0);
        $builder->where("YEAR($this->table.payment_date)", $year);
        $builder->where("payslip.deleted", 0);
        $builder->groupBy("MONTH($this->table.payment_date)");

        return $builder->get()->getResult();
    }
}
