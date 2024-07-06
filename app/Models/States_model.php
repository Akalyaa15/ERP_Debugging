<?php

namespace App\Models;

use CodeIgniter\Model;

class States_model extends Model
{
    protected $table = 'states';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true; 

    protected $allowedFields = ['state_code', 'title', 'country_code']; 

    public function getDetails($options = [])
    {
        $builder = $this->select("$this->table.*, country.countryName")
                        ->join('country', "states.country_code = country.numberCode", 'left')
                        ->where('states.deleted', 0);

        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where('states.id', $id);
        }

        return $builder->findAll();
    }

    public function getStateIdFromExcel($options = [])
    {
        $builder = $this->select('*')
                        ->where('deleted', 0);

        $state = $options['title'] ?? null;
        if ($state) {
            $builder->where('state_code', $state);
        }

        return $builder->findAll();
    }

    public function isStateExists($state_code)
    {
        return $this->where('state_code', $state_code)
                    ->where('deleted', 0)
                    ->first();
    }

    public function isStateNameExists($state_name)
    {
        return $this->where('title', $state_name)
                    ->where('deleted', 0)
                    ->first();
    }
}
