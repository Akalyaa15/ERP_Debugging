<?php

namespace App\Models;

use CodeIgniter\Model;

class GstStateCodeModel extends Model {
    protected $table = 'gst_state_code';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['state_name', 'state_code', 'deleted'];
    protected $returnType = 'array';

    public function getDetails($options = []) {
        $builder = $this->builder($this->table);
        $builder->where('deleted', 0);

        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where('id', $id);
        }

        return $builder->get()->getResultArray();
    }
}
