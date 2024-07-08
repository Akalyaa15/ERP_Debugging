<?php

namespace App\Models;

use CodeIgniter\Model;

class LeaveApplicationsModel extends Model
{
    protected $table = 'leave_applications';
    protected $primaryKey = 'id';
    protected $returnType = 'object'; 

    public function __construct()
    {
        parent::__construct();
    }

    public function getDetailsInfo($id = 0)
    {
        $builder = $this->db->table($this->table);
        $builder->select("$this->table.*, CONCAT(applicant.first_name, ' ', applicant.last_name) AS applicant_name, applicant.image AS applicant_avatar, applicant.job_title, applicant.created_at,
                        CONCAT(checker.first_name, ' ', checker.last_name) AS checker_name, checker.image AS checker_avatar,
                        CONCAT(line.first_name, ' ', line.last_name) AS line_manager, line.image AS line_manager_avatar,
                        CONCAT(alter.first_name, ' ', alter.last_name) AS alter_name, alter.image AS alter_avatar,
                        leave_types.title AS leave_type_title, leave_types.color AS leave_type_color");
        $builder->join('users AS applicant', "applicant.id = $this->table.applicant_id", 'left');
        $builder->join('users AS line', "line.id = $this->table.line_manager", 'left');
        $builder->join('users AS alter', "alter.id = $this->table.alternate_id", 'left');
        $builder->join('users AS checker', "checker.id = $this->table.checked_by", 'left');
        $builder->join('leave_types', "leave_types.id = $this->table.leave_type_id", 'left');
        $builder->where("$this->table.deleted", 0);
        $builder->where("$this->table.id", $id);
        return $builder->get()->getRow();
    }

    public function getList($options = [])
    {
        $builder = $this->db->table($this->table);
        $builder->select("$this->table.id, $this->table.start_date, $this->table.end_date, $this->table.total_hours,
                        $this->table.total_days, $this->table.applicant_id, $this->table.created_at, $this->table.status,
                        CONCAT(users.first_name, ' ', users.last_name) AS applicant_name, users.image AS applicant_avatar, users.user_type AS applicant_user_type,
                        leave_types.title AS leave_type_title, leave_types.color AS leave_type_color");
        $builder->join('users', "users.id = $this->table.applicant_id", 'left');
        $builder->join('leave_types', "leave_types.id = $this->table.leave_type_id", 'left');
        $builder->where("$this->table.deleted", 0);

        $id = get_array_value($options, "id");
        if ($id) {
            $builder->where("$this->table.id", $id);
        }

        $status = get_array_value($options, "status");
        if ($status) {
            $builder->where("$this->table.status", $status);
        }

        $statuss = get_array_value($options, "statuss");
        if ($statuss) {
            $builder->whereIn("$this->table.status", ['pending', 'approve_by_manager']);
        }

        $start_date = get_array_value($options, "start_date");
        $end_date = get_array_value($options, "end_date");
        if ($start_date && $end_date) {
            $builder->where("$this->table.start_date BETWEEN '$start_date' AND '$end_date'");
        }

        $leave_type_id = get_array_value($options, "leave_type_id");
        if ($leave_type_id) {
            $builder->where("$this->table.leave_type_id", $leave_type_id);
        }

        $applicant_id = get_array_value($options, "applicant_id");
        if ($applicant_id) {
            $builder->where("$this->table.applicant_id", $applicant_id);
        }

        $login_user_id = get_array_value($options, "login_user_id");
        $line_manager = get_array_value($options, "line_manager");
        if ($line_manager) {
            $builder->where("$this->table.line_manager", $login_user_id);
        }

        $line_managers = get_array_value($options, "line_managers");
        if ($line_managers) {
            $builder->where("$this->table.line_manager", $line_managers);
        }

        $access_type = get_array_value($options, "access_type");
        if (!$id && $access_type !== "all") {
            $allowed_members = get_array_value($options, "allowed_members", []);
            $allowed_members[] = $login_user_id;
            $builder->whereIn("$this->table.applicant_id", $allowed_members);
        }

        return $builder->get()->getResult();
    }

    public function getSummary($options = [])
    {
        $builder = $this->db->table($this->table);
        $builder->select("SUM($this->table.total_hours) AS total_hours, SUM($this->table.total_days) AS total_days,
                        MAX($this->table.applicant_id) AS applicant_id, $this->table.created_at AS leave_created_at, $this->table.status,
                        CONCAT(users.first_name, ' ', users.last_name) AS applicant_name, users.image AS applicant_avatar,
                        leave_types.title AS leave_type_title, leave_types.color AS leave_type_color");
        $builder->join('users', "users.id = $this->table.applicant_id", 'left');
        $builder->join('leave_types', "leave_types.id = $this->table.leave_type_id", 'left');
        $builder->where("$this->table.deleted", 0);
        $builder->where("$this->table.status", 'approved');

        $start_date = get_array_value($options, "start_date");
        $end_date = get_array_value($options, "end_date");
        if ($start_date && $end_date) {
            $builder->where("$this->table.start_date BETWEEN '$start_date' AND '$end_date'");
        }

        $applicant_id = get_array_value($options, "applicant_id");
        if ($applicant_id) {
            $builder->where("$this->table.applicant_id", $applicant_id);
        }

        $leave_type_id = get_array_value($options, "leave_type_id");
        if ($leave_type_id) {
            $builder->where("$this->table.leave_type_id", $leave_type_id);
        }

        $access_type = get_array_value($options, "access_type");
        if ($access_type !== "all") {
            $allowed_members = get_array_value($options, "allowed_members", []);
            $allowed_members[] = $login_user_id;
            $builder->whereIn("$this->table.applicant_id", $allowed_members);
        }

        $builder->groupBy("$this->table.applicant_id, $this->table.leave_type_id");

        return $builder->get()->getResult();
    }
}
