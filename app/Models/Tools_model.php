<?php

namespace App\Models;

use CodeIgniter\Model;

class Tools_model extends Model
{
    protected $table = 'tools';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'id',
        'user_id',
        'company',
        'vendor_company',
        'title',
    ];

    protected $useSoftDeletes = true; // Enable soft deletes

    protected $returnType = 'object'; // Adjust return type as needed

    public function getDetails($options = [])
    {
        $builder = $this->select("$this->table.*, CONCAT(users.first_name, ' ', users.last_name) AS linked_user_name, clients.company_name AS client_company, vendors.company_name AS vendor_company")
                        ->join('clients', 'clients.id = tools.company', 'left')
                        ->join('vendors', 'vendors.id = tools.vendor_company', 'left')
                        ->join('users', 'users.id = tools.user_id', 'left')
                        ->where('tools.deleted', 0); // Assuming 'deleted' column is used for soft deletes

        if (!empty($options['id'])) {
            $builder->where('tools.id', $options['id']);
        }

        if (!empty($options['user_id'])) {
            $builder->where('tools.user_id', $options['user_id']);
        }

        if (!empty($options['client_id'])) {
            $builder->where('tools.company', $options['client_id']);
        }

        if (!empty($options['vendor_id'])) {
            $builder->where('tools.vendor_company', $options['vendor_id']);
        }

        return $builder->findAll();
    }

    public function getItemSuggestion($keyword = "")
    {
        $builder = $this->select('title')
                        ->like('title', $keyword)
                        ->where('deleted', 0)
                        ->limit(30);

        return $builder->get()->getResult();
    }

    public function getItemSuggestions($keyword = "", $excludeItems = [])
    {
        $builder = $this->select('title')
                        ->like('title', $keyword)
                        ->where('deleted', 0)
                        ->whereNotIn('title', $excludeItems)
                        ->limit(30);

        return $builder->get()->getResult();
    }

    public function getItemInfoSuggestion($itemName = "")
    {
        $builder = $this->select('*')
                        ->like('title', $itemName)
                        ->where('deleted', 0)
                        ->orderBy('id', 'DESC')
                        ->limit(1);

        $query = $builder->get();

        if ($query->getNumRows() > 0) {
            return $query->getRow();
        }

        return null;
    }
}
