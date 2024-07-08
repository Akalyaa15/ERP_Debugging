<?php

namespace App\Models;

use CodeIgniter\Model;

class VendorGroupsModel extends Model
{
    protected $table = 'vendor_groups';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'name', 'description', 'deleted'
    ];
    protected $returnType = 'object';

    public function getDetails($options = [])
    {
        $vendorGroupsTable = $this->db->prefixTable('vendor_groups');
        $where = "";

        if (!empty($options['id'])) {
            $where = " AND $vendorGroupsTable.id=" . $this->db->escape($options['id']);
        }

        $sql = "SELECT $vendorGroupsTable.*
                FROM $vendorGroupsTable
                WHERE $vendorGroupsTable.deleted=0 $where";

        return $this->db->query($sql)->getResult();
    }
}
