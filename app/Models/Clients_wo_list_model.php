<?php

namespace App\Models;

use CodeIgniter\Model;

class Clients_wo_list_model extends Model
{
    protected $table = 'clients_wo_list';
    protected $primaryKey = 'id';
    protected $allowedFields = [];

    public function get_details($options = [])
    {
        $vendors_invoice_list_table = $this->table;
        $vendors_table = 'clients';
        $task_status_table = 'vendors_invoice_status';
        $vendors_invoice_payment_list_table = 'clients_wo_payments_list';

        $builder = $this->db->table($vendors_invoice_list_table);
        $builder->select("$vendors_invoice_list_table.*, $vendors_table.company_name AS vendor_name, $vendors_table.currency_symbol AS currency_symbol,
            $task_status_table.key_name AS status_key_name, $task_status_table.title AS status_title, $task_status_table.color AS status_color,
            IFNULL(payments_table.paid_amount, 0) AS paid_amount");
        $builder->join($vendors_table, "$vendors_invoice_list_table.vendor_id = $vendors_table.id", 'left');
        $builder->join($task_status_table, "$vendors_invoice_list_table.status_id = $task_status_table.id", 'left');

        $builder->select("IFNULL(payments_table.paid_amount, 0) AS paid_amount");
        $builder->join("(SELECT task_id, SUM(title) AS paid_amount FROM $vendors_invoice_payment_list_table WHERE deleted = 0 GROUP BY task_id) AS payments_table", "payments_table.task_id = $vendors_invoice_list_table.id", 'left');

        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where("$vendors_invoice_list_table.id", $id);
        }

        $purchase_order_id = $options['purchase_order_id'] ?? null;
        if ($purchase_order_id) {
            $builder->where("$vendors_invoice_list_table.purchase_order_id", $purchase_order_id);
        }

        $vendor_id = $options['vendor_id'] ?? null;
        if ($vendor_id) {
            $builder->where("$vendors_invoice_list_table.vendor_id", $vendor_id);
        }

        $status_id = $options['status_id'] ?? null;
        if ($status_id) {
            $builder->where("$vendors_invoice_list_table.status_id", $status_id);
        }

        $start_date = $options['start_date'] ?? null;
        $end_date = $options['end_date'] ?? null;
        if ($start_date && $end_date) {
            $builder->where("($vendors_invoice_list_table.invoice_date BETWEEN '$start_date' AND '$end_date')");
        }

        $status = $options['status'] ?? null;
        if ($status === 'draft') {
            $builder->where("$vendors_invoice_list_table.status", 'draft');
            $builder->where("IFNULL(payments_table.paid_amount, 0) <= 0");
        } elseif ($status === 'not_paid') {
            $builder->where("$vendors_invoice_list_table.status != 'draft'");
            $builder->where("IFNULL(payments_table.paid_amount, 0) <= 0");
        } elseif ($status === 'partially_paid') {
            $builder->where("IFNULL(payments_table.paid_amount, 0) > 0");
            $builder->where("IFNULL(payments_table.paid_amount, 0) < $vendors_invoice_list_table.total");
        } elseif ($status === 'fully_paid') {
            $builder->where("TRUNCATE(IFNULL(payments_table.paid_amount, 0), 2) >= $vendors_invoice_list_table.total");
        }

        $builder->where("$vendors_invoice_list_table.deleted", 0);

        return $builder->get()->getResult();
    }

    public function get_vendors_invoice_paid_amount_suggestion($item_name = "")
    {
        $hsn_sac_code_table = 'clients_wo_payments_list';

        $builder = $this->db->table($hsn_sac_code_table);
        $builder->select("SUM($hsn_sac_code_table.title) AS paid");
        $builder->where("$hsn_sac_code_table.deleted", 0);
        $builder->where("$hsn_sac_code_table.task_id", $item_name);

        $result = $builder->get();

        if ($result->getNumRows() > 0) {
            return $result->getRow();
        } else {
            return null;
        }
    }

    public function is_vendors_invoice_exists($invoice_no, $id = 0)
    {
        $builder = $this->db->table($this->table);
        $builder->where('invoice_no', $invoice_no);
        $builder->where('deleted', 0);
        $result = $builder->get();

        if ($result->getNumRows() > 0) {
            $row = $result->getRow();
            if ($row->id != $id) {
                return $row;
            }
        }
        
        return false;
    }
public function get_purchase_orderid($item_name = "", $loan_voucher_no = "")
    {
        $purchase_orders_table = 'estimates';

        $builder = $this->db->table($purchase_orders_table);
        $builder->select("*");
        $builder->where('deleted', 0);
        $builder->where('client_id', $item_name);
        $builder->whereNotIn('id', $loan_voucher_no);
        $builder->orderBy('id', 'ASC');

        return $builder->get()->getResult();
    }
}
