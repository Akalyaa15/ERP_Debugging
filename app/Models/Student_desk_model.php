<?php

namespace App\Models;

use CodeIgniter\Model;

class Student_desk_model extends Model
{
    protected $table = 'student_desk';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true; 

    protected $returnType = 'object'; 
    protected $allowedFields = ['date', 'vap_category', 'other_fields']; 

    public function getDetails($options = [])
    {
        $builder = $this->select("$this->table.*, vap_category.title AS vap_category_title")
                        ->join('vap_category', 'vap_category.id = student_desk.vap_category', 'left')
                        ->where('deleted', 0);

        if (!empty($options['id'])) {
            $builder->where('id', $options['id']);
        }

        $startDate = get_array_value($options, "start_date");
        $endDate = get_array_value($options, "end_date");
        if ($startDate && $endDate) {
            $builder->where("date BETWEEN '$startDate' AND '$endDate'");
        }

        return $builder->findAll();
    }

    public function isStudentDeskEmailExists($email, $id = 0)
    {
        $builder = $this->select('id')
                        ->where('email', $email)
                        ->where('deleted', 0);

        if ($id > 0) {
            $builder->where('id !=', $id);
        }

        $result = $builder->get();

        return ($result->getNumRows() > 0) ? $result->getRow() : false;
    }
}
