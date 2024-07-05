<?php

namespace App\Models;

use CodeIgniter\Model;

class CountryEarningsModel extends Model
{
    protected $table = 'country_earnings';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['field1', 'field2', 'field3']; // replace with actual fields
    protected $returnType = 'array';

    public function getDetails($options = [])
    {
        $builder = $this->db->table($this->table);
        $builder->where('deleted', 0);

        if (!empty($options['id'])) {
            $builder->where('id', $options['id']);
        }

        if (!empty($options['country_id'])) {
            $builder->where('country_id', $options['country_id']);
        }

        return $builder->get()->getResultArray();
    }

    public function getDetailss($options = [])
    {
        $builder = $this->db->table($this->table);
        $builder->where('deleted', 0);
        $builder->where('status', 'active');
        $builder->where('key_name !=', 'basic_salary');

        if (!empty($options['id'])) {
            $builder->where('id !=', $options['id']);
        }

        if (!empty($options['country_id'])) {
            $builder->where('country_id', $options['country_id']);
        }

        return $builder->get()->getResultArray();
    }

    public function insertBatch($data)
    {
        return $this->insertBatch($data);
    }
}
