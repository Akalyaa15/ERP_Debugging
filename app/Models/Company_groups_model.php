<?php

namespace App\Models;

use CodeIgniter\Model;

class Company_groups_model extends Model
{
    protected $table = 'company_groups';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;

    public function get_details($options = [])
    {
        $id = $options['id'] ?? null;
        $builder = $this->db->table($this->table);

        if ($id) {
            $builder->where('id', $id);
        }

        $builder->where('deleted', 0);

        return $builder->get()->getResult();
    }
}
