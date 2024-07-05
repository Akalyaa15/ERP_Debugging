<?php

namespace App\Models;

use CodeIgniter\Model;

class AttendanceTodoModel extends Model
{
    protected $table = 'attendance_to_do';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'todo_id', 'start_date', 'user_id', 'status', 'task_id', 'deleted'
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function getDetails($options = [])
    {
        $builder = $this->db->table($this->table)
                            ->where('deleted', 0);

        $id = get_array_value($options, "id");
        if ($id) {
            $builder->where('id', $id);
        }

        $todoId = get_array_value($options, "todo_id");
        if ($todoId) {
            $builder->where('todo_id', $todoId);
        }

        $startDate = get_array_value($options, "start_date");
        if ($startDate) {
            $builder->where('start_date', $startDate);
        }

        $userId = get_array_value($options, "user_id");
        if ($userId) {
            $builder->where('user_id', $userId);
        }

        $status = get_array_value($options, "status");
        if ($status) {
            $statusArray = explode(",", $status);
            $builder->whereIn('status', $statusArray);
        }

        $taskId = get_array_value($options, "task_id");
        if ($taskId) {
            $builder->where('task_id', $taskId);
        }

        return $builder->get()->getResultArray();
    }

    public function getStatusInfoSuggestion($itemName)
    {
        $builder = $this->db->table('attendance_to_do')
                            ->where('deleted', 0)
                            ->where('todo_id', $itemName)
                            ->where('status', 'to_do')
                            ->orderBy('id');

        return $builder->get()->getResultArray();
    }
}
