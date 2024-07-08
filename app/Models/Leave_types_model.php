<?php

namespace App\Models;

use CodeIgniter\Model;

class LeaveTypesModel extends Model
{
    protected $table = 'leave_types';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['title', 'description', 'created_at', 'deleted'];
    protected $returnType = 'array';

    public function getDetails($options = [])
    {
        $builder = $this->builder();
        $builder->select('*');

        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where('id', $id);
        }

        $builder->where('deleted', 0);
        $query = $builder->get();
        return $query->getResultArray();
    }
}
