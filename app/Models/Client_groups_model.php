<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientGroupsModel extends Model
{
    protected $table = 'client_groups';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['id', 'title', 'description', 'deleted'];

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

        return $builder->get()->getResultArray();
    }
}
