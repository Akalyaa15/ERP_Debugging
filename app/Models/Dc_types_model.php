<?php

namespace App\Models;

use CodeIgniter\Model;

class Dc_types_model extends Model
{
    protected $table = 'dc_types';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;

    public function get_details($options = [])
    {
        $dc_types_table = $this->table;
        $builder = $this->db->table($dc_types_table);

        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where("$dc_types_table.id", $id);
        }

        $builder->where("$dc_types_table.deleted", 0);

        return $builder->get();
    }
}
