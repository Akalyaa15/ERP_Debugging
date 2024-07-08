<?php
namespace App\Models;
use CodeIgniter\Model;
class Voucher_types_model extends Model
{
    protected $table = 'voucher_types';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    public function get_details($options = [])
    {
        $voucher_types_table = $this->table;
        $where = "";
        $id = $options['id'] ?? null;
        if ($id) {
            $where = " AND $voucher_types_table.id=$id";
        }

        $sql = "SELECT $voucher_types_table.*
        FROM $voucher_types_table
        WHERE $voucher_types_table.deleted=0 $where";
        
        return $this->db->query($sql)->getResult();
    }
}