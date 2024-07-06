<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseOrderPaymentsModel extends Model
{
    protected $table = 'purchase_order_payments';
    protected $primaryKey = 'id'; 
    protected $returnType = 'object'; 

    public function getDetails($options = [])
    {
        $paymentsTable = $this->table;
        $purchaseOrdersTable = 'purchase_orders'; 
        $paymentMethodsTable = 'payment_methods'; 
        $vendorsTable = 'vendors';

        $where = [];

        $id = $options['id'] ?? null;
        if ($id) {
            $where["$paymentsTable.id"] = $id;
        }

        $purchaseOrderId = $options['purchase_order_id'] ?? null;
        if ($purchaseOrderId) {
            $where["$paymentsTable.purchase_order_id"] = $purchaseOrderId;
        }

        $vendorId = $options['vendor_id'] ?? null;
        if ($vendorId) {
            $where["$purchaseOrdersTable.vendor_id"] = $vendorId;
        }

        $projectId = $options['project_id'] ?? null;
        if ($projectId) {
            $where["$purchaseOrdersTable.project_id"] = $projectId;
        }

        $paymentMethodId = $options['payment_method_id'] ?? null;
        if ($paymentMethodId) {
            $where["$paymentsTable.payment_method_id"] = $paymentMethodId;
        }

        $startDate = $options['start_date'] ?? null;
        $endDate = $options['end_date'] ?? null;
        if ($startDate && $endDate) {
            $where[] = "($paymentsTable.payment_date BETWEEN '$startDate' AND '$endDate')";
        }

        $builder = $this->db->table($paymentsTable);
        $builder->select("$paymentsTable.*, $purchaseOrdersTable.vendor_id, 
            (SELECT $vendorsTable.currency_symbol FROM $vendorsTable 
                WHERE $vendorsTable.id=$purchaseOrdersTable.vendor_id LIMIT 1) AS currency_symbol, 
            $paymentMethodsTable.title AS payment_method_title");
        $builder->join($purchaseOrdersTable, "$purchaseOrdersTable.id = $paymentsTable.purchase_order_id", 'left');
        $builder->join($paymentMethodsTable, "$paymentMethodsTable.id = $paymentsTable.payment_method_id", 'left');
        $builder->where($where);
        $builder->where("$paymentsTable.deleted", 0);
        $builder->where("$purchaseOrdersTable.deleted", 0);

        return $builder->get()->getResult();
    }

    public function getYearlyPaymentsChart($year)
    {
        $paymentsTable = 'purchase_order_payments'; // Adjust if needed
        $purchaseOrdersTable = 'purchase_orders'; // Adjust if needed

        $builder = $this->db->table($paymentsTable);
        $builder->select("SUM($paymentsTable.amount) AS total, MONTH($paymentsTable.payment_date) AS month");
        $builder->join($purchaseOrdersTable, "$purchaseOrdersTable.id = $paymentsTable.purchase_order_id", 'left');
        $builder->where("$paymentsTable.deleted", 0);
        $builder->where("YEAR($paymentsTable.payment_date)", $year);
        $builder->where("$purchaseOrdersTable.deleted", 0);
        $builder->groupBy("MONTH($paymentsTable.payment_date)");

        return $builder->get()->getResult();
    }
}
