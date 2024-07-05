<?php

namespace App\Models;

use CodeIgniter\Model;

class Dashboards_model extends Model
{
    protected $table = 'dashboards';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;

    protected $allowedFields = ['user_id', 'sort', 'deleted'];

    public function get_details($options = [])
    {
        $dashboard_table = $this->table;

        $builder = $this->db->table($dashboard_table);

        $user_id = $options['user_id'] ?? null;
        if ($user_id) {
            $builder->where("$dashboard_table.user_id", $user_id);
        }

        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where("$dashboard_table.id", $id);
        }

        $builder->where("$dashboard_table.deleted", 0)
                ->orderBy('IF(sort!=0, sort, id)', 'DESC');

        return $builder->get();
    }
}
