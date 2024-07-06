<?php
namespace App\Models;

use CodeIgniter\Model;

class ProjectCommentsModel extends Model
{
    protected $table = 'project_comments';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    public function __construct()
    {
        parent::__construct();
    }

    public function schema()
    {
        return [
            'id' => [
                'label' => lang('id'),
                'type' => 'int',
            ],
            'created_by' => [
                'label' => lang('created_by'),
                'type' => 'foreign_key',
                'linked_model' => UsersModel::class,
                'label_fields' => ['first_name', 'last_name'],
            ],
            'created_at' => [
                'label' => lang('created_date'),
                'type' => 'date_time',
            ],
            'description' => [
                'label' => lang('comment'),
                'type' => 'text',
            ],
            'project_id' => [
                'label' => lang('project'),
                'type' => 'foreign_key',
                'linked_model' => ProjectsModel::class,
                'label_fields' => ['title'],
            ],
            'task_id' => [
                'label' => lang('task'),
                'type' => 'foreign_key',
                'linked_model' => TasksModel::class,
                'label_fields' => ['id'],
            ],
            'file_id' => [
                'label' => lang('file'),
                'type' => 'foreign_key',
                'linked_model' => ProjectFilesModel::class,
                'label_fields' => ['id'],
            ],
            'customer_feedback_id' => [
                'label' => lang('feedback'),
                'type' => 'foreign_key',
                'linked_model' => CustomerFeedbackModel::class,
                'label_fields' => ['description'],
            ],
            'comment_id' => [
                'label' => lang('comment'),
                'type' => 'foreign_key',
                'linked_model' => self::class,
                'label_fields' => ['description'],
            ],
            'deleted' => [
                'label' => lang('deleted'),
                'type' => 'int',
            ],
        ];
    }

    public function getDetails($options = [])
    {
        $projectCommentsTable = $this->table;
        $usersTable = $this->db->table('users');

        $where = [];
        $id = $options['id'] ?? null;
        if ($id) {
            $where[] = "$projectCommentsTable.id = $id";
        }

        $project_id = $options['project_id'] ?? null;
        if ($project_id) {
            $where[] = "$projectCommentsTable.project_id = $project_id AND $projectCommentsTable.task_id = 0 AND $projectCommentsTable.file_id = 0 AND $projectCommentsTable.customer_feedback_id = 0";
        }

        $task_id = $options['task_id'] ?? null;
        if ($task_id) {
            $where[] = "$projectCommentsTable.task_id = $task_id";
        }

        $file_id = $options['file_id'] ?? null;
        if ($file_id) {
            $where[] = "$projectCommentsTable.file_id = $file_id";
        }

        $customer_feedback_id = $options['customer_feedback_id'] ?? null;
        if ($customer_feedback_id) {
            $where[] = "$projectCommentsTable.customer_feedback_id = $customer_feedback_id";
        }

        $sort = 'DESC';
        $comment_id = $options['comment_id'] ?? null;
        if ($comment_id) {
            $where[] = "$projectCommentsTable.comment_id = $comment_id";
            $sort = 'ASC';
        } else {
            $where[] = "$projectCommentsTable.comment_id = 0";
        }

        $this->select("$projectCommentsTable.*, $projectCommentsTable.id AS parent_comment_id, CONCAT($usersTable.first_name, ' ', $usersTable.last_name) AS created_by_user, $usersTable.image AS created_by_avatar, $usersTable.user_type,
            (SELECT COUNT(id) FROM $projectCommentsTable WHERE comment_id = parent_comment_id) AS total_replies");
        $this->join($usersTable->getName() . ' u', 'u.id = ' . $projectCommentsTable . '.created_by', 'left');
        $this->where("$projectCommentsTable.deleted", 0);

        if (!empty($where)) {
            $this->where(implode(' AND ', $where));
        }

        $this->orderBy("$projectCommentsTable.created_at", $sort);

        return $this->get()->getResult();
    }

    public function saveComment($data)
    {
        $comment_id = $data['comment_id'] ?? null;
        $file_id = $data['file_id'] ?? null;
        $task_id = $data['task_id'] ?? null;
        $customer_feedback_id = $data['customer_feedback_id'] ?? null;

        if (!empty($data['description'])) {
            $this->initActivityLog("project_comment", "description", "project", "project_id");
        }

        if ($comment_id) {
            $comment_info = $this->find($comment_id);
            $reply_type = "project_comment_reply";
            $data['project_id'] = $comment_info->project_id;
            $type = "";
            $type_id = "";
            if ($comment_info->task_id) {
                $data['task_id'] = $comment_info->task_id;
                $type = "task";
                $type_id = "task_id";
                $reply_type = "task_comment_reply";
            } else if ($comment_info->file_id) {
                $data['file_id'] = $comment_info->file_id;
                $type = "file";
                $type_id = "file_id";
                $reply_type = "file_comment_reply";
            } else if ($comment_info->customer_feedback_id) {
                $data['customer_feedback_id'] = $comment_info->customer_feedback_id;
                $type = "customer_feedback";
                $type_id = "customer_feedback_id";
                $reply_type = "customer_feedback_reply";
            }
            $this->initActivityLog($reply_type, "description", "project", "project_id", $type, $type_id);
        } else if ($file_id) {
            $file_info = $this->ProjectFilesModel->find($file_id);
            $data['project_id'] = $file_info->project_id;
            $this->initActivityLog("project_comment", "description", "project", "project_id", "file", "file_id");
        } else if ($task_id) {
            $task_info = $this->TasksModel->find($task_id);
            $data['project_id'] = $task_info->project_id;

            if (!empty($data['description'])) {
                $this->initActivityLog("task_comment", "description", "project", "project_id", "task", "task_id");
            }
        } else if ($customer_feedback_id) {
            $data['project_id'] = $customer_feedback_id;
            $this->initActivityLog("customer_feedback", "description", "project", "project_id", "customer_feedback", "customer_feedback_id");
        }

        return $this->save($data);
    }
}
