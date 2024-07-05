<?php

namespace App\Models;

use CodeIgniter\Model;

class Earnings_model extends Model
{
    protected $table = 'earnings';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['field_name', 'status', 'key_name', 'deleted']; // Adjust as per your actual field names

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $earnings_table = $this->table;
        $where = "";
        $id = $options['id'] ?? null;
        if ($id) {
            $where .= " AND $earnings_table.id=$id";
        }

        $builder = $this->db->table($earnings_table);
        $builder->where('deleted', 0);
        $builder->where($where);
        return $builder->get();
    }

    public function get_detailss($options = [])
    {
        $earnings_table = $this->table;
        $where = "";
        $id = $options['id'] ?? null;
        if ($id) {
            $where .= " AND $earnings_table.id != $id";
        }

        $builder = $this->db->table($earnings_table);
        $builder->where('deleted', 0);
        $builder->where('status', 'active');
        $builder->where("key_name != 'basic_salary'");
        $builder->where($where);
        return $builder->get();
    }
}