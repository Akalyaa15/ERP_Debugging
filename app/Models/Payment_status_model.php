<?php

namespace App\Models;

use CodeIgniter\Model;

class Payment_status_model extends Model
{
    protected $table = 'payment_status';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $id = $options['id'] ?? null;
        $where = $id ? "id=$id" : '';

        return $this->where('deleted', 0)->where($where)->findAll();
    }
}