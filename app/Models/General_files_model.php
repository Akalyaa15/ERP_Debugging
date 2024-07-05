<?php

namespace App\Models;

use CodeIgniter\Model;

class GeneralFilesModel extends Model {
    protected $table = 'general_files';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['client_id', 'vendor_id', 'user_id', 'company_id', 'uploaded_by', 'file_name', 'file_type', 'file_size', 'description', 'created_at', 'deleted'];
    protected $returnType = 'array';
    public function getDetails($options = []) {
        $builder = $this->builder($this->table);
        $builder->select("$this->table.*, CONCAT(users.first_name, ' ', users.last_name) AS uploaded_by_user_name, users.image AS uploaded_by_user_image, users.user_type AS uploaded_by_user_type");
        $builder->join('users', "users.id = $this->table.uploaded_by", 'left');
        $builder->where("$this->table.deleted", 0);

        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where("$this->table.id", $id);
        }

        $clientId = $options['client_id'] ?? null;
        if ($clientId) {
            $builder->where("$this->table.client_id", $clientId);
        }

        $vendorId = $options['vendor_id'] ?? null;
        if ($vendorId) {
            $builder->where("$this->table.vendor_id", $vendorId);
        }

        $userId = $options['user_id'] ?? null;
        if ($userId) {
            $builder->where("$this->table.user_id", $userId);
        }

        $companyId = $options['company_id'] ?? null;
        if ($companyId) {
            $builder->where("$this->table.company_id", $companyId);
        }

        $query = $builder->get();
        return $query->getResultArray();
    }

}
