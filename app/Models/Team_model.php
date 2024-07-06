<?php

namespace App\Models;

use CodeIgniter\Model;

class Team_model extends Model
{
    protected $table = 'team';
    protected $primaryKey = 'id'; 
    protected $useSoftDeletes = true; 

    protected $returnType = 'object'; 
    public function getDetails($options = [])
    {
        $builder = $this->select('*')
                        ->where('deleted', 0);

        if (!empty($options['id'])) {
            $builder->where('id', $options['id']);
        }

        return $builder->findAll();
    }

    public function getMembers($teamIds = [])
    {
        $builder = $this->select('members')
                        ->whereIn('id', $teamIds)
                        ->where('deleted', 0);

        $query = $builder->findAll();

        $members = [];
        foreach ($query as $row) {
            $members[] = $row->members;
        }

        return $members;
    }
}
