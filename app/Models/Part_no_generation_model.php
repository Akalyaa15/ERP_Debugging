<?php

namespace App\Models;

use CodeIgniter\Model;

class Part_no_generation_model extends Model
{
    protected $table = 'part_no_generation';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $part_no_generation_table = $this->table;
        $vendors_table = 'vendors';
        $where = "";

        $id = $options['id'] ?? null;
        if ($id) {
            $where .= " AND $part_no_generation_table.id=$id";
        }

        $part_no = $options['part_no'] ?? null;
        if ($part_no) {
            $where .= " AND $part_no_generation_table.title='$part_no'";
        }

        $group_id = $options['group_id'] ?? null;
        if ($group_id) {
            $where .= " AND FIND_IN_SET('$group_id', $part_no_generation_table.vendor_id)";
        }

        $sql = "SELECT $part_no_generation_table.*, 
                (SELECT GROUP_CONCAT($vendors_table.id) 
                 FROM $vendors_table 
                 WHERE FIND_IN_SET($vendors_table.id, $part_no_generation_table.vendor_id)) AS groups
                FROM $part_no_generation_table
                WHERE $part_no_generation_table.deleted=0 $where";

        return $this->db->query($sql)->getResult();
    }

    public function get_part_no_suggestion($keyword = "", $d_item = "")
    {
        $part_no_generation_table = $this->table;

        $sql = "SELECT title
                FROM $part_no_generation_table
                WHERE deleted=0 AND title LIKE '%$keyword%'
                AND title NOT IN ($d_item)
                LIMIT 30";

        return $this->db->query($sql)->getResult();
    }

    public function get_part_no_info_suggestion($item_name = "")
    {
        $part_no_generation_table = $this->table;

        $sql = "SELECT *
                FROM $part_no_generation_table
                WHERE deleted=0 AND title LIKE '%$item_name%'
                ORDER BY id DESC
                LIMIT 1";

        $result = $this->db->query($sql);

        if ($result->getNumRows() > 0) {
            return $result->getRow();
        }

        return null;
    }

    public function get_item_suggestionss($s = "")
    {
        $purchase_orders_table = 'purchase_orders';
        $vendors_table = 'vendors';

        $sql = "SELECT $vendors_table.currency, $vendors_table.country
                FROM $vendors_table
                LEFT JOIN $purchase_orders_table ON $purchase_orders_table.vendor_id=$vendors_table.id
                WHERE $vendors_table.deleted=0 AND $purchase_orders_table.id='$s'
                LIMIT 1";

        return $this->db->query($sql)->getRow();
    }

    public function is_part_no_generation_exists($title, $id = 0)
    {
        $result = $this->where('title', $title)
                       ->where('deleted', 0)
                       ->where('id !=', $id)
                       ->findAll();

        if (!empty($result)) {
            return $result[0];
        }

        return false;
    }
}
