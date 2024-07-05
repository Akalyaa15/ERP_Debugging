<?php

namespace App\Models;

use CodeIgniter\Model;

class ChequeHandlerModel extends Model
{
    protected $table = 'cheque_handler';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'id',
        'status_id',
        'issue_date',
        'member_id',
        'member_type',
        'bank_name',
        'cheque_category_id',
        'deleted'
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function getDetails($options = [])
    {
        $builder = $this->db->table($this->table)
                            ->select("$this->table.*, cheque_status.key_name AS status_key_name, cheque_status.title AS status_title, cheque_status.color AS status_color, bank_list.title AS bank_name, cheque_categories.title AS cheque_category")
                            ->join('cheque_status', "$this->table.status_id = cheque_status.id", 'left')
                            ->join('bank_list', "$this->table.bank_name = bank_list.id", 'left')
                            ->join('cheque_categories', "$this->table.cheque_category_id = cheque_categories.id", 'left')
                            ->where("$this->table.deleted", 0);

        $id = get_array_value($options, "id");
        if ($id) {
            $builder->where("$this->table.id", $id);
        }

        $statusId = get_array_value($options, "status_id");
        if ($statusId) {
            $builder->where("$this->table.status_id", $statusId);
        }

        $startDate = get_array_value($options, "start_date");
        $endDate = get_array_value($options, "end_date");
        if ($startDate && $endDate) {
            $builder->where("$this->table.issue_date BETWEEN '$startDate' AND '$endDate'");
        }

        $userId = get_array_value($options, "user_id");
        $clientId = get_array_value($options, "client_id");
        $vendorId = get_array_value($options, "vendor_id");
        $otherId = get_array_value($options, "other_id");

        if ($userId) {
            $builder->where("$this->table.member_id", $userId)
                    ->whereIn("$this->table.member_type", ['tm', 'om']);
        }

        if ($clientId) {
            $builder->where("$this->table.member_id", $clientId)
                    ->where("$this->table.member_type", 'clients');
        }

        if ($vendorId) {
            $builder->where("$this->table.member_id", $vendorId)
                    ->where("$this->table.member_type", 'vendors');
        }

        if ($otherId) {
            $builder->where("$this->table.id", $otherId)
                    ->where("$this->table.member_type", 'others');
        }

        return $builder->get()->getResultArray();
    }
}
