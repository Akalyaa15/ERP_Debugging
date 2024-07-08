<?php

namespace App\Models;

use CodeIgniter\Model;

class VendorsInvoiceListModel extends Model
{
    protected $table = 'vendors_invoice_list';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'vendor_id', 'purchase_order_id', 'status_id', 'invoice_date', 'total', 'status', 'deleted'
    ];
    protected $returnType = 'object';

    public function getDetails($options = [])
    {
        $vendorsInvoiceListTable = $this->db->prefixTable('vendors_invoice_list');
        $vendorsTable = $this->db->prefixTable('vendors');
        $taskStatusTable = $this->db->prefixTable('vendors_invoice_status');
        $vendorsInvoicePaymentListTable = $this->db->prefixTable('vendors_invoice_payments_list');

        $where = "";

        if (!empty($options['id'])) {
            $where .= " AND $vendorsInvoiceListTable.id=" . $this->db->escape($options['id']);
        }
        if (!empty($options['vendor_id'])) {
            $where .= " AND $vendorsInvoiceListTable.vendor_id=" . $this->db->escape($options['vendor_id']);
        }
        if (!empty($options['status_id'])) {
            $where .= " AND $vendorsInvoiceListTable.status_id=" . $this->db->escape($options['status_id']);
        }
        if (!empty($options['start_date']) && !empty($options['end_date'])) {
            $where .= " AND ($vendorsInvoiceListTable.invoice_date BETWEEN " . $this->db->escape($options['start_date']) . " AND " . $this->db->escape($options['end_date']) . ")";
        }

        $sql = "SELECT $vendorsInvoiceListTable.*, $vendorsTable.company_name AS vendor_name,
                $taskStatusTable.key_name AS status_key_name, $taskStatusTable.title AS status_title, $taskStatusTable.color AS status_color,
                IFNULL(payments_table.paid_amount, 0) AS paid_amount
                FROM $vendorsInvoiceListTable
                LEFT JOIN $vendorsTable ON $vendorsInvoiceListTable.vendor_id = $vendorsTable.id
                LEFT JOIN $taskStatusTable ON $vendorsInvoiceListTable.status_id = $taskStatusTable.id
                LEFT JOIN (SELECT task_id, SUM(title) AS paid_amount FROM $vendorsInvoicePaymentListTable WHERE deleted = 0 GROUP BY task_id) AS payments_table 
                ON payments_table.task_id = $vendorsInvoiceListTable.id
                WHERE $vendorsInvoiceListTable.deleted = 0 $where";
                
        return $this->db->query($sql)->getResult();
    }

    public function getVendorsInvoicePaidAmountSuggestion($item_name = "")
    {
        $hsnSacCodeTable = $this->db->prefixTable('vendors_invoice_payments_list');

        $sql = "SELECT SUM($hsnSacCodeTable.title) AS paid
                FROM $hsnSacCodeTable
                WHERE $hsnSacCodeTable.deleted = 0 AND $hsnSacCodeTable.task_id = " . $this->db->escape($item_name);

        $result = $this->db->query($sql);

        if ($result->getNumRows()) {
            return $result->getRow();
        }
        return null;
    }

    public function isVendorsInvoiceExists($invoice_no, $id = 0)
    {
        $result = $this->where(['invoice_no' => $invoice_no, 'deleted' => 0])->findAll();

        if ($result && $result[0]->id != $id) {
            return $result[0];
        }
        return false;
    }
}
