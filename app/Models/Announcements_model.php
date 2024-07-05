<?php
namespace App\Models;
use CodeIgniter\Model;
class AnnouncementsModel extends Model
{
    protected $table = 'announcements';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = false;
    protected $allowedFields = ['share_with', 'start_date', 'end_date', 'created_by', 'read_by', 'deleted'];

    public function getUnreadAnnouncements($user_id, $team_id, $user_type)
    {
        $now = gmdate("Y-m-d");
        $where = "FIND_IN_SET($user_id, read_by) = 0";

        if ($user_type === "staff") {
            $where .= " AND (FIND_IN_SET('all_members', share_with) OR FIND_IN_SET('member:$user_id', share_with) OR FIND_IN_SET('team:$team_id', share_with))";
        } elseif ($user_type === "client") {
            $where .= " AND (FIND_IN_SET('all_clients', share_with) OR FIND_IN_SET('contact:$user_id', share_with))";
        } elseif ($user_type === "vendor") {
            $where .= " AND (FIND_IN_SET('all_vendors', share_with) OR FIND_IN_SET('vendor_contact:$user_id', share_with))";
        } elseif ($user_type === "resource") {
            $where .= " AND (FIND_IN_SET('all_resource', share_with) OR FIND_IN_SET('outsource_member:$user_id', share_with))";
        }

        return $this->where("deleted", 0)
                    ->where("start_date <=", $now)
                    ->where("end_date >=", $now)
                    ->where($where)
                    ->findAll();
    }

    public function getMarqueeAnnouncements($user_id, $team_id, $user_type)
    {
        $now = gmdate("Y-m-d");
        $where = "";

        if ($user_type === "staff") {
            $where .= " AND (FIND_IN_SET('all_members', share_with) OR FIND_IN_SET('member:$user_id', share_with) OR FIND_IN_SET('team:$team_id', share_with))";
        } elseif ($user_type === "client") {
            $where .= " AND (FIND_IN_SET('all_clients', share_with) OR FIND_IN_SET('contact:$user_id', share_with))";
        } elseif ($user_type === "vendor") {
            $where .= " AND (FIND_IN_SET('all_vendors', share_with) OR FIND_IN_SET('vendor_contact:$user_id', share_with))";
        } elseif ($user_type === "resource") {
            $where .= " AND (FIND_IN_SET('all_resource', share_with) OR FIND_IN_SET('outsource_member:$user_id', share_with))";
        }

        return $this->where("deleted", 0)
                    ->where("end_date >=", $now)
                    ->where($where)
                    ->orderBy("end_date", "ASC")
                    ->findAll();
    }

    public function getDetails($options = [])
    {
        $builder = $this->db->table($this->table);
        $builder->select("announcements.*, CONCAT(users.first_name, ' ', users.last_name) AS created_by_user, users.image AS created_by_avatar, partners.*, clients.*, vendors.*");
        $builder->join('users', 'users.id = announcements.created_by', 'left');
        $builder->join('partners', 'partners.id = announcements.partner_id', 'left');
        $builder->join('clients', 'clients.id = announcements.client_id', 'left');
        $builder->join('vendors', 'vendors.id = announcements.vendor_id', 'left');
        $builder->where('announcements.deleted', 0);

        if (!empty($options['id'])) {
            $builder->where('announcements.id', $options['id']);
        }

        if (!empty($options['user_id'])) {
            $user_id = $options['user_id'];
            $team_ids = !empty($options['team_ids']) ? explode(",", $options['team_ids']) : [];
            $team_search_sql = "";

            foreach ($team_ids as $team_id) {
                $team_search_sql .= " OR (FIND_IN_SET('team:$team_id', announcements.share_with))";
            }

            if (!empty($options['is_partner'])) {
                $builder->where("(announcements.created_by = $user_id 
                OR announcements.share_with = 'all_partners' 
                OR FIND_IN_SET('partner_contact:$user_id', announcements.share_with))");
            } elseif (!empty($options['is_client'])) {
                $builder->where("(announcements.created_by = $user_id 
                OR announcements.share_with = 'all_clients' 
                OR FIND_IN_SET('contact:$user_id', announcements.share_with))");
            } elseif (!empty($options['is_vendor'])) {
                $builder->where("(announcements.created_by = $user_id 
                OR announcements.share_with = 'all_vendors' 
                OR FIND_IN_SET('vendor_contact:$user_id', announcements.share_with))");
            } elseif (!empty($options['is_resource'])) {
                $builder->where("(announcements.created_by = $user_id 
                OR announcements.share_with = 'all_resource' 
                OR FIND_IN_SET('outsource_member:$user_id', announcements.share_with) $team_search_sql)");
            } else {
                $builder->where("(announcements.created_by = $user_id 
                OR announcements.share_with = 'all_members' 
                OR FIND_IN_SET('member:$user_id', announcements.share_with) $team_search_sql)");
            }
        }

        if (!empty($options['partner_id'])) {
            $builder->where('announcements.partner_id', $options['partner_id']);
        }

        if (!empty($options['client_id'])) {
            $builder->where('announcements.client_id', $options['client_id']);
        }

        if (!empty($options['vendor_id'])) {
            $builder->where('announcements.vendor_id', $options['vendor_id']);
        }

        return $builder->get()->getResult();
    }

    public function markAsRead($id, $user_id)
    {
        $builder = $this->db->table($this->table);
        $builder->set('read_by', "CONCAT(read_by, ',', '$user_id')", false);
        $builder->where('id', $id);
        $builder->where("FIND_IN_SET($user_id, read_by) = 0");
        return $builder->update();
    }

    public function getResponseByUsers($user_ids_array)
    {
        $user_ids = implode(",", $user_ids_array);
        if ($user_ids) {
            $builder = $this->db->table('users');
            $builder->select("id, user_type, image, CONCAT(first_name, ' ', last_name) AS member_name");
            $builder->where("FIND_IN_SET(id, '$user_ids')");
            $builder->where('deleted', 0);
            return $builder->get()->getResult();
        }
        return false;
    }
}
