<?php

namespace App\Models;

use CodeIgniter\Model;

class Clients_wo_payments_list_model extends Model
{
    protected $table = 'clients_wo_payments_list';
    protected $primaryKey = 'id';
    public function get_details($options = [])
    {
        $checklist_items_table = $this->table;
        $payment_table = 'payment_methods';

        $builder = $this->db->table($checklist_items_table);
        $builder->select("$checklist_items_table.*, IF($checklist_items_table.sort != 0, $checklist_items_table.sort, $checklist_items_table.id) AS new_sort, $payment_table.title AS vendor_payment_name");
        $builder->join($payment_table, "$payment_table.id = $checklist_items_table.payment_method_id", 'left');

        $task_id = $options['task_id'] ?? null;
        if ($task_id) {
            $builder->where("$checklist_items_table.task_id", $task_id);
        }

        $builder->where("$checklist_items_table.deleted", 0);

        $builder->orderBy('new_sort', 'ASC');

        return $builder->get()->getResult();
    }

    public function get_yearly_payments_chart($year)
    {
        $payments_table = 'clients_wo_payments_list';
        $purchase_orders_table = 'clients_wo_list';

        $builder = $this->db->table($payments_table);
        $builder->select("SUM($payments_table.title) AS total, MONTH($payments_table.payment_date) AS month");
        $builder->join($purchase_orders_table, "$purchase_orders_table.id = $payments_table.task_id", 'left');
        $builder->where("$payments_table.deleted", 0);
        $builder->where("YEAR($payments_table.payment_date)", $year);
        $builder->where("$purchase_orders_table.deleted", 0);
        $builder->groupBy("MONTH($payments_table.payment_date)");

        return $builder->get()->getResult();
    }
}
