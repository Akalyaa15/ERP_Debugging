<?php

namespace App\Models;

use CodeIgniter\Model;

class KycInfoModel extends Model
{
    protected $table = 'kyc_info';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    public function getDetails($options = [])
    {
        $kycInfoTable = $this->table;
        $where = [];

        $id = $options['id'] ?? null;
        if ($id) {
            $where[] = "$kycInfoTable.id = $id";
        }

        $userId = $options['user_id'] ?? null;
        if ($userId) {
            $where[] = "$kycInfoTable.user_id = $userId";
        }

        $whereClause = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT $kycInfoTable.*
                FROM $kycInfoTable
                WHERE $kycInfoTable.deleted = 0
                $whereClause";

        return $this->db->query($sql)->getResult();
    }
}