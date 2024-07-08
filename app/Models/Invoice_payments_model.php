<?php

namespace App\Models;

use CodeIgniter\Model;

class InvoicePaymentsModel extends Model
{
    protected $table = 'invoice_payments';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'invoice_id',
        'payment_date',
        'amount',
        'payment_method_id',
    ];

    public function getDetails($options = [])
    {
        $invoicePaymentsTable = $this->table;
        $invoicesTable = $this->db->prefixTable('invoices');
        $paymentMethodsTable = $this->db->prefixTable('payment_methods');
        $clientsTable = $this->db->prefixTable('clients');

        $where = [];

        $id = $options['id'] ?? null;
        if ($id) {
            $where[] = "$invoicePaymentsTable.id = $id";
        }

        $invoiceId = $options['invoice_id'] ?? null;
        if ($invoiceId) {
            $where[] = "$invoicePaymentsTable.invoice_id = $invoiceId";
        }

        $clientId = $options['client_id'] ?? null;
        if ($clientId) {
            $where[] = "$invoicesTable.client_id = $clientId";
        }

        $projectId = $options['project_id'] ?? null;
        if ($projectId) {
            $where[] = "$invoicesTable.project_id = $projectId";
        }

        $paymentMethodId = $options['payment_method_id'] ?? null;
        if ($paymentMethodId) {
            $where[] = "$invoicePaymentsTable.payment_method_id = $paymentMethodId";
        }

        $startDate = $options['start_date'] ?? null;
        $endDate = $options['end_date'] ?? null;
        if ($startDate && $endDate) {
            $where[] = "($invoicePaymentsTable.payment_date BETWEEN '$startDate' AND '$endDate')";
        }

        $whereClause = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT $invoicePaymentsTable.*, $invoicesTable.client_id, 
                       (SELECT $clientsTable.currency_symbol FROM $clientsTable WHERE $clientsTable.id = $invoicesTable.client_id LIMIT 1) AS currency_symbol, 
                       $paymentMethodsTable.title AS payment_method_title
                FROM $invoicePaymentsTable
                LEFT JOIN $invoicesTable ON $invoicesTable.id = $invoicePaymentsTable.invoice_id
                LEFT JOIN $paymentMethodsTable ON $paymentMethodsTable.id = $invoicePaymentsTable.payment_method_id
                $whereClause
                AND $invoicePaymentsTable.deleted = 0
                AND $invoicesTable.deleted = 0";

        return $this->db->query($sql)->getResult();
    }

    public function getYearlyPaymentsChart($year)
    {
        $paymentsTable = $this->table;
        $invoicesTable = $this->db->prefixTable('invoices');

        $payments = "SELECT SUM($paymentsTable.amount) AS total, MONTH($paymentsTable.payment_date) AS month
            FROM $paymentsTable
            LEFT JOIN $invoicesTable ON $invoicesTable.id = $paymentsTable.invoice_id
            WHERE $paymentsTable.deleted = 0 
            AND YEAR($paymentsTable.payment_date) = $year 
            AND $invoicesTable.deleted = 0
            GROUP BY MONTH($paymentsTable.payment_date)";

        return $this->db->query($payments)->getResult();
    }
}
