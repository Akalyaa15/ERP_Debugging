<?php

namespace App\Models;

use CodeIgniter\Model;

class Email_templates_model extends Model
{
    protected $table = 'email_templates';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;

    protected $allowedFields = ['template_name', 'default_message', 'custom_message', 'email_subject', 'deleted'];

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $email_templates_table = $this->table;
        $where = "";
        $id = $options['id'] ?? null;
        if ($id) {
            $where .= " AND $email_templates_table.id=$id";
        }

        $builder = $this->db->table($email_templates_table);
        $builder->where('deleted', 0);
        $builder->where($where);
        return $builder->get();
    }

    public function get_final_template($template_name = "")
    {
        $email_templates_table = $this->table;

        $builder = $this->db->table($email_templates_table);
        $builder->select("$email_templates_table.default_message, $email_templates_table.custom_message, $email_templates_table.email_subject, 
            signature_template.custom_message AS signature_custom_message, signature_template.default_message AS signature_default_message");
        $builder->join("$email_templates_table AS signature_template", "signature_template.template_name='signature'", 'left');
        $builder->where("$email_templates_table.deleted", 0);
        $builder->where("$email_templates_table.template_name", $template_name);

        $result = $builder->get()->getRow();

        $info = new \stdClass();
        $info->subject = $result->email_subject;
        $info->message = $result->custom_message ? $result->custom_message : $result->default_message;
        $info->signature = $result->signature_custom_message ? $result->signature_custom_message : $result->signature_default_message;

        return $info;
    }
}
