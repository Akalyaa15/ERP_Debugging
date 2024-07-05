<?php

namespace App\Models;

use CodeIgniter\Model;

class BranchesModel extends Model
{
    protected $table = 'branches';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['id', 'branch_code', 'company_name', 'buid', 'deleted'];

    public function __construct()
    {
        parent::__construct();
    }

    public function getDetails($options = [])
    {
        $builder = $this->db->table($this->table)
                            ->select("$this->table.*, companys.company_name as company")
                            ->join('companys', "companys.cr_id = $this->table.company_name", 'left')
                            ->where("$this->table.deleted", 0);

        $id = get_array_value($options, "id");
        if ($id) {
            $builder->where("$this->table.id", $id);
        }

        $branchCode = get_array_value($options, "branch_code");
        if ($branchCode) {
            $builder->where("$this->table.branch_code", $branchCode);
        }

        $companyName = get_array_value($options, "company_name");
        if ($companyName) {
            $builder->where("$this->table.company_name", $companyName);
        }

        $buid = get_array_value($options, "buid");
        if ($buid) {
            $builder->where("$this->table.buid", $buid);
        }

        return $builder->get()->getResultArray();
    }

    public function isBranchExists($branchCode)
    {
        return $this->where('branch_code', $branchCode)
                    ->where('deleted', 0)
                    ->findAll();
    }

    public function branchCount($companyName)
    {
        return $this->where('company_name', $companyName)
                    ->findAll();
    }

    public function isBranchNameExists($branchName, $companyName)
    {
        return $this->where('title', $branchName)
                    ->where('company_name', $companyName)
                    ->where('deleted', 0)
                    ->findAll();
    }

    public function getItemSuggestionsCountryName($keyword = "", $keywords = "")
    {
        $itemsTable = $this->db->table('country');
        $statesTable = $this->db->table('states');

        $query = $statesTable->select('states.title, states.id')
                             ->join('country', "states.country_code = country.numberCode", 'left')
                             ->where('country.deleted', 0)
                             ->where('country.numberCode', $keywords)
                             ->like('states.title', $keyword)
                             ->where('states.deleted', 0)
                             ->limit(500)
                             ->get();

        return $query->getResult();
    }

    public function getItemSuggestionsBranchName($keyword = "", $keywords = "")
    {
        $itemsTable = $this->db->table('country');
        $statesTable = $this->db->table('branches');

        $query = $statesTable->select('branches.title, branches.id, branches.branch_code, branches.buid, branches.company_name')
                             ->join('country', "branches.company_setup_country = country.numberCode", 'left')
                             ->where('country.deleted', 0)
                             ->where('country.numberCode', $keywords)
                             ->like('branches.title', $keyword)
                             ->where('branches.deleted', 0)
                             ->limit(500)
                             ->get();

        return $query->getResult();
    }

    public function getCompanyItemSuggestionsBranchName($keyword = "")
    {
        $statesTable = $this->db->table('branches');

        $query = $statesTable->select('branches.title, branches.id, branches.branch_code')
                             ->where('branches.company_name', $keyword)
                             ->where('branches.deleted', 0)
                             ->limit(500)
                             ->get();

        return $query->getResult();
    }
}
