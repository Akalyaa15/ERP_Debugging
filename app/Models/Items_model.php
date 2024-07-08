<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemsModel extends Model
{
    protected $table = 'items';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'product_generation_id',
        'stock',
    ];

    public function getDetails($options = [])
    {
        $itemsTable = $this->table;
        $taxesTable = $this->db->prefixTable('taxes');

        $where = [];

        $id = $options['id'] ?? null;
        if ($id) {
            $where[] = "$itemsTable.id = $id";
        }

        $productGenerationId = $options['product_generation_id'] ?? null;
        if ($productGenerationId) {
            $where[] = "$itemsTable.product_generation_id = '$productGenerationId'";
        }

        $quantity = $options['quantity'] ?? null;
        if ($quantity === "0") {
            $where[] = "$itemsTable.stock = '$quantity'";
        } elseif ($quantity === "10") {
            $where[] = "$itemsTable.stock > 0 AND $itemsTable.stock <= 10";
        } elseif ($quantity === "30") {
            $where[] = "$itemsTable.stock > 10 AND $itemsTable.stock <= 30";
        } elseif ($quantity === "50") {
            $where[] = "$itemsTable.stock > 30 AND $itemsTable.stock <= 50";
        } elseif ($quantity === "51") {
            $where[] = "$itemsTable.stock >= '$quantity'";
        } elseif ($quantity === "101") {
            $where[] = "$itemsTable.stock >= '$quantity'";
        }

        $whereClause = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT $itemsTable.*
        FROM $itemsTable
        WHERE $itemsTable.deleted = 0
        $whereClause";

        return $this->db->query($sql)->getResult();
    }

    public function isInventoryProductExists($title, $id = 0)
    {
        $result = $this->where('title', $title)
                       ->where('deleted', 0)
                       ->where('id !=', $id)
                       ->get();

        if ($result->getResult()) {
            return $result->getRow();
        } else {
            return false;
        }
    }
}
