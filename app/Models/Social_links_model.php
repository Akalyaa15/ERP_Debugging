<?php

namespace App\Models;

use CodeIgniter\Model;

class SocialLinksModel extends Model
{
    protected $table = 'social_links';
    protected $primaryKey = 'id'; 
    protected $useSoftDeletes = true; 
    public function getDetails($options = [])
    {
        $builder = $this->select('*')
                        ->where('deleted', 0);

        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where('id', $id);
        }

        return $builder->findAll();
    }
}
