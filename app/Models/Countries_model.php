<?php

namespace App\Models;

use CodeIgniter\Model;

class Countries_model extends Model
{
    protected $table = 'country';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true; // Enable soft deletes

    public function get_details($options = [])
    {
        $countries_table = $this->table;
        $vat_types_table = $this->db->prefixTable('vat_types'); // Use prefixTable for database prefix

        $id = $options['id'] ?? null;
        $numberCode = $options['numberCode'] ?? null;

        $builder = $this->db->table($countries_table);
        $builder->select("$countries_table.*, $vat_types_table.title as vat_name");
        $builder->join($vat_types_table, "$vat_types_table.id = $countries_table.vat_type", 'left');
        $builder->where('deleted', 0);

        if ($id) {
            $builder->where("$countries_table.id", $id);
        }

        if ($numberCode) {
            $builder->where("$countries_table.numberCode", $numberCode);
        }

        return $builder->get()->getResult();
    }

    public function get_item_suggestions_country_name($keyword = "", $keywords = "")
    {
        $countries_table = $this->table;
        $states_table = $this->db->prefixTable('states'); // Use prefixTable for database prefix

        $builder = $this->db->table($countries_table);
        $builder->select("$states_table.title, $states_table.id");
        $builder->join($states_table, "$states_table.country_code = $countries_table.numberCode", 'left');
        $builder->where("$countries_table.deleted", 0);
        $builder->where("$countries_table.id", $keywords);
        $builder->like("$states_table.title", $keyword);
        $builder->where("$states_table.deleted", 0);
        $builder->limit(500);

        return $builder->get()->getResult();
    }

    public function get_country_suggestion($keyword = "")
    {
        $countries_table = $this->table;

        $builder = $this->db->table($countries_table);
        $builder->select("countryName, id");
        $builder->like("countryName", $keyword);
        $builder->where("deleted", 0);
        $builder->limit(500);

        return $builder->get()->getResult();
    }

    public function get_country_info_suggestion($item_name = "")
    {
        $countries_table = $this->table;

        $builder = $this->db->table($countries_table);
        $builder->where("deleted", 0);
        $builder->where("id", $item_name);
        $builder->orderBy("id", "DESC");
        $builder->limit(1);

        $query = $builder->get();

        if ($query->getNumRows() > 0) {
            return $query->getRow();
        }

        return null;
    }

    public function get_country_code_suggestion($item_name = "")
    {
        $countries_table = $this->table;
        $vat_types_table = $this->db->prefixTable('vat_types'); // Use prefixTable for database prefix

        $builder = $this->db->table($countries_table);
        $builder->select("$countries_table.*, $vat_types_table.title as vat_name");
        $builder->join($vat_types_table, "$vat_types_table.id = $countries_table.vat_type", 'left');
        $builder->where("$countries_table.deleted", 0);
        $builder->where("$countries_table.id", $item_name);
        $builder->orderBy("id", "DESC");
        $builder->limit(1);

        $query = $builder->get();

        if ($query->getNumRows() > 0) {
            return $query->getRow();
        }

        return null;
    }

    public function get_country_id_excel($options = [])
    {
        $countries_table = $this->table;

        $countryName = $options['countryName'] ?? null;

        $builder = $this->db->table($countries_table);
        $builder->where('deleted', 0);

        if ($countryName) {
            $builder->where('numberCode', $countryName);
        }

        return $builder->get()->getResult();
    }

    public function is_country_iso_exists($iso)
    {
        $result = $this->where('iso', $iso)->where('deleted', 0)->get()->getRow();

        if ($result) {
            return $result;
        } else {
            return false;
        }
    }

    public function is_country_exists($numberCode)
    {
        $result = $this->where('numberCode', $numberCode)->where('deleted', 0)->get()->getRow();

        if ($result) {
            return $result;
        } else {
            return false;
        }
    }

    public function is_country_name_exists($countryName)
    {
        $result = $this->where('countryName', $countryName)->where('deleted', 0)->get()->getRow();

        if ($result) {
            return $result;
        } else {
            return false;
        }
    }

    public function get_country_annual_leave_info_suggestion($item_name = "")
    {
        $countries_table = $this->table;

        $builder = $this->db->table($countries_table);
        $builder->where("deleted", 0);
        $builder->where("numberCode", $item_name);
        $builder->orderBy("id", "DESC");
        $builder->limit(1);

        $query = $builder->get();

        if ($query->getNumRows() > 0) {
            return $query->getRow();
        }

        return null;
    }
}