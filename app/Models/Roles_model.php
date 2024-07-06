<?php

namespace App\Models;

use CodeIgniter\Model;

class RolesModel extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    public function getDetails($options = [])
    {
        $rolesTable = $this->table;
        $where = [];

        $id = $options['id'] ?? null;
        if ($id) {
            $where["$rolesTable.id"] = $id;
        }

        $builder = $this->db->table($rolesTable);
        $builder->select("*");
        $builder->where($where);
        $builder->where('deleted', 0);

        return $builder->get()->getResult();
    }
}
