<?php

namespace App\Models;

use CodeIgniter\Model;

class ChecklistItemsModel extends Model
{
    protected $table = 'checklist_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['id', 'task_id', 'title', 'description', 'sort', 'deleted'];

    public function __construct()
    {
        parent::__construct();
    }

    public function getDetails($options = [])
    {
        $builder = $this->db->table($this->table)
                            ->select("$this->table.*, IF($this->table.sort != 0, $this->table.sort, $this->table.id) AS new_sort")
                            ->where('deleted', 0);

        $task_id = get_array_value($options, "task_id");
        if ($task_id) {
            $builder->where('task_id', $task_id);
        }

        return $builder->orderBy('new_sort', 'ASC')
                       ->get()
                       ->getResultArray();
    }

    public function getAllChecklistOfProject($project_id)
    {
        $builder = $this->db->table($this->table)
                            ->select("$this->table.task_id, $this->table.title")
                            ->join('tasks', 'tasks.id = checklist_items.task_id')
                            ->where('checklist_items.deleted', 0)
                            ->where('tasks.project_id', $project_id);

        return $builder->get()->getResultArray();
    }
}
