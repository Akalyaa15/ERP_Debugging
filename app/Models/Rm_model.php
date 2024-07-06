<?php

namespace App\Models;

use CodeIgniter\Model;

class RmModel extends Model
{
    protected $table = 'team';
    protected $primaryKey = 'id';
    protected $returnType = 'object'; 
    public function getDetails($options = [])
    {
        $teamTable = $this->table;
        $where = [];

        $id = $options['id'] ?? null;
        if ($id) {
            $where["$teamTable.id"] = $id;
        }

        $builder = $this->db->table($teamTable);
        $builder->select("*");
        $builder->where($where);
        $builder->where('deleted', 0);

        return $builder->get()->getResult();
    }

    public function getMembers($teamIds = [])
    {
        $teamTable = $this->table;

        $builder = $this->db->table($teamTable);
        $builder->select("members");
        $builder->whereIn('id', $teamIds);
        $builder->where('deleted', 0);

        return $builder->get()->getResult();
    }
}
