<?php

namespace App\Models;

use CodeIgniter\Model;

class Department_model extends Model
{
    protected $table = 'department';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['department_code', 'title', 'deleted'];

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $department_table = $this->table;
        $where = "";
        $id = $options['id'] ?? null;
        if ($id) {
            $where .= " AND $department_table.id=$id";
        }
        $department_code = $options['department_code'] ?? null;
        if ($department_code) {
            $where .= " AND $department_table.department_code='$department_code'";
        }
        $builder = $this->db->table($department_table);
        $builder->select('*');
        $builder->where('deleted', 0);
        $builder->where($where);
        return $builder->get();
    }

    public function is_department_exists($department_code)
    {
        return $this->where('department_code', $department_code)
                    ->where('deleted', 0)
                    ->findAll();
    }

    public function is_department_name_exists($department_name)
    {
        return $this->where('title', $department_name)
                    ->where('deleted', 0)
                    ->findAll();
    }
}
