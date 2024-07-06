<?php

class Vendors_invoice_payments_list_model extends Crud_model {

    private $table = null;

    function __construct() {
        $this->table = 'vendors_invoice_payments_list';
        parent::__construct($this->table);
    }

    function get_details($options = array()) {
        $checklist_items_table = $this->db->dbprefix("vendors_invoice_payments_list");

        $where = "";

        $task_id = get_array_value($options, "task_id");
        if ($task_id) {
            $where .= " AND $checklist_items_table.task_id=$task_id";
        }

        $sql = "SELECT $checklist_items_table.*, IF($checklist_items_table.sort!=0, $checklist_items_table.sort, $checklist_items_table.id) AS new_sort
        FROM $checklist_items_table
        WHERE $checklist_items_table.deleted=0 $where
        ORDER BY new_sort ASC";
        return $this->db->query($sql);
    }

}
