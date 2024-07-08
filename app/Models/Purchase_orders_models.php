<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseOrdersModel extends Model
{
    protected $table = 'purchase_orders';
    protected $primaryKey = 'id';
   protected $allowedFields = [
    'vendor_id',
    'purchase_order_date',
    'status',
    'valid_until',
    'freight_amount',
];


    public function getDetails($options = [])
    {
        $builder = $this->db->table('purchase_orders');
        $builder->select('purchase_orders.*, vendors.currency, vendors.currency_symbol, vendors.company_name, vendors.country, projects.title as project_title');
        $builder->join('vendors', 'vendors.id = purchase_orders.vendor_id', 'left');
        $builder->join('projects', 'projects.id = purchase_orders.project_id', 'left');

        // Add custom fields
        $customFields = $this->prepareCustomFieldQuery('purchase_orders', $options);
        $builder->select($customFields['select_string']);
        $builder->join($customFields['join_string'], '', 'left');

        // Apply filters
        if (!empty($options['id'])) {
            $builder->where('purchase_orders.id', $options['id']);
        }
        if (!empty($options['vendor_id'])) {
            $builder->where('purchase_orders.vendor_id', $options['vendor_id']);
        }
        if (!empty($options['start_date']) && !empty($options['end_date'])) {
            $builder->where("purchase_orders.purchase_order_date BETWEEN '{$options['start_date']}' AND '{$options['end_date']}'");
        }
        if (!empty($options['exclude_draft'])) {
            $builder->where('purchase_orders.status !=', 'draft');
        }

        // Handle status filters
        $status = $options['status'] ?? null;
        switch ($status) {
            case 'draft':
                $builder->where('purchase_orders.status', 'draft')
                        ->where('IFNULL(payments_table.payment_received, 0) <=', 0);
                break;
            case 'not_paid':
                $builder->where('purchase_orders.status !=', 'draft')
                        ->where('IFNULL(payments_table.payment_received, 0) <=', 0);
                break;
            case 'partially_paid':
                $builder->where('IFNULL(payments_table.payment_received, 0) >', 0)
                        ->where('IFNULL(payments_table.payment_received, 0) < items_table.purchase_order_value');
                break;
            case 'fully_paid':
                $builder->where('IFNULL(payments_table.payment_received, 0) >=', 'TRUNCATE(items_table.purchase_order_value, 2)');
                break;
            case 'overdue':
                $builder->where('purchase_orders.status !=', 'draft')
                        ->where('purchase_orders.valid_until <', date('Y-m-d'))
                        ->where('TRUNCATE(IFNULL(payments_table.payment_received, 0), 2) <', 'TRUNCATE(items_table.purchase_order_value, 2)');
                break;
            default:
                break;
        }

        $query = $builder->get();
        return $query->getResult();
    }

    public function getPurchaseOrderTotalSummary($purchaseOrderId = 0)
    {
        $builder = $this->db->table('purchase_order_items');
        $builder->select('SUM(total) as estimate_subtotal, SUM(tax_amount) as estimate_tax_subtotal, SUM(net_total) as estimate_net_subtotal');
        $builder->where('purchase_order_id', $purchaseOrderId)->where('deleted', 0);
        $item = $builder->get()->getRow();

        $builder = $this->db->table('purchase_orders');
        $builder->select('freight_amount, gst');
        $builder->where('id', $purchaseOrderId)->where('deleted', 0);
        $estimate = $builder->get()->getRow();

        $builder = $this->db->table('purchase_order_payments');
        $builder->select('SUM(amount) as total_paid');
        $builder->where('purchase_order_id', $purchaseOrderId)->where('deleted', 0);
        $payment = $builder->get()->getRow();

        $result = new \stdClass();
        $result->estimate_subtotal = $item->estimate_subtotal ?? 0;
        $result->estimate_tax_subtotal = $item->estimate_tax_subtotal ?? 0;
        $result->estimate_net_subtotal = $item->estimate_net_subtotal ?? 0;
        $result->freight_amount = $estimate->freight_amount ?? 0;
        $result->estimate_net_subtotal_default = ($item->estimate_net_subtotal ?? 0) + ($estimate->freight_amount ?? 0);
        $result->igst_total = $result->estimate_tax_subtotal;
        $result->freight_tax1 = ($estimate->gst / 100) + 1;
        $result->freight_tax2 = ($estimate->freight_amount ?? 0) / $result->freight_tax1;
        $result->freight_tax3 = ($result->freight_tax2 ?? 0) * ($estimate->gst ?? 0) / 100;
        $result->freight_tax = ($result->freight_tax2 ?? 0) + ($result->freight_tax3 ?? 0);
        $result->estimate_net_total = ($result->estimate_net_subtotal ?? 0) + ($result->freight_amount ?? 0);
        $result->total_paid = $payment->total_paid ?? 0;
        $result->balance_due = number_format(round($result->estimate_net_total), 2, ".", "") - number_format($payment->total_paid ?? 0, 2, ".", "");

        return $result;
    }

    public function setPurchaseOrderStatusToNotPaid($purchaseOrderId = 0)
    {
        return $this->update($purchaseOrderId, ['status' => 'not_paid']);
    }

    private function prepareCustomFieldQuery($table_name, $options)
    {
        // Implement your custom field query logic here
        // Example implementation:
        $select_string = '';
        $join_string = '';

        return ['select_string' => $select_string, 'join_string' => $join_string];
    }
}

