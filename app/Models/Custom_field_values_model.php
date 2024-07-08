<?php

namespace App\Models;

use CodeIgniter\Model;

class Custom_field_values_model extends Model
{
    protected $table = 'custom_field_values';

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $custom_field_values_table = $this->table;
        $custom_fields_table = $this->db->prefixTable('custom_fields');

        $builder = $this->db->table($custom_field_values_table)
                            ->select("$custom_field_values_table.*, $custom_fields_table.title AS custom_field_title, $custom_fields_table.field_type AS custom_field_type, $custom_fields_table.sort")
                            ->join($custom_fields_table, "$custom_fields_table.id = $custom_field_values_table.custom_field_id")
                            ->where("$custom_field_values_table.deleted", 0);

        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where("$custom_fields_table.id", $id);
        }

        $related_to_type = $options['related_to_type'] ?? null;
        if ($related_to_type) {
            $builder->where("$custom_field_values_table.related_to_type", $related_to_type);
        }

        $related_to_id = $options['related_to_id'] ?? null;
        if ($related_to_id) {
            $builder->where("$custom_field_values_table.related_to_id", $related_to_id);
        }

        $show_in_invoice = $options['show_in_invoice'] ?? null;
        if ($show_in_invoice) {
            $builder->where("$custom_fields_table.show_in_invoice", 1);
        }

        $show_in_estimate = $options['show_in_estimate'] ?? null;
        if ($show_in_estimate) {
            $builder->where("$custom_fields_table.show_in_estimate", 1);
        }

        $builder->orderBy("$custom_fields_table.sort", 'ASC');

        return $builder->get()->getResultArray();
    }

    public function upsert($data)
    {
        $existing = $this->getOneWhere([
            "related_to_type" => $data["related_to_type"] ?? null,
            "related_to_id" => $data["related_to_id"] ?? null,
            "custom_field_id" => $data["custom_field_id"] ?? null,
            "deleted" => 0
        ]);

        $custom_field_info = model('App\Models\Custom_fields_model')->getOne($data["custom_field_id"] ?? null);

        $changes = [
            "field_type" => $custom_field_info->field_type ?? null,
            "title" => $custom_field_info->title ?? null,
        ];

        if ($existing) {
            // Update
            $save_id = $this->save($data, $existing['id']); // Update

            if ($save_id) {
                if ($existing['value'] != $data['value'] ?? null) {
                    // Updated, but has changed values
                    $changes["from"] = $existing['value'];
                    $changes["to"] = $data['value'] ?? null;
                    return ["operation" => "update", "save_id" => $save_id, "changes" => $changes];
                } else {
                    // Updated but changed the default input fields for the first time
                    return ["save_id" => $save_id, "changes" => $changes];
                }
            }
        } else {
            // Insert
            $save_id = $this->save($data); // Insert
            return ["operation" => "insert", "save_id" => $save_id, "changes" => $changes];
        }
    }
}
