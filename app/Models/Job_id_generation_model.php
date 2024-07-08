<?php

namespace App\Models;

use CodeIgniter\Model;

class JobIdGenerationModel extends Model
{
    protected $table = 'job_id_generation';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    public function getDetails($options = [])
    {
        $jobIdGenerationTable = $this->table;
        $vendorsTable = $this->db->dbprefix('vendors');
        $where = [];

        $id = $options['id'] ?? null;
        if ($id) {
            $where[] = "$jobIdGenerationTable.id = $id";
        }

        $jobId = $options['job_id'] ?? null;
        if ($jobId) {
            $where[] = "$jobIdGenerationTable.title = '$jobId'";
        }

        $groupId = $options['group_id'] ?? null;
        if ($groupId) {
            $where[] = "FIND_IN_SET('$groupId', $jobIdGenerationTable.vendor_id)";
        }

        $whereClause = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT $jobIdGenerationTable.*, 
                       (SELECT GROUP_CONCAT($vendorsTable.id) FROM $vendorsTable WHERE FIND_IN_SET($vendorsTable.id, $jobIdGenerationTable.vendor_id)) AS groups
                FROM $jobIdGenerationTable
                WHERE $jobIdGenerationTable.deleted = 0
                $whereClause";

        return $this->db->query($sql)->getResult();
    }

    public function getJobIdSuggestions($keyword = "", $dItem = "")
    {
        $jobIdGenerationTable = $this->table;

        $sql = "SELECT $jobIdGenerationTable.title
                FROM $jobIdGenerationTable
                WHERE $jobIdGenerationTable.deleted = 0  
                      AND $jobIdGenerationTable.title LIKE '%$keyword%'
                      AND $jobIdGenerationTable.title NOT IN ($dItem)
                LIMIT 30";

        return $this->db->query($sql)->getResult();
    }

    public function getJobIdInfoSuggestion($itemName = "")
    {
        $jobIdGenerationTable = $this->table;

        $sql = "SELECT $jobIdGenerationTable.*
                FROM $jobIdGenerationTable
                WHERE $jobIdGenerationTable.deleted = 0  
                      AND $jobIdGenerationTable.title LIKE '%$itemName%'
                ORDER BY id DESC
                LIMIT 1";

        return $this->db->query($sql)->getRow();
    }

    public function getItemSuggestions($s = "")
    {
        $purchaseOrdersTable = $this->db->dbprefix('purchase_orders');
        $vendorsTable = $this->db->dbprefix('vendors');

        $sql = "SELECT $vendorsTable.currency, $vendorsTable.country
                FROM $vendorsTable
                LEFT JOIN $purchaseOrdersTable ON $purchaseOrdersTable.vendor_id = $vendorsTable.id
                WHERE $vendorsTable.deleted = 0  
                      AND $purchaseOrdersTable.id = '$s'
                LIMIT 1";

        return $this->db->query($sql)->getRow();
    }

    public function isJobIdGenerationExists($title, $id = 0)
    {
        $result = $this->where('title', $title)
                        ->where('deleted', 0)
                        ->where('id !=', $id)
                        ->first();

        return $result ? $result : false;
    }
}
