<?php

namespace App\Models;

use CodeIgniter\Model;

class ProjectMembersModel extends Model
{
    protected $table = 'project_members';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    public function saveMember($data = [], $id = 0)
    {
        $userId = $data['user_id'] ?? null;
        $projectId = $data['project_id'] ?? null;
        if (!$userId || !$projectId) {
            return false;
        }

        $exists = $this->where('user_id', $userId)
                       ->where('project_id', $projectId)
                       ->first();

        if ($exists && $exists->id && $exists->deleted == 0) {
            return 'exists';
        } elseif ($exists && $exists->id && $exists->deleted == 1) {
            if ($this->delete($exists->id, true)) {
                return $exists->id;
            }
        } else {
            return $this->save($data, $id);
        }
    }

    public function getDetails($options = [])
    {
        $projectMembersTable = $this->db->table('project_members');
        $usersTable = $this->db->table('users');

        $where = [];
        $id = $options['id'] ?? null;
        if ($id) {
            $projectMembersTable->where('project_members.id', $id);
        }

        $projectId = $options['project_id'] ?? null;
        if ($projectId) {
            $projectMembersTable->where('project_members.project_id', $projectId);
        }

        $projectManager = $options['project_manager'] ?? null;
        if ($projectManager) {
            $projectMembersTable->where('project_members.is_project_manager', $projectManager);
        }

        $purchaseManager = $options['purchase_manager'] ?? null;
        if ($purchaseManager) {
            $projectMembersTable->where('project_members.is_purchase_manager', $purchaseManager);
        }

        $projectMembersTable->select('project_members.*, CONCAT(users.first_name, " ", users.last_name) AS member_name, users.image AS member_image, users.user_type AS member_user_type, users.job_title');
        $projectMembersTable->join('users', 'users.id = project_members.user_id', 'left');
        $projectMembersTable->where('project_members.deleted', 0);

        return $projectMembersTable->get()->getResult();
    }

    public function getProjectMembersDropdownList($projectId = 0, $userIds = [])
    {
        $projectMembersTable = $this->db->table('project_members');
        $usersTable = $this->db->table('users');

        $where = ['project_members.project_id' => $projectId];

        if (is_array($userIds) && count($userIds)) {
            $usersTable->whereIn('users.id', $userIds);
        }

        $projectMembersTable->select('project_members.user_id, CONCAT(users.first_name, " ", users.last_name) AS member_name');
        $projectMembersTable->join('users', 'users.id = project_members.user_id', 'left');
        $projectMembersTable->where('project_members.deleted', 0);
        $projectMembersTable->where('users.status', 'active');
        $projectMembersTable->groupBy('project_members.user_id');
        $projectMembersTable->orderBy('users.first_name', 'ASC');

        return $projectMembersTable->get()->getResult();
    }

    public function isUserAProjectMember($projectId = 0, $userId = 0)
    {
        $result = $this->where('project_id', $projectId)
                       ->where('user_id', $userId)
                       ->where('deleted', 0)
                       ->first();

        return $result ? true : false;
    }

    public function getRestTeamMembersForAProject($projectId = 0)
    {
        $usersTable = $this->db->table('users');
        $projectMembersTable = $this->db->table('project_members');

        $subQuery = $projectMembersTable->select('project_members.user_id')
                                       ->where('project_members.project_id', $projectId)
                                       ->where('project_members.deleted', 0)
                                       ->getCompiledSelect();

        $usersTable->select('users.id, CONCAT(users.first_name, " ", users.last_name) AS member_name');
        $usersTable->join('project_members', 'project_members.user_id = users.id', 'left');
        $usersTable->where('users.user_type', 'staff');
        $usersTable->where('users.deleted', 0);
        $usersTable->where('users.status', 'active');
        $usersTable->where('users.id NOT IN (' . $subQuery . ')', null, false);
        $usersTable->groupBy('users.id');
        $usersTable->orderBy('users.first_name', 'ASC');

        return $usersTable->get()->getResult();
    }

    public function getRestRmMembersForAProject($projectId = 0)
    {
        $usersTable = $this->db->table('users');
        $projectMembersTable = $this->db->table('project_members');

        $subQuery = $projectMembersTable->select('project_members.user_id')
                                       ->where('project_members.project_id', $projectId)
                                       ->where('project_members.deleted', 0)
                                       ->getCompiledSelect();

        $usersTable->select('users.id, CONCAT(users.first_name, " ", users.last_name) AS member_name');
        $usersTable->join('project_members', 'project_members.user_id = users.id', 'left');
        $usersTable->where('users.user_type', 'resource');
        $usersTable->where('users.deleted', 0);
        $usersTable->where('users.status', 'active');
        $usersTable->where('users.id NOT IN (' . $subQuery . ')', null, false);
        $usersTable->groupBy('users.id');
        $usersTable->orderBy('users.first_name', 'ASC');

        return $usersTable->get()->getResult();
    }
}
