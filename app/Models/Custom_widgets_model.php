<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomWidgetsModel extends Model
{
    protected $table = 'custom_widgets';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['user_id', 'title', 'field3'];
    protected $returnType = 'array';

    public function getDetails($options = [])
    {
        $builder = $this->db->table($this->table);
        $builder->where('deleted', 0);

        if (!empty($options['user_id'])) {
            $builder->where('user_id', $options['user_id']);
        }

        if (!empty($options['id'])) {
            $builder->where('id', $options['id']);
        }

        $builder->orderBy('title', 'ASC');

        return $builder->get()->getResultArray();
    }}
