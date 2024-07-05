<?php

namespace App\Models;

use CodeIgniter\Model;

class Clients_po_list_model extends Model
{
    protected $table = 'clients_po_list';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        // list all fields here
    ];

    public function get_details($options = [])
    {
        $vendors_invoice_list_table = $this->db->prefixTable('clients_po_list');
        $vendors_table = $this->db->prefixTable('clients');
        $task_status_table = $this->db->prefixTable('vendors_invoice_status');
        $vendors_invoice_payment_list_table = $this->db->prefixTable('clients_po_payments_list');

        $where = [];
        
        $id = get_array_value($options, "id");
        if ($id) {
            $where['id'] = $id;
        }

        $purchase_order_id = get_array_value($options, "purchase_order_id");
        if ($purchase_order_id) {
            $where['purchase_order_id'] = $purchase_order_id;
        }

        $vendor_id = get_array_value($options, "vendor_id");
        if ($vendor_id) {
            $where['vendor_id'] = $vendor_id;
        }

        $status_id = get_array_value($options, "status_id");
        if ($status_id) {
            $where['status_id'] = $status_id;
        }

        $start_date = get_array_value($options, "start_date");
        $end_date = get_array_value($options, "end_date");
        if ($start_date && $end_date) {
            $where["invoice_date BETWEEN '{$start_date}' AND '{$end_date}'"] = null;
        }

        $status = get_array_value($options, "status");
        if ($status === "draft") {
            $where["status"] = "draft";
            $where["IFNULL(payments_table.paid_amount, 0) <= 0"] = null;
        } elseif ($status === "not_paid") {
            $where["status != 'draft'"] = null;
            $where["IFNULL(payments_table.paid_amount, 0) <= 0"] = null;
        } elseif ($status === "partially_paid") {
            $where["IFNULL(payments_table.paid_amount, 0) > 0"] = null;
            $where["IFNULL(payments_table.paid_amount, 0) < clients_po_list_table.total"] = null;
        } elseif ($status === "fully_paid") {
            $where["TRUNCATE(IFNULL(payments_table.paid_amount, 0), 2) >= clients_po_list_table.total"] = null;
        }

        $builder = $this->db->table($vendors_invoice_list_table);
        $builder->select("$vendors_invoice_list_table.*, $vendors_table.company_name AS vendor_name, $vendors_table.currency_symbol AS currency_symbol, 
            $task_status_table.key_name AS status_key_name, $task_status_table.title AS status_title, $task_status_table.color AS status_color, IFNULL(payments_table.paid_amount, 0) AS paid_amount");
        $builder->join($vendors_table, "$vendors_invoice_list_table.vendor_id = $vendors_table.id", 'left');
        $builder->join($task_status_table, "$vendors_invoice_list_table.status_id = $task_status_table.id", 'left');
        $builder->join("(SELECT task_id, SUM(title) AS paid_amount FROM $vendors_invoice_payment_list_table WHERE deleted=0 GROUP BY task_id) AS payments_table", "payments_table.task_id = $vendors_invoice_list_table.id", 'left');
        $builder->where($where);
        $builder->where('deleted', 0);

        return $builder->get();
    }

    public function get_vendors_invoice_paid_amount_suggestion($item_name = "")
    {
        $hsn_sac_code_table = $this->db->prefixTable('clients_po_payments_list');

        $builder = $this->db->table($hsn_sac_code_table);
        $builder->select("SUM(title) AS paid");
        $builder->where('task_id', $item_name);
        $builder->where('deleted', 0);

        $result = $builder->get();
        return $result->getRow();
    }

    public function is_vendors_invoice_exists($invoice_no, $id = 0)
    {
        $builder = $this->db->table($this->table);
        $builder->select('*');
        $builder->where('invoice_no', $invoice_no);
        $builder->where('deleted', 0);
        $builder->where('id !=', $id);

        $result = $builder->get();
        return $result->getRow();
    }

    public function get_purchase_orderid($item_name = "", $loan_voucher_no = "")
    {
        $purchase_orders_table = $this->db->prefixTable('estimates');

        $builder = $this->db->table($purchase_orders_table);
        $builder->select('*');
        $builder->where('client_id', $item_name);
        $builder->where('deleted', 0);
        $builder->whereNotIn('id', $loan_voucher_no);

        $result = $builder->get();
        return $result->getResult();
    }
}