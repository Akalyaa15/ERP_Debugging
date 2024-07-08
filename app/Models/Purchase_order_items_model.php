<?php

namespace App\Models;

use CodeIgniter\Model;

class Purchase_order_items_model extends Model
{
    protected $table = 'purchase_order_items';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['purchase_order_id', 'title', 'description', 'quantity', 'unit_price', 'tax', 'total', 'status', 'deleted'];

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $builder = $this->db->table($this->table);
        $estimate_items_table = $builder->getName();
        $estimates_table = 'purchase_orders'; // adjust table names as per your database
        $clients_table = 'vendors';
        $where = [];

        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where("$estimate_items_table.id", $id);
        }

        $purchase_order_id = $options['purchase_order_id'] ?? null;
        if ($purchase_order_id) {
            $builder->where("$estimate_items_table.purchase_order_id", $purchase_order_id);
        }

        $builder->select("$estimate_items_table.*, 
            (SELECT $clients_table.currency_symbol FROM $clients_table 
            WHERE $clients_table.id = $estimates_table.vendor_id 
            LIMIT 1) AS currency_symbol")
            ->join($estimates_table, "$estimates_table.id = $estimate_items_table.purchase_order_id", 'left')
            ->where("$estimate_items_table.deleted", 0);

        return $builder->get();
    }

    public function is_po_product_exists($title, $purchase_order_id, $id = 0)
    {
        $builder = $this->db->table($this->table);
        $builder->where('title', $title)
                ->where('purchase_order_id', $purchase_order_id)
                ->where('deleted', 0);

        $result = $builder->get()->getRow();

        if ($result && $result->id != $id) {
            return $result;
        } else {
            return false;
        }
    }
}
