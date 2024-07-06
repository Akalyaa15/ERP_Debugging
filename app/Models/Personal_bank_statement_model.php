<?php

namespace App\Models;

use CodeIgniter\Model;

class PersonalBankStatementModel extends Model
{
    protected $table = 'personal_bank_statement';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    public function __construct()
    {
        parent::__construct();
    }

    public function getDetails($options = [])
    {
        $builder = $this->db->table($this->table);
        $builder->select('*');

        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where('id', $id);
        }

        $user_id = $options['user_id'] ?? null;
        if ($user_id) {
            $builder->where('user_id', $user_id);
        }

        $start_date = $options['start_date'] ?? null;
        $end_date = $options['end_date'] ?? null;
        if ($start_date && $end_date) {
            $builder->where("ValueName BETWEEN '$start_date' AND '$end_date'");
        }

        $builder->where('deleted', 0);
        return $builder->get()->getResult();
    }

    public function select()
    {
        return $this->orderBy('id', 'DESC')
                    ->findAll();
    }

    public function insertBatch($data)
    {
        return $this->insertBatch($data);
    }
}
