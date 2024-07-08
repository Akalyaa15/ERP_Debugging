<?php

namespace App\Models;

use CodeIgniter\Model;

class ServiceIdGenerationModel extends Model
{
    protected $table = 'service_id_generation';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'id', 'title', 'associated_with_part_no', 'rate', 'deleted'
    ];

    public function getDetails($options = [])
    {
        $productIdGenerationTable = $this->db->prefixTable('service_id_generation');
        $partNoGenerationTable = $this->db->prefixTable('job_id_generation');
        $where = "";

        if (!empty($options['id'])) {
            $where = " AND $productIdGenerationTable.id=" . $this->db->escape($options['id']);
        }

        if (!empty($options['service_id'])) {
            $where .= " AND $productIdGenerationTable.title=" . $this->db->escape($options['service_id']);
        }

        $sql = "SELECT $productIdGenerationTable.*, $partNoGenerationTable.rate AS part_no_value
                FROM $productIdGenerationTable
                LEFT JOIN $partNoGenerationTable ON $partNoGenerationTable.id = $productIdGenerationTable.associated_with_part_no
                WHERE $productIdGenerationTable.deleted=0 $where";

        return $this->db->query($sql)->getResult();
    }

    public function getServiceIdSuggestion($keyword = "")
    {
        $productIdGenerationTable = $this->db->prefixTable('service_id_generation');
        $inventoryTable = $this->db->prefixTable('items');

        $sqls = "SELECT $inventoryTable.title
                 FROM $inventoryTable
                 WHERE $inventoryTable.deleted=0";

        $inventoryResult = $this->db->query($sqls)->getResult();

        $inventoryItems = [];
        if ($inventoryResult) {
            foreach ($inventoryResult as $inventory) {
                $inventoryItems[] = $inventory->title;
            }
        }

        $inventoryItemString = empty($inventoryItems) ? "('empty')" : "('" . implode("','", $inventoryItems) . "')";

        $sql = "SELECT $productIdGenerationTable.title
                FROM $productIdGenerationTable
                WHERE $productIdGenerationTable.deleted=0
                AND $productIdGenerationTable.title LIKE '%" . $this->db->escapeLikeString($keyword) . "%'
                AND $productIdGenerationTable.title NOT IN $inventoryItemString
                LIMIT 30";

        return $this->db->query($sql)->getResult();
    }

    public function getServiceIdInfoSuggestion($itemName = "")
    {
        $productIdGenerationTable = $this->db->prefixTable('service_id_generation');

        $sql = "SELECT $productIdGenerationTable.*
                FROM $productIdGenerationTable
                WHERE $productIdGenerationTable.deleted=0
                AND $productIdGenerationTable.title LIKE '%" . $this->db->escapeLikeString($itemName) . "%'
                ORDER BY id DESC
                LIMIT 1";

        $result = $this->db->query($sql);

        if ($result->getNumRows() > 0) {
            return $result->getRow();
        }

        return null;
    }

    public function isServiceIdGenerationExists($title, $id = 0)
    {
        $builder = $this->db->table($this->table);
        $builder->where('title', $title);
        $builder->where('deleted', 0);

        if ($id) {
            $builder->where('id !=', $id);
        }

        $result = $builder->get();

        if ($result->getNumRows() > 0) {
            return $result->getRow();
        }

        return false;
    }
}