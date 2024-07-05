<?php

namespace App\Models;

use CodeIgniter\Model;

class Estimate_payments_model extends Model
{
    protected $table = 'estimate_payments';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $builder = $this->db->table('estimate_payments');
        $builder->select('estimate_payments.*, estimates.client_id, clients.currency_symbol, payment_methods.title AS payment_method_title');
        $builder->join('estimates', 'estimates.id = estimate_payments.estimate_id', 'left');
        $builder->join('clients', 'clients.id = estimates.client_id', 'left');
        $builder->join('payment_methods', 'payment_methods.id = estimate_payments.payment_method_id', 'left');
        $builder->where('estimate_payments.deleted', 0);
        $builder->where('estimates.deleted', 0);

        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where('estimate_payments.id', $id);
        }

        $estimate_id = $options['estimate_id'] ?? null;
        if ($estimate_id) {
            $builder->where('estimate_payments.estimate_id', $estimate_id);
        }

        $client_id = $options['client_id'] ?? null;
        if ($client_id) {
            $builder->where('estimates.client_id', $client_id);
        }

        $project_id = $options['project_id'] ?? null;
        if ($project_id) {
            $builder->where('estimates.project_id', $project_id);
        }

        $payment_method_id = $options['payment_method_id'] ?? null;
        if ($payment_method_id) {
            $builder->where('estimate_payments.payment_method_id', $payment_method_id);
        }

        $start_date = $options['start_date'] ?? null;
        $end_date = $options['end_date'] ?? null;
        if ($start_date && $end_date) {
            $builder->where("estimate_payments.payment_date BETWEEN '$start_date' AND '$end_date'");
        }

        return $builder->get();
    }

    public function get_yearly_payments_chart($year)
    {
        $builder = $this->db->table('estimate_payments');
        $builder->select('SUM(estimate_payments.amount) AS total, MONTH(estimate_payments.payment_date) AS month');
        $builder->join('estimates', 'estimates.id = estimate_payments.estimate_id', 'left');
        $builder->where('estimate_payments.deleted', 0);
        $builder->where('YEAR(estimate_payments.payment_date)', $year);
        $builder->where('estimates.deleted', 0);
        $builder->groupBy('MONTH(estimate_payments.payment_date)');

        return $builder->get()->getResult();
    }
}
