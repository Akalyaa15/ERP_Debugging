<?php

namespace App\Models;

use CodeIgniter\Model;

class Estimate_forms_model extends Model
{
    protected $table = 'estimate_forms';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $estimate_forms_table = $this->table;
        $where = "";
        $id = $options['id'] ?? null;
        if ($id) {
            $where .= " AND $estimate_forms_table.id=$id";
        }

        $builder = $this->db->table($estimate_forms_table);
        $builder->where('deleted', 0);
        $builder->where($where);
        return $builder->get();
    }
}
