<?php

namespace App\Models;
use CodeIgniter\Model;

class Work_order_payments_model extends Model
{
    protected $table = 'work_order_payments';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    public function __construct()
    {
        parent::__construct();
    }
 public function get_details($options = [])
    {
        $work_order_payments_table = $this->table;
        $work_orders_table = 'work_orders';
        $payment_methods_table = 'payment_methods';
        $vendors_table = 'vendors';

        $where = "";

        $id = $options['id'] ?? null;
        if ($id) {
            $where .= " AND $work_order_payments_table.id=$id";
        }

        $work_order_id = $options['work_order_id'] ?? null;
        if ($work_order_id) {
            $where .= " AND $work_order_payments_table.work_order_id=$work_order_id";
        }

        $vendor_id = $options['vendor_id'] ?? null;
        if ($vendor_id) {
            $where .= " AND $work_orders_table.vendor_id=$vendor_id";
        }

        $project_id = $options['project_id'] ?? null;
        if ($project_id) {
            $where .= " AND $work_orders_table.project_id=$project_id";
        }

        $payment_method_id = $options['payment_method_id'] ?? null;
        if ($payment_method_id) {
            $where .= " AND $work_order_payments_table.payment_method_id=$payment_method_id";
        }

        $start_date = $options['start_date'] ?? null;
        $end_date = $options['end_date'] ?? null;
        if ($start_date && $end_date) {
            $where .= " AND ($work_order_payments_table.payment_date BETWEEN '$start_date' AND '$end_date') ";
        }

        $sql = "SELECT $work_order_payments_table.*, 
                $work_orders_table.vendor_id, 
                (SELECT $vendors_table.currency_symbol 
                 FROM $vendors_table 
                 WHERE $vendors_table.id=$work_orders_table.vendor_id 
                 LIMIT 1) AS currency_symbol, 
                $payment_methods_table.title AS payment_method_title
                FROM $work_order_payments_table
                LEFT JOIN $work_orders_table 
                ON $work_orders_table.id=$work_order_payments_table.work_order_id
                LEFT JOIN $payment_methods_table 
                ON $payment_methods_table.id = $work_order_payments_table.payment_method_id
                WHERE $work_order_payments_table.deleted=0 
                AND $work_orders_table.deleted=0 $where";

        return $this->db->query($sql)->getResult();
    }

    public function get_yearly_payments_chart($year)
    {
        $payments_table = 'work_order_payments';
        $work_orders_table = 'work_orders';

        $payments = "SELECT SUM(amount) AS total, 
                    MONTH(payment_date) AS month
                    FROM $payments_table
                    LEFT JOIN $work_orders_table 
                    ON $work_orders_table.id=$payments_table.work_order_id
                    WHERE $payments_table.deleted=0 
                    AND YEAR(payment_date)= $year 
                    AND $work_orders_table.deleted=0
                    GROUP BY MONTH(payment_date)";

        return $this->db->query($payments)->getResult();
    }
}