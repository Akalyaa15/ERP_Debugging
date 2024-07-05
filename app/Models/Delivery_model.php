<?php

namespace App\Models;

use CodeIgniter\Model;

class Delivery_model extends Model
{
    protected $table = 'delivery';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['client_id', 'invoice_for_dc', 'estimate_date', 'status', 'deleted', 'dc_type_id', 'dispatched_through'];

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $estimates_table = $this->table;
        $clients_table = 'users'; // Assuming 'users' is the table name for clients

        $dc_types_table = 'dc_types';
        $mode_of_dispatch_table = 'mode_of_dispatch';

        $where = "";
        $id = $options['id'] ?? null;
        if ($id) {
            $where .= " AND $estimates_table.id=$id";
        }

        $client_id = $options['client_id'] ?? null;
        if ($client_id) {
            $where .= " AND $estimates_table.client_id=$client_id";
        }

        $invoice_no = $options['invoice_for_dc'] ?? null;
        if ($invoice_no) {
            $where .= " AND $estimates_table.invoice_for_dc='$invoice_no'";
        }

        $start_date = $options['start_date'] ?? null;
        $end_date = $options['end_date'] ?? null;
        if ($start_date && $end_date) {
            $where .= " AND ($estimates_table.estimate_date BETWEEN '$start_date' AND '$end_date') ";
        }

        $status = $options['status'] ?? null;
        if ($status) {
            $where .= " AND $estimates_table.status='$status'";
        }

        $exclude_draft = $options['exclude_draft'] ?? null;
        if ($exclude_draft) {
            $where .= " AND $estimates_table.status!='draft' ";
        }

        // Prepare custom field binding query
        $custom_fields = $options['custom_fields'] ?? null;
        $custom_field_query_info = $this->prepare_custom_field_query_string("delivery", $custom_fields, $estimates_table);
        $select_custom_fields = $custom_field_query_info['select_string'];
        $join_custom_fields = $custom_field_query_info['join_string'];

        $builder = $this->db->table($estimates_table);
        $builder->select("$estimates_table.*, $clients_table.first_name, $clients_table.last_name, $clients_table.id as project_title, $dc_types_table.title as dc_type_title, $mode_of_dispatch_table.title as mode_of_dispatch");
        $builder->join($clients_table, "$clients_table.id = $estimates_table.client_id", 'left');
        $builder->join($dc_types_table, "$dc_types_table.id = $estimates_table.dc_type_id", 'left');
        $builder->join($mode_of_dispatch_table, "$mode_of_dispatch_table.id = $estimates_table.dispatched_through", 'left');
        $builder->where("$estimates_table.deleted", 0);
        $builder->where($where);

        return $builder->get();
    }

    public function get_id($options = [])
    {
        $estimates_table = $this->table;

        $builder = $this->db->table($estimates_table);
        $builder->select("$estimates_table.id");
        $builder->orderBy("$estimates_table.id", 'DESC');
        $builder->limit(1);

        return $builder->get()->getRow();
    }

    public function is_estimate_no_exists($dc_no, $id = 0)
    {
        $result = $this->where('dc_no', $dc_no)->where('deleted', 0)->findAll();

        if ($result && $result[0]->id != $id) {
            return $result[0];
        } else {
            return false;
        }
    }

    public function get_last_estimate_id_exists()
    {
        $estimates_table = $this->table;

        $builder = $this->db->table($estimates_table);
        $builder->orderBy('id', 'DESC');
        $builder->limit(1);

        return $builder->get()->getRow();
    }
}
