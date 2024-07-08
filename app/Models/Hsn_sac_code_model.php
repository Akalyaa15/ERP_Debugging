<?php
namespace App\Models;
use CodeIgniter\Model;
class HsnSacCodeModel extends Model {
protected $table = 'hsn_sac_code';
protected $primaryKey = 'id';
protected $useSoftDeletes = true;
protected $allowedFields = ['hsn_code', 'hsn_description', 'deleted'];
protected $returnType = 'array';
public function getDetails($options = []) {
        $id = $options['id'] ?? null;
        $builder = $this->builder($this->table);
        $builder->where('deleted', 0);
        if ($id) {
            $builder->where('id', $id);
        }
        return $builder->get()->getResultArray();
    }
   public function getItemSuggestion($keyword = "") {
        return $this->like('hsn_code', $keyword)
                    ->where('deleted', 0)
                    ->limit(30)
                    ->findAll();
    }
   public function getItemInfoSuggestion($item_name = "") {
        return $this->like('hsn_code', $item_name)
                    ->where('deleted', 0)
                    ->orderBy('id', 'DESC')
                    ->first();
    }

    public function getItemFreightSuggestion($item_name = "") {
        return $this->like('hsn_code', $item_name)
                    ->where('deleted', 0)
                    ->orderBy('id', 'DESC')
                    ->first();
    }

    public function getFreightSuggestion($keyword = "") {
        return $this->like('hsn_code', $keyword)
                    ->where('deleted', 0)
                    ->limit(30)
                    ->findAll();
    }
    public function isHsnCodeExists($hsn_code, $id = 0) {
        $result = $this->where(['hsn_code' => $hsn_code, 'deleted' => 0])
                       ->findAll();

        if ($result && $result[0]['id'] != $id) {
            return $result[0];
        }
        return false;
    }
}
