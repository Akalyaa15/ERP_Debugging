<?php

namespace App\Models;

use CodeIgniter\Model;

class LeadsModel extends Model
{
    protected $table = 'leads';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    public function getDetails($options = [])
    {
        $leadsTable = $this->table;
        $where = [];

        $id = $options['id'] ?? null;
        if ($id) {
            $where[] = "$leadsTable.id = $id";
        }

        $whereClause = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT $leadsTable.*
                FROM $leadsTable
                WHERE $leadsTable.deleted = 0
                $whereClause";

        return $this->db->query($sql)->getResult();
    }
}
