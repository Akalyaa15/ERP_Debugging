<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemsModel extends Model
{
    protected $table = 'items';
    protected $primaryKey = 'id';

    public function getDetails($options = [])
    {
        $itemsTable = $this->table;
        $where = [];

        $id = $options['id'] ?? null;
        if ($id) {
            $where[] = "$itemsTable.id = $id";
        }

        $whereClause = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT $itemsTable.*
                FROM $itemsTable
                WHERE $itemsTable.deleted = 0
                $whereClause";

        return $this->db->query($sql)->getResult();
    }
}