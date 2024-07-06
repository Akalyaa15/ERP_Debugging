<?php
namespace App\Models;
use CodeIgniter\Model;
class GroupsModel extends Model {
    protected $table = 'groups';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['title', 'description', 'members', 'deleted'];
    protected $returnType = 'array';

    public function getDetails($options = []) {
        $builder = $this->builder($this->table);
        $builder->where('deleted', 0);

        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where('id', $id);
        }

        $userId = $options['user_id'] ?? null;
        if ($userId) {
            $builder->where("FIND_IN_SET('{$userId}', members)"); // Use query builder's escaping
        }
     return $builder->get()->getResultArray();
    }

    public function getMembers($teamIds = []) {
        $builder = $this->builder($this->table);
        $builder->select('members');
        $builder->whereIn('id', $teamIds);
        $builder->where('deleted', 0);

        $query = $builder->get();
        return $query->getResultArray();
    }

    public function isGroupTitleExists($title, $id = 0) {
        $builder = $this->builder($this->table);
        $builder->select('id');
        $builder->where('title', $title);
        $builder->where('deleted', 0);

        if ($id > 0) {
            $builder->where('id !=', $id);
        }

        $query = $builder->get();
        return $query->getRow();
    }
}
