<?php

namespace App\Models;

use CodeIgniter\Model;

class Custom_fields_model extends Model
{
    protected $table = 'custom_fields';

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $custom_fields_table = $this->table;

        $builder = $this->db->table($custom_fields_table)
                            ->select("$custom_fields_table.*")
                            ->where("$custom_fields_table.deleted", 0);

        $id = get_array_value($options, "id");
        if ($id) {
            $builder->where("$custom_fields_table.id", $id);
        }

        $related_to = get_array_value($options, "related_to");
        if ($related_to) {
            $builder->where("$custom_fields_table.related_to", $related_to);
        }

        $show_in_table = get_array_value($options, "show_in_table");
        if ($show_in_table) {
            $builder->where("$custom_fields_table.show_in_table", 1);
        }

        $show_in_invoice = get_array_value($options, "show_in_invoice");
        if ($show_in_invoice) {
            $builder->where("$custom_fields_table.show_in_invoice", 1);
        }

        $show_in_estimate = get_array_value($options, "show_in_estimate");
        if ($show_in_estimate) {
            $builder->where("$custom_fields_table.show_in_estimate", 1);
        }

        $builder->orderBy("$custom_fields_table.sort", 'ASC');

        return $builder->get()->getResult();
    }

    public function get_max_sort_value($related_to = "")
    {
        $custom_fields_table = $this->table;

        $builder = $this->db->table($custom_fields_table)
                            ->selectMax('sort as sort')
                            ->where("$custom_fields_table.deleted", 0)
                            ->where("$custom_fields_table.related_to", $related_to);

        $result = $builder->get()->getRow();
        return $result ? $result->sort : 0;
    }

    public function get_combined_details($related_to, $related_to_id = 0, $is_admin = 0, $user_type = "")
    {
        $custom_fields_table = $this->table;
        $custom_field_values_table = $this->db->dbprefix('custom_field_values');

        $builder = $this->db->table($custom_fields_table)
                            ->select("$custom_fields_table.*, $custom_field_values_table.id AS custom_field_values_id, $custom_field_values_table.value")
                            ->join($custom_field_values_table, "$custom_fields_table.id = $custom_field_values_table.custom_field_id AND $custom_field_values_table.deleted = 0 AND $custom_field_values_table.related_to_id = $related_to_id", 'left')
                            ->where("$custom_fields_table.deleted", 0)
                            ->where("$custom_fields_table.related_to", $related_to);

        // Check visibility permissions
        if (!$is_admin) {
            $builder->where("$custom_fields_table.visible_to_admins_only", 0);
        }

        // Check client visibility
        if ($user_type === "client") {
            $builder->where("$custom_fields_table.hide_from_clients", 0);
        }

        $builder->orderBy("$custom_fields_table.sort", 'ASC');

        return $builder->get()->getResult();
    }

    public function get_custom_field_headers_for_table($related_to, $is_admin = 0, $user_type = "")
    {
        $custom_fields_for_table = $this->get_available_fields_for_table($related_to, $is_admin, $user_type);

        $json_string = "";
        foreach ($custom_fields_for_table as $column) {
            $json_string .= ',' . '{"title":"' . $column->title . '"}';
        }

        return $json_string;
    }

    public function get_available_fields_for_table($related_to, $is_admin = 0, $user_type = "")
    {
        $custom_fields_table = $this->table;

        $builder = $this->db->table($custom_fields_table)
                            ->select('id, title, field_type')
                            ->where("$custom_fields_table.related_to", $related_to)
                            ->where("$custom_fields_table.show_in_table", 1)
                            ->where("$custom_fields_table.deleted", 0);

        // Check visibility permissions
        if (!$is_admin) {
            $builder->where("$custom_fields_table.visible_to_admins_only", 0);
        }

        // Check client visibility
        if ($user_type === "client") {
            $builder->where("$custom_fields_table.hide_from_clients", 0);
        }

        $builder->orderBy("$custom_fields_table.sort", 'ASC');

        return $builder->get()->getResult();
    }
}
