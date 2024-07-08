<?php

namespace App\Models;

use CodeIgniter\Model;

class Estimate_requests_model extends Model
{
    protected $table = 'estimate_requests';

    public function __construct()
    {
        parent::__construct();
    }

    public function getDetails($options = [])
    {
        $estimate_requests_table = $this->table;
        $estimate_forms_table = 'estimate_forms';
        $clients_table = 'clients';
        $leads_table = 'leads';
        $users_table = 'users';

        $where = [];
        $id = $options['id'] ?? null;
        if ($id) {
            $where[] = "$estimate_requests_table.id = $id";
        }

        $client_id = $options['client_id'] ?? null;
        if ($client_id) {
            $where[] = "$estimate_requests_table.client_id = $client_id";
        }

        $lead_id = $options['lead_id'] ?? null;
        if ($lead_id) {
            $where[] = "$estimate_requests_table.lead_id = $lead_id";
        }

        $assigned_to = $options['assigned_to'] ?? null;
        if ($assigned_to) {
            $where[] = "$estimate_requests_table.assigned_to = $assigned_to";
        }

        $status = $options['status'] ?? null;
        if ($status) {
            $where[] = "$estimate_requests_table.status = '$status'";
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT $estimate_requests_table.*, $clients_table.company_name, $estimate_forms_table.title AS form_title, $leads_table.company_name AS lead_company_name, 
                CONCAT($users_table.first_name, ' ', $users_table.last_name) AS assigned_to_user, $users_table.image as assigned_to_avatar 
                FROM $estimate_requests_table
                LEFT JOIN $clients_table ON $clients_table.id = $estimate_requests_table.client_id
                LEFT JOIN $leads_table ON $leads_table.id = $estimate_requests_table.lead_id    
                LEFT JOIN $users_table ON $users_table.id = $estimate_requests_table.assigned_to
                LEFT JOIN $estimate_forms_table ON $estimate_forms_table.id = $estimate_requests_table.estimate_form_id
                WHERE $estimate_requests_table.deleted = 0 AND $whereClause";

        return $this->db->query($sql)->getResultArray();
    }
}
