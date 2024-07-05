<?php

namespace App\Models;

use CodeIgniter\Model;

class Country_deductions_model extends Model
{
    protected $table = 'country_deductions';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;

    public function get_details($options = [])
    {
        $deductions_table = $this->table;

        $id = $options['id'] ?? null;
        $country_id = $options['country_id'] ?? null;

        $builder = $this->db->table($deductions_table);
        $builder->where('deleted', 0);

        if ($id) {
            $builder->where('id', $id);
        }

        if ($country_id) {
            $builder->where('country_id', $country_id);
        }

        return $builder->get()->getResult();
    }
}
