<?php

namespace App\Models;

use CodeIgniter\Model;

class Designation_model extends Model
{
    protected $table = 'designation';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['department_code', 'designation_code', 'title', 'deleted'];

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $designation_table = $this->table;
        $department_table = 'department'; // Assuming 'department' is another model

        $where = "";
        $id = $options['id'] ?? null;
        if ($id) {
            $where .= " AND $designation_table.id=$id";
        }

        $designation_code = $options['designation_code'] ?? null;
        if ($designation_code) {
            $where .= " AND $designation_table.designation_code='$designation_code'";
        }

        $department_code = $options['department_code'] ?? null;
        if ($department_code) {
            $where .= " AND $designation_table.department_code='$department_code'";
        }

        $builder = $this->db->table($designation_table);
        $builder->select("$designation_table.*, $department_table.title as department_title");
        $builder->join($department_table, "$department_table.department_code = $designation_table.department_code");
        $builder->where("$designation_table.deleted", 0);
        $builder->where("$department_table.deleted", 0);
        $builder->where($where);
        return $builder->get();
    }

    public function is_designation_exists($department_code, $designation_code = 0)
    {
        return $this->where('department_code', $department_code)
                    ->where('designation_code', $designation_code)
                    ->where('deleted', 0)
                    ->findAll();
    }

    public function get_designation_details($dep_code = "")
    {
        $items_table = $this->table;

        $builder = $this->db->table($items_table);
        $builder->select('*');
        $builder->where('deleted', 0);
        $builder->where('department_code', $dep_code);
        $builder->orderBy('id');

        return $builder->get()->getResult();
    }
}
