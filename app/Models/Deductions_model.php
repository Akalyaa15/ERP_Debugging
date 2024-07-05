<?php

namespace App\Models;

use CodeIgniter\Model;

class Deductions_model extends Model
{
    protected $table = 'deductions';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['user_id', 'sort', 'deleted'];

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $deductions_table = $this->table;
        $builder = $this->db->table($deductions_table);

        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where("$deductions_table.id", $id);
        }

        $builder->where("$deductions_table.deleted", 0);

        return $builder->get();
    }
}
