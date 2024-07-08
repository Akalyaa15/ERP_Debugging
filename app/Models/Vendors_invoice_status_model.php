<?php

namespace App\Models;

use CodeIgniter\Model;

class VendorsInvoiceStatusModel extends CrudModel
{
    protected $table = 'vendors_invoice_status';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'title', 'key_name', 'color', 'sort', 'deleted'
    ];
    protected $returnType = 'object';

    public function getDetails($options = [])
    {
        $taskStatusTable = $this->db->prefixTable('vendors_invoice_status');

        $where = "";
        $id = get_array_value($options, "id");
        if ($id) {
            $where = " AND $taskStatusTable.id = " . $this->db->escape($id);
        }

        $sql = "SELECT *
                FROM $taskStatusTable
                WHERE deleted = 0 $where
                ORDER BY sort ASC";

        return $this->db->query($sql)->getResult();
    }

    public function getMaxSortValue()
    {
        $taskStatusTable = $this->db->prefixTable('vendors_invoice_status');

        $sql = "SELECT MAX(sort) as sort
                FROM $taskStatusTable
                WHERE deleted = 0";

        $result = $this->db->query($sql);
        if ($result->num_rows()) {
            return $result->getRow()->sort;
        } else {
            return 0;
        }
    }
}
