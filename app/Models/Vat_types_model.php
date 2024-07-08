<?php

namespace App\Models;
use CodeIgniter\Model;
class VatTypesModel extends Model
{
    protected $table = 'vat_types';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'name', 'rate', 'deleted'
    ];
    protected $returnType = 'object';

    public function getDetails($options = [])
    {
        $vatTypesTable = $this->db->prefixTable('vat_types');
        $where = "";

        if (!empty($options['id'])) {
            $where = " AND $vatTypesTable.id=" . $this->db->escape($options['id']);
        }

        $sql = "SELECT $vatTypesTable.*
                FROM $vatTypesTable
                WHERE $vatTypesTable.deleted=0 $where";

        return $this->db->query($sql)->getResult();
    }
}