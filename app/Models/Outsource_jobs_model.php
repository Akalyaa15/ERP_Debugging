<?php

namespace App\Models;

use CodeIgniter\Model;

class Outsource_jobs_model extends Model
{
    protected $table = 'outsource_jobs';
    protected $primaryKey = 'id';

    public function getDetails($options = [])
    {
        $where = [];
        $id = $options['id'] ?? null;
        if ($id) {
            $where['id'] = $id;
        }

        $query = $this->where('deleted', 0)
                      ->where($where)
                      ->findAll();

        return $query;
    }

    public function isOutsourceJobExists($title, $id = 0)
    {
        $result = $this->where('title', $title)
                       ->where('deleted', 0)
                       ->findAll();

        if ($result && count($result) > 0) {
            foreach ($result as $row) {
                if ($row['id'] != $id) {
                    return $row;
                }
            }
        }
        return false;
    }
}
