<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductCategoriesModel extends Model
{
    protected $table = 'product_categories';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    public function __construct()
    {
        parent::__construct();
    }

    public function getDetails($options = [])
    {
        $where = [];
        $id = $options['id'] ?? null;
        if ($id) {
            $where['id'] = $id;
        }

        $this->select('*');
        $this->where($where);
        $this->where('deleted', 0);

        return $this->get()->getResult();
    }

    public function isProductCategoryExists($title, $id = 0)
    {
        $result = $this->where('title', $title)
                       ->where('deleted', 0)
                       ->findAll();

        if (!empty($result) && $result[0]->id != $id) {
            return $result[0];
        } else {
            return false;
        }
    }
}
