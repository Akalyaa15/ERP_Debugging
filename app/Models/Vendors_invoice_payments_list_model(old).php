<?php

namespace App\Models;

use CodeIgniter\Model;

class VendorsInvoicePaymentsListModel extends CrudModel
{
    protected $table = 'vendors_invoice_payments_list';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'task_id', 'title', 'payment_date', 'payment_method_id', 'deleted'
    ];
    protected $returnType = 'object';

    public function getDetails($options = [])
    {
        $checklistItemsTable = $this->db->prefixTable('vendors_invoice_payments_list');

        $where = "";

        $task_id = get_array_value($options, "task_id");
        if ($task_id) {
            $where .= " AND $checklistItemsTable.task_id = " . $this->db->escape($task_id);
        }

        $sql = "SELECT $checklistItemsTable.*, 
                       IF($checklistItemsTable.sort != 0, $checklistItemsTable.sort, $checklistItemsTable.id) AS new_sort
                FROM $checklistItemsTable
                WHERE $checklistItemsTable.deleted = 0 $where
                ORDER BY new_sort ASC";

        return $this->db->query($sql)->getResult();
    }
}
