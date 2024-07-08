<?php

namespace App\Models;

use CodeIgniter\Model;

class ManufacturerModel extends Model
{
    protected $table = 'manufacturer';
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

    public function isManufacturerListExists($title, $id = 0)
    {
        $result = $this->where('title', $title)
                       ->where('deleted', 0)
                       ->where('id !=', $id)
                       ->findAll();

        return count($result) > 0 ? $result[0] : false;
    }
}
