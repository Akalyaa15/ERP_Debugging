<?php
namespace App\Models;
use CodeIgniter\Model;
class AnnouncementsModel extends Model
{ protected $table = 'announcements';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['title', 'description', 'start_date', 'end_date', 'share_with', 'read_by', 'created_by', 'deleted'];
    public function __construct()
    {
        parent::__construct();
    }
    public function getUnreadAnnouncements($user_id, $user_type)
    {
        $now = date("Y-m-d");

        $where = "FIND_IN_SET('$user_id', read_by) = 0";
        
        if ($user_type === "staff" || $user_type === "resource") {
            $where .= " AND FIND_IN_SET('all_members', share_with)";
        } elseif ($this->login_user->partner_id) {
            $where .= " AND FIND_IN_SET('all_partners', share_with)";
        } elseif ($user_type === "client") {
            $where .= " AND FIND_IN_SET('all_clients', share_with)";
        } elseif ($user_type === "vendor") {
            $where .= " AND FIND_IN_SET('all_vendors', share_with)";
        }

        $query = $this->db->table($this->table)
                          ->where('deleted', 0)
                          ->where('start_date <=', $now)
                          ->where('end_date >=', $now)
                          ->where($where)
                          ->get();

        return $query->getResult();
    }

    public function getDetails($options = [])
    {
        $id = $options['id'] ?? null;
        $share_with = $options['share_with'] ?? null;

        $builder = $this->db->table($this->table)
                            ->select("$this->table.*, CONCAT(users.first_name, ' ', users.last_name) AS created_by_user, users.image AS created_by_avatar")
                            ->join('users', 'users.id = announcements.created_by')
                            ->where("$this->table.deleted", 0);

        if ($id) {
            $builder->where("$this->table.id", $id);
        }

        if ($share_with) {
            $builder->where("FIND_IN_SET('$share_with', $this->table.share_with)");
        }

        $query = $builder->get();

        return $query->getResult();
    }

    public function markAsRead($id, $user_id)
    {
        $query = $this->db->query("UPDATE $this->table SET read_by = CONCAT(read_by, ',$user_id') WHERE id = ? AND FIND_IN_SET(?, read_by) = 0", [$id, $user_id]);

        return $query;
    }
}