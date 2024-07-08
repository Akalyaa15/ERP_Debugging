<?php

namespace App\Models;

use CodeIgniter\Model;

class ExcelImportModel extends Model
{
    protected $table = 'bank_statement';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['ValueName', 'PostDate', 'TransactionId', 'RemitterBranch', 'Description', 'ChequeNo', 'CreditAmount', 'DebitAmount', 'Balance', 'account_number', 'BankName'];
    protected $useTimestamps = false;

    public function getDetails($options = [])
    {
        $builder = $this->builder();

        $id = $options['id'] ?? null;
        $ValueName = $options['ValueName'] ?? null;
        $PostDate = $options['PostDate'] ?? null;
        $TransactionId = $options['TransactionId'] ?? null;
        $RemitterBranch = $options['RemitterBranch'] ?? null;
        $Description = $options['Description'] ?? null;
        $ChequeNo = $options['ChequeNo'] ?? null;
        $CreditAmount = $options['CreditAmount'] ?? null;
        $DebitAmount = $options['DebitAmount'] ?? null;
        $Balance = $options['Balance'] ?? null;
        $account_number = $options['account_number'] ?? null;
        $BankName = $options['BankName'] ?? null;

        if ($id) {
            $builder->where('id', $id);
        }
        if ($ValueName) {
            $builder->where('ValueName', $ValueName);
        }
        if ($PostDate) {
            $builder->where('PostDate', $PostDate);
        }
        if ($TransactionId) {
            $builder->where('TransactionId', $TransactionId);
        }
        if ($RemitterBranch) {
            $builder->where('RemitterBranch', $RemitterBranch);
        }
        if ($Description) {
            $builder->where('Description', $Description);
        }
        if ($ChequeNo) {
            $builder->where('ChequeNo', $ChequeNo);
        }
        if ($CreditAmount) {
            $builder->where('CreditAmount', $CreditAmount);
        }
        if ($DebitAmount) {
            $builder->where('DebitAmount', $DebitAmount);
        }
        if ($Balance) {
            $builder->where('Balance', $Balance);
        }
        if ($BankName) {
            $builder->where('BankName', $BankName);
        }
        if ($account_number) {
            $builder->where('account_number', $account_number);
        }

        $start_date = $options['start_date'] ?? null;
        $end_date = $options['end_date'] ?? null;
        if ($start_date && $end_date) {
            $builder->where("ValueName BETWEEN '$start_date' AND '$end_date'");
        }

        $builder->where('deleted', 0);
        $query = $builder->get();
        return $query->getResultArray();
    }

    public function selectAll()
    {
        return $this->orderBy('id', 'DESC')->findAll();
    }

    public function insertBatch($data)
    {
        $this->insertBatch($data);
    }
}
