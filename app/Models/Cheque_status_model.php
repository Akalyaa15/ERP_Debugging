<?php

namespace App\Models;

use CodeIgniter\Model;

class ChequeStatusModel extends Model
{
    protected $table = 'cheque_status';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['id', 'key_name', 'title', 'color', 'sort', 'deleted'];

    public function __construct()
    {
        parent::__construct();
    }

    public function getDetails($options = [])
    {
        $builder = $this->db->table($this->table)
                            ->where('deleted', 0);

        $id = get_array_value($options, "id");
        if ($id) {
            $builder->where('id', $id);
        }

        $builder->orderBy('sort', 'ASC');

        return $builder->get()->getResultArray();
    }

    public function getMaxSortValue()
    {
        return $this->selectMax('sort')
                    ->where('deleted', 0)
                    ->get()
                    ->getRowArray()['sort'] ?? 0;
    }
}
