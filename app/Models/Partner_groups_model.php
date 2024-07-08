<?php

namespace App\Models;

use CodeIgniter\Model;

class Partner_groups_model extends Model
{
    protected $table = 'partner_groups';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $client_groups_table = $this->table;
        $where = "";

        $id = $options['id'] ?? null;
        if ($id) {
            $where = " AND $client_groups_table.id=$id";
        }

        $sql = "SELECT *
                FROM $client_groups_table
                WHERE deleted=0 $where";

        return $this->db->query($sql)->getResult();
    }
}