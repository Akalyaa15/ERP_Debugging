<?php

namespace App\Models;

use CodeIgniter\Model;

class Credentials_model extends Model
{
    private $table = 'credentials';

    public function __construct()
    {
        parent::__construct();
    }

    public function getDetails($options = [])
    {
        $table = $this->table;
        $where = "";

        $id = $options['id'] ?? null;
        if ($id) {
            $where = " AND $table.id = $id";
        }

        $sql = "SELECT $table.*
                FROM $table
                WHERE $table.deleted = 0 $where";

        return $this->db->query($sql)->getResultArray();
    }
}
