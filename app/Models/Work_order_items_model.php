<?php

namespace App\Models;

use CodeIgniter\Model;

class Work_order_items_model extends Model
{
    protected $table = 'work_order_items';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $estimate_items_table = $this->table;
        $work_orders_table = 'work_orders';
        $vendors_table = 'vendors';
        $where = "";
        
        $id = $options['id'] ?? null;
        if ($id) {
            $where .= " AND $estimate_items_table.id=$id";
        }

        $work_order_id = $options['work_order_id'] ?? null;
        if ($work_order_id) {
            $where .= " AND $estimate_items_table.work_order_id=$work_order_id";
        }

        $sql = "SELECT $estimate_items_table.*, 
                (SELECT $vendors_table.currency_symbol 
                 FROM $vendors_table 
                 WHERE $vendors_table.id=$work_orders_table.vendor_id 
                 LIMIT 1) AS currency_symbol
                FROM $estimate_items_table
                LEFT JOIN $work_orders_table 
                ON $work_orders_table.id=$estimate_items_table.work_order_id
                WHERE $estimate_items_table.deleted=0 $where";

        return $this->db->query($sql)->getResult();
    }

    public function get_item_suggestion($keyword = "", $d_item = "")
    {
        $items_table = 'outsource_jobs';

        $sql = "SELECT title
                FROM $items_table
                WHERE deleted=0 AND title LIKE '%$keyword%' AND title NOT IN ($d_item)
                LIMIT 30";

        return $this->db->query($sql)->getResult();
    }

    public function get_item_info_suggestion($item_name = "")
    {
        $items_table = 'outsource_jobs';

        $sql = "SELECT *
                FROM $items_table
                WHERE deleted=0 AND title LIKE '%$item_name%'
                ORDER BY id DESC
                LIMIT 1";

        $result = $this->db->query($sql);

        return $result->getRow();
    }

    public function get_item_suggestionss($s = "")
    {
        $work_orders_table = 'work_orders';
        $vendors_table = 'vendors';

        $sql = "SELECT $vendors_table.currency, $vendors_table.country
                FROM $vendors_table
                LEFT JOIN $work_orders_table ON $work_orders_table.vendor_id=$vendors_table.id
                WHERE $vendors_table.deleted=0 AND $work_orders_table.id='$s'
                LIMIT 1";

        return $this->db->query($sql)->getRow();
    }

    public function is_wo_product_exists($title, $work_order_id, $id = 0)
    {
        $result = $this->where('title', $title)
                       ->where('work_order_id', $work_order_id)
                       ->where('deleted', 0)
                       ->where('id !=', $id)
                       ->findAll();

        return !empty($result) ? $result[0] : false;
    }
}
