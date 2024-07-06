<?php

namespace App\Models;

use CodeIgniter\Model;

class Ticket_types_model extends Model
{
    protected $table = 'ticket_types';
    protected $primaryKey = 'id'; 
    protected $useSoftDeletes = true; 
    protected $returnType = 'object'; 

    public function getDetails($options = [])
    {
        $builder = $this->select('*')
                        ->where('deleted', 0);

        if (!empty($options['id'])) {
            $builder->where('id', $options['id']);
        }

        return $builder->findAll();
    }
}
