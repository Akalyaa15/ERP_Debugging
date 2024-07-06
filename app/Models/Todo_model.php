<?php

namespace App\Models;

use CodeIgniter\Model;
class Todo_model extends Model
{
    protected $table = 'to_do';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'id',
        'created_by',
        'title',
        'status',
        'labels',
    ];

    protected $useSoftDeletes = true; // Enable soft deletes

    protected $returnType = 'object'; // Adjust return type as needed

    public function getDetails($options = [])
    {
        $builder = $this->select('*')
                        ->where('deleted', 0); // Assuming 'deleted' column is used for soft deletes

        if (!empty($options['id'])) {
            $builder->where('id', $options['id']);
        }

        if (!empty($options['created_by'])) {
            $builder->where('created_by', $options['created_by']);
        }

        if (!empty($options['status'])) {
            $builder->whereIn('status', explode(',', $options['status']));
        }

        return $builder->findAll();
    }

    public function getLabelSuggestions($user_id)
    {
        $builder = $this->select('GROUP_CONCAT(labels) as label_groups')
                        ->where('deleted', 0)
                        ->where('created_by', $user_id)
                        ->get();

        return $builder->getRow()->label_groups;
    }

    public function getSearchSuggestions($search = "", $created_by = 0)
    {
        $builder = $this->select('id, title')
                        ->like('title', $search)
                        ->where('deleted', 0)
                        ->where('created_by', $created_by)
                        ->orderBy('title', 'ASC')
                        ->limit(10);

        return $builder->findAll();
    }
}
