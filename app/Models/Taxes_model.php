<?php

namespace App\Models;

use CodeIgniter\Model;

class Taxes_model extends Model
{
    protected $table = 'taxes';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'id',
        'name',
        'rate',
        'description',
    ];

    public function getDetails($options = [])
    {
        $taxesTable = $this->table;
        
        $id = $options['id'] ?? null;
        if ($id) {
            $this->where('id', $id);
        }

        $this->where('deleted', 0);

        return $this->findAll();
    }
}
