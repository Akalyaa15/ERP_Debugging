<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductIdGenerationModel extends Model
{
    protected $table = 'product_id_generation';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    public function __construct()
    {
        parent::__construct();
    }

    public function getDetails($options = [])
    {
        $product_id_generation_table = $this->table;
        $part_no_generation_table = $this->db->table('part_no_generation');

        $where = [];
        $id = $options['id'] ?? null;
        if ($id) {
            $where[] = "$product_id_generation_table.id = $id";
        }

        $product_id = $options['product_id'] ?? null;
        if ($product_id) {
            $where[] = "$product_id_generation_table.title = '$product_id'";
        }

        $this->select("$product_id_generation_table.*, $part_no_generation_table.rate AS part_no_value");
        $this->join($part_no_generation_table->getName() . ' pn', 'pn.id = ' . $product_id_generation_table . '.associated_with_part_no', 'left');
        $this->where("$product_id_generation_table.deleted", 0);

        if (!empty($where)) {
            $this->where(implode(' AND ', $where));
        }

        return $this->get()->getResult();
    }

    public function getProductIdSuggestion($keyword = "")
    {
        $product_id_generation_table = $this->table;
        $inventory_table = $this->db->table('items');

        $inventory_result = $inventory_table
            ->select('title')
            ->where('deleted', 0)
            ->get()
            ->getResult();

        $inventory_items = [];
        foreach ($inventory_result as $inventory) {
            $inventory_items[] = $inventory->title;
        }

        $inventory_item = json_encode($inventory_items);
        $inventory_item = str_replace("[", "(", $inventory_item);
        $inventory_item = str_replace("]", ")", $inventory_item);

        $sql = "SELECT title
                FROM $product_id_generation_table
                WHERE deleted = 0 AND title LIKE '%$keyword%' AND title NOT IN $inventory_item
                LIMIT 30";

        return $this->db->query($sql)->getResult();
    }

    public function getProductIdInfoSuggestion($item_name = "")
    {
        $product_id_generation_table = $this->table;

        $sql = "SELECT *
                FROM $product_id_generation_table
                WHERE deleted = 0 AND title LIKE '%$item_name%'
                ORDER BY id DESC
                LIMIT 1";

        $result = $this->db->query($sql);

        if ($result->getNumRows() > 0) {
            return $result->getRow();
        }

        return null;
    }

    public function isProductIdGenerationExists($title, $id = 0)
    {
        $result = $this->where('title', $title)
                       ->where('deleted', 0)
                       ->findAll();

        if (!empty($result) && $result[0]->id != $id) {
            return $result[0];
        } else {
            return false;
        }
    }
}
