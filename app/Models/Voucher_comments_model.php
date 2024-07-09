<?php

namespace App\Models;

use CodeIgniter\Model;

class VoucherCommentsModel extends Model
{
    protected $table = 'voucher_comments';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'voucher_id', 'comment', 'created_by', 'created_at', 'deleted'
    ];
    protected $returnType = 'object';
    public function getDetails($options = [])
    {
        $voucherCommentsTable = $this->db->prefixTable('voucher_comments');
        $usersTable = $this->db->prefixTable('users');

        $where = "";
        $sort = "ASC";

        $id = get_array_value($options, "id");
        if ($id) {
            $where .= " AND $voucherCommentsTable.id = " . $this->db->escape($id);
        }

        $voucherId = get_array_value($options, "voucher_id");
        if ($voucherId) {
            $where .= " AND $voucherCommentsTable.voucher_id = " . $this->db->escape($voucherId);
        }

        $sortDescending = get_array_value($options, "sort_as_decending");
        if ($sortDescending) {
            $sort = "DESC";
        }

        $sql = "SELECT $voucherCommentsTable.*, CONCAT($usersTable.first_name, ' ', $usersTable.last_name) AS created_by_user, $usersTable.image AS created_by_avatar, $usersTable.user_type
                FROM $voucherCommentsTable
                LEFT JOIN $usersTable ON $usersTable.id = $voucherCommentsTable.created_by
                WHERE $voucherCommentsTable.deleted = 0 $where
                ORDER BY $voucherCommentsTable.created_at $sort";

        return $this->db->query($sql)->getResult();
    }
}
