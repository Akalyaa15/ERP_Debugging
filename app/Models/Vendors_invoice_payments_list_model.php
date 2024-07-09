<?php

namespace App\Models;

use CodeIgniter\Model;

class VendorsInvoicePaymentsListModel extends Model
{
    protected $table = 'vendors_invoice_payments_list';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'task_id', 'payment_method_id', 'title', 'payment_date', 'sort', 'deleted'
    ];
    protected $returnType = 'object';

    public function getDetails($options = [])
    {
        $checklistItemsTable = $this->db->prefixTable('vendors_invoice_payments_list');
        $paymentTable = $this->db->prefixTable('payment_methods');

        $where = "";

        if (!empty($options['task_id'])) {
            $where .= " AND $checklistItemsTable.task_id=" . $this->db->escape($options['task_id']);
        }

        $sql = "SELECT $checklistItemsTable.*, 
                       IF($checklistItemsTable.sort != 0, $checklistItemsTable.sort, $checklistItemsTable.id) AS new_sort,
                       $paymentTable.title AS vendor_payment_name
                FROM $checklistItemsTable
                LEFT JOIN $paymentTable ON $paymentTable.id = $checklistItemsTable.payment_method_id 
                WHERE $checklistItemsTable.deleted = 0 $where
                ORDER BY new_sort ASC";
                
        return $this->db->query($sql)->getResult();
    }

    public function getYearlyPaymentsChart($year)
    {
        $paymentsTable = $this->db->prefixTable('vendors_invoice_payments_list');
        $purchaseOrdersTable = $this->db->prefixTable('vendors_invoice_list');

        $sql = "SELECT SUM($paymentsTable.title) AS total, 
                       MONTH($paymentsTable.payment_date) AS month
                FROM $paymentsTable
                LEFT JOIN $purchaseOrdersTable ON $purchaseOrdersTable.id = $paymentsTable.task_id
                WHERE $paymentsTable.deleted = 0 
                  AND YEAR($paymentsTable.payment_date) = " . $this->db->escape($year) . "
                  AND $purchaseOrdersTable.deleted = 0
                GROUP BY MONTH($paymentsTable.payment_date)";
                
        return $this->db->query($sql)->getResult();
    }
}
