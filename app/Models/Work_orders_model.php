<?php

namespace App\Models;

use CodeIgniter\Model;

class WorkOrdersModel extends Model
{
    protected $table = 'work_orders';
    protected $primaryKey = 'id';
    protected $allowedFields = ['status', 'work_no'];

    public function __construct()
    {
        parent::__construct();
    }
    public function getDetails($options = [])
    {
        $workOrdersTable = $this->table;
        $vendorsTable = 'vendors'; 
        $workOrderItemsTable = 'work_order_items'; 
        $projectsTable = 'projects';
        $workOrderPaymentsTable = 'work_order_payments';

        $builder = $this->db->table($workOrdersTable);

        $builder->select("$workOrdersTable.*, $vendorsTable.currency, $vendorsTable.currency_symbol, $vendorsTable.company_name, $vendorsTable.buyer_type, $vendorsTable.country, $projectsTable.title as project_title");

        $builder->join($vendorsTable, "$vendorsTable.id = $workOrdersTable.vendor_id", 'left');
        $builder->join($projectsTable, "$projectsTable.id = $workOrdersTable.project_id", 'left');

        $builder->select("(SELECT SUM(net_total) FROM $workOrderItemsTable WHERE work_order_id = $workOrdersTable.id AND deleted = 0) AS work_order_value", false);
        $builder->select("(SELECT SUM(amount) FROM $workOrderPaymentsTable WHERE work_order_id = $workOrdersTable.id AND deleted = 0) AS payment_received", false);

        $where = [];
        $id = $options['id'] ?? null;
        if ($id) {
            $where["$workOrdersTable.id"] = $id;
        }

        $vendorId = $options['vendor_id'] ?? null;
        if ($vendorId) {
            $where["$workOrdersTable.vendor_id"] = $vendorId;
        }

        $startDate = $options['start_date'] ?? null;
        $endDate = $options['end_date'] ?? null;
        if ($startDate && $endDate) {
            $builder->where("$workOrdersTable.work_order_date BETWEEN '$startDate' AND '$endDate'");
        }

        $excludeDraft = $options['exclude_draft'] ?? null;
        if ($excludeDraft) {
            $builder->where("$workOrdersTable.status != 'draft'");
        }

        $status = $options['status'] ?? null;
        if ($status === 'draft') {
            $builder->where("$workOrdersTable.status", 'draft');
            $builder->where("IFNULL(payment_received, 0) <= 0");
        } elseif ($status === 'not_paid') {
            $builder->where("$workOrdersTable.status !=", 'draft');
            $builder->where("IFNULL(payment_received, 0) <= 0");
        } elseif ($status === 'partially_paid') {
            $builder->where("IFNULL(payment_received, 0) > 0");
            $builder->where("IFNULL(payment_received, 0) < work_order_value");
        } elseif ($status === 'fully_paid') {
            $builder->where("TRUNCATE(IFNULL(payment_received, 0), 2) >= work_order_value");
        } elseif ($status === 'overdue') {
            $builder->where("$workOrdersTable.status !=", 'draft');
            $builder->where("$workOrdersTable.valid_until <", date('Y-m-d'));
            $builder->where("TRUNCATE(IFNULL(payment_received, 0), 2) < work_order_value");
        }

        // Add custom fields selection and joins if needed
        $customFields = $options['custom_fields'] ?? null;
        if ($customFields) {
            // Assuming prepare_custom_field_query_string and related functions are defined elsewhere
            $customFieldQueryInfo = $this->prepare_custom_field_query_string("work_orders", $customFields, $workOrdersTable);
            $selectCustomFields = get_array_value($customFieldQueryInfo, "select_string");
            $joinCustomFields = get_array_value($customFieldQueryInfo, "join_string");

            $builder->select($selectCustomFields);
            $builder->join($joinCustomFields);
        }

        $builder->where("$workOrdersTable.deleted", 0);

        return $builder->get();
    }
    public function getWorkOrderTotalSummary($workOrderId = 0)
    {
        $estimateItemsTable = $this->table('work_order_items');
        $estimatesTable = $this->table('work_orders');
        $clientsTable = $this->table('vendors');
        $workOrderPaymentsTable = $this->table('work_order_payments');

        $itemQuantityTotalSql = "SELECT SUM(quantity_total) AS estimate_quantity_subtotal
            FROM $estimateItemsTable
            LEFT JOIN $estimatesTable ON $estimatesTable.id = $estimateItemsTable.work_order_id    
            WHERE $estimateItemsTable.deleted = 0 AND $estimateItemsTable.work_order_id = $workOrderId AND $estimatesTable.deleted = 0";
        $itemQuantityTotal = $this->db->query($itemQuantityTotalSql)->getRow();

        $itemSql = "SELECT SUM(total) AS estimate_subtotal
            FROM $estimateItemsTable
            LEFT JOIN $estimatesTable ON $estimatesTable.id = $estimateItemsTable.work_order_id    
            WHERE $estimateItemsTable.deleted = 0 AND $estimateItemsTable.work_order_id = $workOrderId AND $estimatesTable.deleted = 0";
        $item = $this->db->query($itemSql)->getRow();

        $itemssSql = "SELECT SUM(tax_amount) AS estimate_tax_subtotal
            FROM $estimateItemsTable
            LEFT JOIN $estimatesTable ON $estimatesTable.id = $estimateItemsTable.work_order_id    
            WHERE $estimateItemsTable.deleted = 0 AND $estimateItemsTable.work_order_id = $workOrderId AND $estimatesTable.deleted = 0";
        $itemss = $this->db->query($itemssSql)->getRow();

        $netTotalSql = "SELECT SUM(net_total) AS estimate_net_subtotal
            FROM $estimateItemsTable
            LEFT JOIN $estimatesTable ON $estimatesTable.id = $estimateItemsTable.work_order_id    
            WHERE $estimateItemsTable.deleted = 0 AND $estimateItemsTable.work_order_id = $workOrderId AND $estimatesTable.deleted = 0";
        $netTotal = $this->db->query($netTotalSql)->getRow();

        $estimateSql = "SELECT *
            FROM $estimatesTable
            WHERE $estimatesTable.deleted = 0 AND $estimatesTable.id = $workOrderId";
        $estimate = $this->db->query($estimateSql)->getRow();

        $clientSql = "SELECT currency_symbol, currency 
            FROM $clientsTable 
            WHERE id = $estimate->vendor_id";
        $client = $this->db->query($clientSql)->getRow();

        $paymentSql = "SELECT SUM(amount) AS total_paid
            FROM $workOrderPaymentsTable
            WHERE deleted = 0 AND work_order_id = $workOrderId";
        $payment = $this->db->query($paymentSql)->getRow();

        $result = new \stdClass();
        $result->estimate_subtotal = $item->estimate_subtotal;
        $result->estimate_quantity_subtotal = $itemQuantityTotal->estimate_quantity_subtotal;
        $result->estimate_tax_subtotal = $itemss->estimate_tax_subtotal;
        $result->estimate_net_subtotal = $netTotal->estimate_net_subtotal;
        $result->freight_amount = $estimate->freight_amount;
        $result->freight_rate_amount = $estimate->amount;
        $result->freight_tax_amount = $estimate->freight_tax_amount;
        $result->estimate_net_subtotal_default = $netTotal->estimate_net_subtotal + $result->freight_amount;
        $result->igst_total = $result->estimate_tax_subtotal;
        $result->freight_tax1 = ($estimate->gst / 100) + 1;
        $result->freight_tax2 = $estimate->freight_amount / $result->freight_tax1;
        $result->freight_tax3 = $result->freight_tax2 * $estimate->gst / 100;
        $result->freight_tax = $result->freight_tax2 + $result->freight_tax3;
        $result->estimate_net_total = $result->estimate_net_subtotal + $result->freight_amount;
        $result->total_paid = $payment->total_paid;
        $result->currency_symbol = $client->currency_symbol ?? get_setting("currency_symbol");
        $result->currency = $client->currency ?? get_setting("default_currency");
        $result->balance_due = number_format(round($result->estimate_net_total), 2, ".", "") - number_format($payment->total_paid, 2, ".", "");
        
        return $result;
    }

    private function _getWorkOrderValueCalculationQuery($workOrdersTable)
    {
        $freightAmount = "(IFNULL($workOrdersTable.freight_amount,0))";

        $workOrderValueCalculationQuery = "ROUND(
            IFNULL(items_table.work_order_value, 0) + $freightAmount
        )";

        return $workOrderValueCalculationQuery;
    }

    public function setWorkOrderStatusToNotPaid($workOrderId = 0)
    {
        $statusData = ["status" => "not_paid"];
        return $this->update($workOrderId, $statusData);
    }

    public function isWorkOrderNoExists($workNo, $id = 0)
    {
        $result = $this->where('work_no', $workNo)
                        ->where('deleted', 0)
                        ->where('id !=', $id)
                        ->findAll();

        return (!empty($result)) ? $result[0] : false;
    }

    public function getLastWorkOrderIdExists()
    {
        $query = $this->db->table($this->table)
                          ->orderBy('id', 'DESC')
                          ->limit(1);
        
        return $query->get()->getRow();
    }
}
