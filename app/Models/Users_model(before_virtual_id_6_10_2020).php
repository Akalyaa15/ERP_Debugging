<?php

namespace App\Models;

use CodeIgniter\Model;
class Users_model extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'first_name', 'last_name', 'email', 'password', 'status', 'deleted', 'disable_login',
        'user_type', 'client_id', 'vendor_id', 'partner_id', 'line_manager', 'department', 'designation',
        'signature', 'is_primary_contact', 'sticky_note', 'theme_color', 'work_mode', 'user_timezone',
        'image', 'message_checked_at', 'g_message_checked_at', 'notification_checked_at', 'created_at', 'updated_at'
    ];

    public function authenticate($email, $password)
    {
        $builder = $this->db->table($this->table);
        $builder->select('id, user_type, client_id, vendor_id');
        $builder->where('email', $email);
        $builder->where('password', md5($password));
        $builder->where('status', 'active');
        $builder->where('deleted', 0);
        $builder->where('disable_login', 0);

        $query = $builder->get();

        if ($query->getNumRows() == 1) {
            $user_info = $query->getRow();

            if ($user_info->user_type === 'client' && get_setting('disable_client_login')) {
                return false;
            } elseif ($user_info->user_type === 'vendor' && get_setting('disable_vendor_login')) {
                return false;
            } elseif ($user_info->user_type === 'client') {
                $clients_table = $this->db->table('clients');
                $client_query = $clients_table->getWhere(['id' => $user_info->client_id, 'deleted' => 0]);

                if ($client_query->getNumRows() == 0) {
                    return false;
                }
            } elseif ($user_info->user_type === 'vendor') {
                $vendors_table = $this->db->table('vendors');
                $vendor_query = $vendors_table->getWhere(['id' => $user_info->vendor_id, 'deleted' => 0]);

                if ($vendor_query->getNumRows() == 0) {
                    return false;
                }
            }

            session()->set('user_id', $user_info->id);
            return true;
        }
    return false;
    }

    public function login_user_id()
    {
        return session()->get('user_id') ?: false;
    }

    public function sign_out()
    {
        session()->remove('user_id');
        return redirect()->to('signin');
    }


    public function get_details($options = [])
    {
        $users_table = $this->table;
        $team_member_job_info_table = 'team_member_job_info'; // Adjust table name if needed
        $roles_table = 'roles'; // Adjust table name if needed
    
        $builder = $this->db->table($users_table);
        $builder->select("$users_table.*, 
            $team_member_job_info_table.date_of_hire, $team_member_job_info_table.salary, $team_member_job_info_table.salary_term");
    
        // Handle options
        $id = $options['id'] ?? null;
        $status = $options['status'] ?? null;
        $user_type = $options['user_type'] ?? null;
        $client_id = $options['client_id'] ?? null;
        $vendor_id = $options['vendor_id'] ?? null;
        $exclude_user_id = $options['exclude_user_id'] ?? null;
        $company_id = $options['company_id'] ?? null;
    
        if ($id) {
            $builder->where("$users_table.id", $id);
        }
        if ($status === 'active') {
            $builder->where("$users_table.status", 'active');
        } elseif ($status === 'inactive') {
            $builder->where("$users_table.status", 'inactive');
        }
        if ($user_type) {
            $builder->where("$users_table.user_type", $user_type);
        }
        if ($client_id) {
            $builder->where("$users_table.client_id", $client_id);
        }
        if ($vendor_id) {
            $builder->where("$users_table.vendor_id", $vendor_id);
        }
        if ($exclude_user_id) {
            $builder->where("$users_table.id !=", $exclude_user_id);
        }
        if ($company_id) {
            $builder->where("$users_table.company_id", $company_id);
        }
    
        $custom_field_type = ($user_type === 'client') ? 'contacts' : 'team_members';
        $custom_fields = $options['custom_fields'] ?? [];
        $custom_field_query_info = $this->prepare_custom_field_query_string($custom_field_type, $custom_fields, $users_table);
        $select_custom_fields = $custom_field_query_info['select_string'];
        $join_custom_fields = $custom_field_query_info['join_string'];
    
        // Add custom fields to the query
        $builder->select($select_custom_fields);
        $builder->join($join_custom_fields);
    
        $builder->join($team_member_job_info_table, "$team_member_job_info_table.user_id = $users_table.id", 'left');
        $builder->join($roles_table, "$roles_table.id = $users_table.role_id AND $roles_table.deleted = 0", 'left');
    
        $builder->where("$users_table.deleted", 0);
        $builder->orderBy("$users_table.first_name");
    
        return $builder->get();
    }
      
    public function isEmailExists($email, $id = 0)
    {
        $builder = $this->db->table($this->table);
        $result = $builder->getWhere(['email' => $email, 'deleted' => 0]);
        if ($result->getNumRows() && $result->getRow()->id != $id) {
            return $result->getRow();
        } else {
            return false;
        }
    }

    public function getJobInfo($user_id)
    {
        $this->useTable("team_member_job_info");
        return $this->getWhere(['user_id' => $user_id])->getRow();
    }

    public function saveJobInfo($data)
    {
        $this->useTable("team_member_job_info");
        $where = ['user_id' => $data['user_id']];
        $exists = $this->getWhere($where)->getRow();
        if ($exists) {
            return $this->update($where, $data);
        } else {
            return $this->insert($data);
        }
    }
    public function getTeamMembers($member_ids = "")
    {
        $builder = $this->db->table($this->table);
        $builder->select('*');
        $builder->where('deleted', 0);
        $builder->where('user_type', 'staff');
        $builder->whereIn('id', explode(",", $member_ids));
        $builder->orderBy('first_name');
        return $builder->get();
    }
    public function getTeamAndOutsourceMembers($member_ids = "")
    {
        $builder = $this->db->table($this->table);
        $builder->select('*');
        $builder->where('deleted', 0);
        $builder->whereIn('user_type', ['staff', 'resource']);
        $builder->whereIn('id', explode(",", $member_ids));
        $builder->orderBy('first_name');
        return $builder->get();
    }

    public function getAccessInfo($user_id = 0)
    {
        $builder = $this->db->table($this->table);
        $builder->select('users.id, users.user_type, users.is_admin, users.role_id, users.email, users.password, users.work_mode, users.user_timezone,
            users.first_name, users.last_name, users.image, users.message_checked_at, users.g_message_checked_at, users.notification_checked_at, users.client_id, users.vendor_id, users.partner_id, users.line_manager, users.department, users.designation, users.signature,
            users.is_primary_contact, users.sticky_note, users.theme_color,
            roles.title as role_title, roles.permissions,
            (SELECT GROUP_CONCAT(id) FROM team WHERE FIND_IN_SET(?, members)) as team_ids');
        $builder->join('roles', 'roles.id = users.role_id AND roles.deleted = 0', 'left');
        $builder->where('users.deleted', 0);
        $builder->where('users.id', $user_id);
        return $builder->get()->getRow();
    }

    public function getTeamMembersAndClients($user_type = "", $user_ids = "", $exclude_user = 0)
    {
        $builder = $this->db->table($this->table);
        $clientsTable = $this->db->table('clients');

        $builder->select('users.id, users.client_id, users.user_type, users.first_name, users.last_name, clients.company_name,
            users.image, users.job_title, users.last_online');
        $builder->join('clients', 'clients.id = users.client_id AND clients.deleted = 0', 'left');
        $builder->where('users.deleted', 0);
        $builder->where('users.status', 'active');
        if ($user_type) {
            $builder->where('users.user_type', $user_type);
        }
        if ($user_ids) {
            $builder->whereIn('users.id', explode(",", $user_ids));
        }
        if ($exclude_user) {
            $builder->where('users.id !=', $exclude_user);
        }
        $builder->orderBy('users.user_type, users.first_name', 'ASC');
        return $builder->get();
    }

    /* return comma separated list of user names */

    public function userGroupNames($user_ids = "")
    {
        $builder = $this->db->table($this->table);
        $builder->select('GROUP_CONCAT(users.first_name, " ", users.last_name) AS user_group_name');
        $builder->whereIn('users.id', explode(",", $user_ids));
        return $builder->get()->getRow();
    }


    /* return list of ids of the online users */

    public function getOnlineUserIds()
    {
        $builder = $this->db->table($this->table);
        $now = date('Y-m-d H:i:s');

        $builder->select('id');
        $builder->where('TIMESTAMPDIFF(MINUTE, users.last_online, ?) <= 0', [$now]);
        return $builder->get()->getResult();
    }

    public function getActiveMembersAndClients($options = [])
    {
        $builder = $this->db->table($this->table);
        $clientsTable = $this->db->table('clients');

        $builder->select('CONCAT(users.first_name, " ", users.last_name) AS member_name, users.last_online, users.id, users.image, users.job_title, users.user_type, clients.company_name');
        $builder->join('clients', 'clients.id = users.client_id AND clients.deleted = 0', 'left');
        $builder->where('users.deleted', 0);
        if (isset($options['user_type'])) {
            $builder->where('users.user_type', $options['user_type']);
        }
        if (isset($options['exclude_user_id'])) {
            $builder->where('users.id !=', $options['exclude_user_id']);
        }
        $builder->orderBy('users.last_online', 'DESC');
        return $builder->get();
    }
    public function getItemInfoSuggestion($branch = "", $designation = "", $country = "", $department = "")
    {
        $builder = $this->db->table($this->table);
        $builder->select('*');
        $builder->where('deleted', 0);
        $builder->where('branch', $branch);
        $builder->where('designation', $designation);
        $builder->where('country', $country);
        $builder->where('department', $department);
        $builder->orderBy('id', 'DESC');
        $result = $builder->get();
        if ($result->getNumRows()) {
            return $result->getNumRows();
        }
    }
    public function getClientContacts($item_name = "")
    {
        $builder = $this->db->table($this->table);
        $builder->select('*');
        $builder->where('deleted', 0);
        $builder->where('client_id', $item_name);
        $builder->orderBy('id');
        return $builder->get()->getResult();
    }
    public function getVendorContacts($item_name = "")
    {
        $builder = $this->db->table($this->table);
        $builder->select('*');
        $builder->where('deleted', 0);
        $builder->where('vendor_id', $item_name);
        $builder->orderBy('id');
        return $builder->get()->getResult();
    }
}
