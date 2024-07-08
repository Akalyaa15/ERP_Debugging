<?php

namespace App\Models;

use CodeIgniter\Model;
class Delivery_items_model extends Model
{
    protected $table = 'delivery_items';

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $delivery_items_table = $this->table;
        $delivery_table = $this->db->dbprefix('delivery');

        $builder = $this->db->table($delivery_items_table)
                            ->select("$delivery_items_table.*")
                            ->leftJoin($delivery_table, "$delivery_table.id = $delivery_items_table.estimate_id")
                            ->where("$delivery_items_table.deleted", 0);

        $id = get_array_value($options, "id");
        if ($id) {
            $builder->where("$delivery_items_table.id", $id);
        }

        $estimate_id = get_array_value($options, "estimate_id");
        if ($estimate_id) {
            $builder->where("$delivery_items_table.estimate_id", $estimate_id);
        }

        return $builder->get()->getResult();
    }

    public function get_sold_details($options = [])
    {
        $delivery_items_table = $this->table;
        $delivery_table = $this->db->dbprefix('delivery');

        $builder = $this->db->table($delivery_items_table)
                            ->select("$delivery_items_table.sold")
                            ->leftJoin($delivery_table, "$delivery_table.id = $delivery_items_table.estimate_id")
                            ->where("$delivery_items_table.deleted", 0)
                            ->where("$delivery_items_table.sold !=", 0);

        $id = get_array_value($options, "id");
        if ($id) {
            $builder->where("$delivery_items_table.id", $id);
        }

        $estimate_id = get_array_value($options, "estimate_id");
        if ($estimate_id) {
            $builder->where("$delivery_items_table.estimate_id", $estimate_id);
        }

        return $builder->get()->getResult();
    }

    public function get_ret_sold_details($options = [])
    {
        $delivery_items_table = $this->table;

        $builder = $this->db->table($delivery_items_table)
                            ->select("$delivery_items_table.sold, $delivery_items_table.ret_sold")
                            ->where("$delivery_items_table.deleted", 0)
                            ->where("($delivery_items_table.sold > 0 OR $delivery_items_table.ret_sold > 0)");

        $id = get_array_value($options, "id");
        if ($id) {
            $builder->where("$delivery_items_table.id", $id);
        }

        $estimate_id = get_array_value($options, "estimate_id");
        if ($estimate_id) {
            $builder->where("$delivery_items_table.estimate_id", $estimate_id);
        }

        return $builder->get()->getResult();
    }

    public function get_details_for_invoice($options = [])
    {
        $delivery_items_table = $this->table;
        $delivery_table = $this->db->dbprefix('delivery');

        $builder = $this->db->table($delivery_items_table)
                            ->select("$delivery_items_table.*")
                            ->leftJoin($delivery_table, "$delivery_table.id = $delivery_items_table.estimate_id")
                            ->where("$delivery_items_table.deleted", 0)
                            ->where("$delivery_items_table.sold !=", 0);

        $id = get_array_value($options, "id");
        if ($id) {
            $builder->where("$delivery_items_table.id", $id);
        }

        $estimate_id = get_array_value($options, "estimate_id");
        if ($estimate_id) {
            $builder->where("$delivery_items_table.estimate_id", $estimate_id);
        }

        return $builder->get()->getResult();
    }
}
