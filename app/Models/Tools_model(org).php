<?php

namespace App\Models;

use CodeIgniter\Model;

class Tools_model extends Model
{
    protected $table = 'tools';
    protected $primaryKey = 'id'; 

    protected $allowedFields = [
        'id',
        'title',
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

        return $builder->get()->getResult();
    }

    public function getItemSuggestion($keyword = "")
    {
        $builder = $this->select('title')
                        ->like('title', $keyword)
                        ->where('deleted', 0)
                        ->limit(30);

        return $builder->get()->getResult();
    }

    public function getItemSuggestions($keyword = "", $excludeItems = [])
    {
        $builder = $this->select('title')
                        ->like('title', $keyword)
                        ->where('deleted', 0)
                        ->whereNotIn('title', $excludeItems)
                        ->limit(30);

        return $builder->get()->getResult();
    }

    public function getItemInfoSuggestion($itemName = "")
    {
        $builder = $this->select('*')
                        ->like('title', $itemName)
                        ->where('deleted', 0)
                        ->orderBy('id', 'DESC')
                        ->limit(1);

        $query = $builder->get();

        if ($query->getNumRows() > 0) {
            return $query->getRow();
        }

        return null;
    }
}
