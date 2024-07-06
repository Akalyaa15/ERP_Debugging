<?php

namespace App\Models;

use CodeIgniter\Model;

class ServiceCategoriesModel extends Model
{
    protected $table = 'service_categories';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    public function getDetails($options = [])
    {
        $id = $options['id'] ?? null;
        
        $query = $this->where('deleted', 0);
        
        if ($id) {
            $query->where('id', $id);
        }
        
        return $query->findAll();
    }

    public function isServiceCategoryListExists($title, $id = 0)
    {
        $query = $this->where('title', $title)
                      ->where('deleted', 0);

        if ($id) {
            $query->where('id !=', $id);
        }

        $result = $query->findAll();

        return (!empty($result)) ? $result[0] : false;
    }
}
