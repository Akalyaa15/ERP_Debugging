<?php

namespace App\Models;

use CodeIgniter\Model;

class Task_status_model extends Model
{
    protected $table = 'task_status';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $builder = $this->db->table('task_status');
        $builder->select('*');

        if (!empty($options['id'])) {
            $builder->where('id', $options['id']);
        }

        $builder->where('deleted', 0)
                ->orderBy('sort', 'ASC');

        return $builder->get()->getResult();
    }

    public function get_max_sort_value()
    {
        $builder = $this->db->table('task_status');
        $builder->selectMax('sort', 'max_sort')
                ->where('deleted', 0);

        $result = $builder->get()->getRow();
        return ($result) ? $result->max_sort : 0;
    }
}
