<?php

namespace App\Models;

use CodeIgniter\Model;

class LoanPaymentsListModel extends Model
{
    protected $table = 'loan_payments_list';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['loan_id', 'payment_method_id', 'amount', 'description', 'created_at', 'deleted'];
    protected $returnType = 'array';

    public function getDetails($options = [])
    {
        $builder = $this->builder();
        $builder->select("$this->table.*, IF($this->table.sort != 0, $this->table.sort, $this->table.id) AS new_sort, payment_methods.title AS loan_payment_name");
        $builder->join('payment_methods', "payment_methods.id = $this->table.payment_method_id", 'left');

        $loanId = $options['loan_id'] ?? null;
        if ($loanId) {
            $builder->where("$this->table.loan_id", $loanId);
        }

        $builder->where("$this->table.deleted", 0);
        $builder->orderBy('new_sort', 'ASC');
        $query = $builder->get();
        return $query->getResultArray();
    }

    public function getVendorsInvoicePaidAmountSuggestion($loanId = null)
    {
        $builder = $this->builder();
        $builder->selectSum('title', 'paid');
        $builder->where("$this->table.deleted", 0);
        $builder->where("$this->table.loan_id", $loanId);
        $query = $builder->get();
        return $query->getRow();
    }
}
