<?php

namespace App\Models;

use CodeIgniter\Model;

class BankNameModel extends Model
{
    protected $table = 'bank_list';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['id', 'title', 'account_number', 'deleted'];

    public function __construct()
    {
        parent::__construct();
    }

    public function getDetails($options = [])
    {
        $builder = $this->db->table($this->table)
                            ->where('deleted', 0);

        $id = get_array_value($options, "id");
        if ($id) {
            $builder->where('id', $id);
        }

        return $builder->get()->getResultArray();
    }

    public function getAccountNumberSuggestions($id)
    {
        $builder = $this->db->table($this->table)
                            ->select('GROUP_CONCAT(account_number) as account_number_groups')
                            ->where('deleted', 0)
                            ->where('id', $id);

        return $builder->get()->getRow()->account_number_groups;
    }

    public function getItemAccountNumberSuggestions($keywords = "", $bankId = "")
    {
        $builder = $this->db->table($this->table)
                            ->select('account_number')
                            ->where('deleted', 0)
                            ->where('id', $bankId)
                            ->like('account_number', $keywords)
                            ->limit(500);

        return $builder->get()->getRow();
    }

    public function isBankNameExists($bankName)
    {
        $query = $this->where('title', $bankName)
                      ->where('deleted', 0)
                      ->findAll();

        return !empty($query) ? $query[0] : false;
    }
}
