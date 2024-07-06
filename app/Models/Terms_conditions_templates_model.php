<?php

namespace App\Models;

use CodeIgniter\Model;

class Terms_conditions_templates_model extends Model
{
    protected $table = 'terms_conditions_templates';
    protected $primaryKey = 'id'; // Adjust primary key if necessary
    protected $useSoftDeletes = true; // Enable soft deletes

    protected $returnType = 'object'; // Adjust return type as needed

    public function getDetails($options = [])
    {
        $builder = $this->select('*')
                        ->where('deleted', 0);

        if (!empty($options['id'])) {
            $builder->where('id', $options['id']);
        }

        return $builder->findAll();
    }

    public function getDefault()
    {
        return $this->where('deleted', 0)
                    ->where('is_default', 1)
                    ->findAll();
    }

    public function setZero()
    {
        return $this->set('is_default', 0)
                    ->update();
    }

    public function getFinalTemplate($templateName = "")
    {
        $builder = $this->select('default_message, custom_message, email_subject')
                        ->where('deleted', 0)
                        ->where('template_name', $templateName);

        $signatureBuilder = clone $builder;
        $signatureBuilder->where('template_name', 'signature')
                         ->select('custom_message AS signature_custom_message, default_message AS signature_default_message');

        $query = $builder->join('terms_conditions_templates AS signature_template', 'signature_template.template_name', 'signature')
                        ->findAll();

        $result = $query->getRow();

        $info = new \stdClass();
        $info->subject = $result->email_subject;
        $info->message = $result->custom_message ?: $result->default_message;
        $info->signature = $result->signature_custom_message ?: $result->signature_default_message;

        return $info;
    }
}
