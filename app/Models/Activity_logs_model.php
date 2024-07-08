<?php
namespace App\Models;
use CodeIgniter\Model;
use stdClass;
class ActivityLogsModel extends Model
{
    protected $table = 'activity_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'log_for', 'log_for_id', 'log_type', 'log_type_id', 'created_by', 'created_at'
    ];
    protected $useTimestamps = false;
    public function saveLog($data)
    {   $data["created_at"] = $this->getCurrentUTCTime();
        $data["created_by"] = session()->get('login_user')->id;
        $this->insert($data);
        return $this->insertID();
    }
   public function deleteWhere($where = [])
    {
        if (count($where)) {
            return $this->where($where)->delete();
        }
    }
   public function getDetails($options = [])
    {
        $activity_logs_table = $this->db->prefixTable('activity_logs');
        $project_members_table = $this->db->prefixTable('project_members');
        $users_table = $this->db->prefixTable('users');
        
        $where = [];
        $limit = $optionsactivity_logs['limit'] ?? 20;
        $offset = $options['offset'] ?? 0;

        $extraJoinInfo = '';
        $extraSelect = '';

        if (!empty($options['log_for'])) {
            $where["$activity_logs_table.log_for"] = $options['log_for'];
            if (!empty($options['log_for_id'])) {
                $where["$activity_logs_table.log_for_id"] = $options['log_for_id'];
            } elseif ($options['log_for'] === 'project') {
                $link_with_table = $this->db->prefixTable('projects');
                $extraJoinInfo = " LEFT JOIN $link_with_table ON $activity_logs_table.log_for_id=$link_with_table.id ";
                $extraSelect = " , $link_with_table.title as log_for_title";
            }
        }
        if (!empty($options['log_type']) && !empty($options['log_type_id'])) {
            $where["$activity_logs_table.log_type"] = $options['log_type'];
            $where["$activity_logs_table.log_type_id"] = $options['log_type_id'];
        }

        $projectJoin = '';
        $projectWhere = '';
        if (empty($options['is_admin']) && !empty($options['user_id'])) {
            $projectJoin = " LEFT JOIN (SELECT $project_members_table.user_id, $project_members_table.project_id FROM $project_members_table WHERE $project_members_table.user_id={$options['user_id']} AND $project_members_table.deleted=0 GROUP BY $project_members_table.project_id) AS project_members_table ON project_members_table.project_id= $activity_logs_table.log_for_id AND log_for='project' ";
            $projectWhere = " AND project_members_table.user_id={$options['user_id']}";
        }

        $sql = "SELECT SQL_CALC_FOUND_ROWS $activity_logs_table.*,  CONCAT($users_table.first_name, ' ', $users_table.last_name) AS created_by_user, $users_table.image as created_by_avatar, $users_table.user_type $extraSelect
            FROM $activity_logs_table
            LEFT JOIN $users_table ON $users_table.id= $activity_logs_table.created_by
            $extraJoinInfo
            $projectJoin
            WHERE $activity_logs_table.deleted=0 " . implode(" AND ", $where) . $projectWhere . "
            ORDER BY $activity_logs_table.created_at DESC
            LIMIT $offset, $limit";

        $query = $this->db->query($sql);
        $data = new stdClass();
        $data->result = $query->getResult();
        $data->found_rows = $this->db->query("SELECT FOUND_ROWS() as found_rows")->getRow()->found_rows;
        return $data;
    }
    public function getOne($id = 0)
    {
        return $this->getOneWhere(['id' => $id]);
    }

    public function getOneWhere($where = [])
    {
        $result = $this->where($where)->first();
        if ($result) {
            return $result;
        } else {
            $fields = $this->db->getFieldData($this->table);
            $emptyRow = new stdClass();
            foreach ($fields as $field) {
                $emptyRow->{$field->name} = '';
            }
            return $emptyRow;
        }
    }

    public function updateWhere($data = [], $where = [])
    {
        if (count($where)) {
            return $this->where($where)->set($data)->update();
        }
    }

    private function getCurrentUTCTime()
    {
        return gmdate('Y-m-d H:i:s');
    }
}