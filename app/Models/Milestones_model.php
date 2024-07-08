<?php

namespace App\Models;

use CodeIgniter\Model;

class Milestones_model extends Model
{
    protected $table = 'milestones';

    public function __construct()
    {
        parent::__construct();
        $this->initActivityLog("milestone", "title", "project", "project_id");
    }

    public function schema(): array
    {
        return [
            'id' => [
                'label' => lang('id'),
                'type' => 'int',
            ],
            'title' => [
                'label' => lang('title'),
                'type' => 'text',
            ],
            'project_id' => [
                'label' => lang('project'),
                'type' => 'foreign_key',
                'linked_model' => Projects_model::class, // Assuming Projects_model is defined correctly
                'label_fields' => ['title'],
            ],
            'due_date' => [
                'label' => lang('due_date'),
                'type' => 'date',
            ],
            'deleted' => [
                'label' => lang('deleted'),
                'type' => 'int',
            ],
        ];
    }

    public function getDetails(array $options = [])
    {
        $builder = $this->db->table($this->table);
        $milestones_table = $builder->getName();
        $tasks_table = $this->db->table('tasks');

        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where("$milestones_table.id", $id);
        }

        $project_id = $options['project_id'] ?? null;
        if ($project_id) {
            $builder->where("$milestones_table.project_id", $project_id);
        }

        $builder->select("$milestones_table.*, 
            IFNULL(total_points_table.total_points, 0) AS total_points, 
            IFNULL(completed_points_table.completed_points, 0) AS completed_points");

        $totalPointsQuery = $this->db->table('tasks')
            ->select('milestone_id, SUM(points) AS total_points')
            ->where('deleted', 0)
            ->where('milestone_id !=', 0)
            ->groupBy('milestone_id');

        $completedPointsQuery = $this->db->table('tasks')
            ->select('milestone_id, SUM(points) AS completed_points')
            ->where('deleted', 0)
            ->where('milestone_id !=', 0)
            ->where('status_id', 3)
            ->groupBy('milestone_id');

        $builder->joinSub($totalPointsQuery, 'total_points_table', 'total_points_table.milestone_id = ' . $builder->escapeIdentifier("$milestones_table.id"), 'left');
        $builder->joinSub($completedPointsQuery, 'completed_points_table', 'completed_points_table.milestone_id = ' . $builder->escapeIdentifier("$milestones_table.id"), 'left');

        $builder->where("$milestones_table.deleted", 0);

        return $builder->get();
    }
}
