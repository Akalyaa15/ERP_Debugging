<?php

namespace App\Models;

use CodeIgniter\Model;

class ProjectFilesModel extends Model
{
    protected $table = 'project_files';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    public function __construct()
    {
        parent::__construct();
        $this->initActivityLog("project_file", "file_name", "project", "project_id");
    }

    public function schema()
    {
        return [
            'id' => [
                'label' => lang('id'),
                'type' => 'int',
            ],
            'file_name' => [
                'label' => lang('file_name'),
                'type' => 'text',
            ],
            'project_id' => [
                'label' => lang('project'),
                'type' => 'foreign_key',
                'linked_model' => ProjectsModel::class,
                'label_fields' => ['title'],
            ],
            'start_date' => [
                'label' => lang('start_date'),
                'type' => 'date',
            ],
            'end_date' => [
                'label' => lang('end_date'),
                'type' => 'date',
            ],
            'deleted' => [
                'label' => lang('deleted'),
                'type' => 'int',
            ],
        ];
    }

    public function getDetails($options = [])
    {
        $projectFilesTable = $this->table;
        $usersTable = $this->db->table('users');

        $where = [];
        $id = $options['id'] ?? null;
        if ($id) {
            $where[] = "$projectFilesTable.id = $id";
        }

        $projectId = $options['project_id'] ?? null;
        if ($projectId) {
            $where[] = "$projectFilesTable.project_id = $projectId";
        }

        $this->select("$projectFilesTable.*, CONCAT($usersTable.first_name, ' ', $usersTable.last_name) AS uploaded_by_user_name, $usersTable.image AS uploaded_by_user_image, $usersTable.user_type AS uploaded_by_user_type");
        $this->join($usersTable->getName() . ' u', 'u.id = ' . $projectFilesTable . '.uploaded_by', 'left');
        $this->where("$projectFilesTable.deleted", 0);

        if (!empty($where)) {
            $this->where(implode(' AND ', $where));
        }

        return $this->get()->getResult();
    }

    public function getFiles($ids = [])
    {
        $projectFilesTable = $this->table;
        $idsString = implode(',', $ids);

        $sql = "SELECT * FROM $projectFilesTable WHERE deleted = 0 AND FIND_IN_SET($projectFilesTable.id, '$idsString')";
        $query = $this->query($sql);

        if ($query->getResult()) {
            return $query;
        }
    }
}