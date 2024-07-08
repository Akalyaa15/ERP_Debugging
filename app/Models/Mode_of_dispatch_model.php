<?php

namespace App\Models;

use CodeIgniter\Model;

class Mode_of_dispatch_model extends Model
{
    protected $table = 'mode_of_dispatch';

    public function getDetails($options = [])
    {
        $mode_of_dispatch_table = $this->table;
        $where = "";
        $id = $options['id'] ?? null;
        if ($id) {
            $where = " AND $mode_of_dispatch_table.id=$id";
        }

        $sql = "SELECT $mode_of_dispatch_table.*
                FROM $mode_of_dispatch_table
                WHERE $mode_of_dispatch_table.deleted=0 $where";

        return $this->db->query($sql);
    }
}
