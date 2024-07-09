<?php

namespace App\Models;
use CodeIgniter\Model;
use App\Entities\UserEntity;

class UsersModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $returnType = UserEntity::class;

    protected $allowedFields = [
        'user_type',
        'client_id',
        'vendor_id',
        'email',
        'password',
        'status',
        'deleted',
        'disable_login',
        'is_admin',
    ];
    public function __construct()
    {
        parent::__construct();
    }
    public function authenticate($email, $password)
    {
        $user = $this->select('id, user_type, client_id, vendor_id')
                     ->where('email', $email)
                     ->where('password', md5($password))
                     ->where('status', 'active')
                     ->where('deleted', 0)
                     ->where('disable_login', 0)
                     ->first();

        if ($user) {
            // Check client or vendor login settings
            if (($user->user_type === "client" && get_setting("disable_client_login")) ||
                ($user->user_type === "vendor" && get_setting("disable_vendor_login"))) {
                return false;
            }

            // Additional checks for client or vendor
            $checkTable = ($user->user_type === "client") ? 'clients' : 'vendors';
            $checkField = ($user->user_type === "client") ? 'client_id' : 'vendor_id';

            $check = $this->db->table($this->db->dbprefix($checkTable))
                             ->select('id')
                             ->where('id', $user->$checkField)
                             ->where('deleted', 0)
                             ->get();

            if (!$check->getRow()) {
                return false;
            }

            // Set user session
            $session = session();
            $session->set('user_id', $user->id);
            return true;
        }

        return false;
    }
    public function login_user_id()
    {
        $session = session();
        return $session->get('user_id') ?? false;
    }

    public function sign_out()
    {
        $session = session();
        $session->remove('user_id');
        return redirect()->to('signin');
    }

    public function get_details($options = [])
    {
        $usersTable = $this->table;
        $teamMemberJobInfoTable = 'team_member_job_info';
        $rolesTable = 'roles';
    
        $builder = $this->db->table($usersTable)
                            ->select("$usersTable.*, $teamMemberJobInfoTable.date_of_hire, $teamMemberJobInfoTable.salary, $teamMemberJobInfoTable.salary_term")
                            ->join($teamMemberJobInfoTable, "$teamMemberJobInfoTable.user_id = $usersTable.id", 'left')
                            ->join($rolesTable, "$rolesTable.id = $usersTable.role_id AND $rolesTable.deleted = 0", 'left')
                            ->where("$usersTable.deleted", 0);
    
        $id = $options['id'] ?? null;
        $status = $options['status'] ?? null;
        $userType = $options['user_type'] ?? null;
        $clientId = $options['client_id'] ?? null;
        $vendorId = $options['vendor_id'] ?? null;
        $excludeUserId = $options['exclude_user_id'] ?? null;
        $companyId = $options['company_id'] ?? null;
    
        if ($id) {
            $builder->where("$usersTable.id", $id);
        }
    
        if ($status === "active") {
            $builder->where("$usersTable.status", 'active');
        } elseif ($status === "inactive") {
            $builder->where("$usersTable.status", 'inactive');
        }
    
        if ($userType) {
            $builder->where("$usersTable.user_type", $userType);
        }
    
        if ($clientId) {
            $builder->where("$usersTable.client_id", $clientId);
        }
    
        if ($vendorId) {
            $builder->where("$usersTable.vendor_id", $vendorId);
        }
    
        if ($excludeUserId) {
            $builder->where("$usersTable.id !=", $excludeUserId);
        }
    
        if ($companyId) {
            $builder->where("$usersTable.company_id", $companyId);
        }
    
        $customFieldType = ($userType === "client") ? "contacts" : "team_members";

        //prepare custom fild binding query
        $custom_fields = get_array_value($options, "custom_fields");
        $custom_field_query_info = $this->prepare_custom_field_query_string($custom_field_type, $custom_fields, $users_table);
        $select_custom_fieds = get_array_value($custom_field_query_info, "select_string");
        $join_custom_fieds = get_array_value($custom_field_query_info, "join_string");
       //prepare full query string
        $sql = "SELECT $users_table.*,
            $team_member_job_info_table.date_of_hire, $team_member_job_info_table.salary, $team_member_job_info_table.salary_term $select_custom_fieds,  $roles_table.title as role_title
        FROM $users_table
        LEFT JOIN $team_member_job_info_table ON $team_member_job_info_table.user_id=$users_table.id
        $join_custom_fieds  
        LEFT JOIN $roles_table ON $roles_table.id = $users_table.role_id AND $roles_table.deleted = 0  
        WHERE $users_table.deleted=0 $where
        ORDER BY $users_table.first_name";
        return $this->db->query($sql);
    }

    function is_email_exists($email, $id = 0) {
        $result = $this->get_all_where(array("email" => $email, "deleted" => 0));
        if ($result->num_rows() && $result->row()->id != $id) {
            return $result->row();
        } else {
            return false;
        }
    }

     // check personal email id
    function is_personal_email_exists($email, $id = 0) {
        $result = $this->get_all_where(array("personal_email" => $email, "deleted" => 0));
        if ($result->num_rows() && $result->row()->id != $id) {
            return $result->row();
        } else {
            return false;
        }
    }

    function get_job_info($user_id) {
        parent::use_table("team_member_job_info");
        return parent::get_one_where(array("user_id" => $user_id));
    }

    function save_job_info($data) {
        parent::use_table("team_member_job_info");

        //check if job info already exists
        $where = array("user_id" => get_array_value($data, "user_id"));
        $exists = parent::get_one_where($where);
        if ($exists->user_id) {
            //job info found. update the record
            return parent::update_where($data, $where);
        } else {
            //insert new one
            return parent::save($data);
        }
    }

    function get_team_members($member_ids = "") {
        $users_table = $this->db->dbprefix('users');
        $sql = "SELECT $users_table.*
        FROM $users_table
        WHERE $users_table.deleted=0 AND $users_table.user_type='staff' AND FIND_IN_SET($users_table.id, '$member_ids')
        ORDER BY $users_table.first_name";
        return $this->db->query($sql);
    }
function get_team_and_outsource_members($member_ids = "") {
        $users_table = $this->db->dbprefix('users');
        $sql = "SELECT $users_table.*
        FROM $users_table
        WHERE $users_table.deleted=0 AND ($users_table.user_type='staff' OR $users_table.user_type='resource') AND FIND_IN_SET($users_table.id, '$member_ids')
        ORDER BY $users_table.first_name";
        return $this->db->query($sql);
    }
    function get_access_info($user_id = 0) {
        $users_table = $this->db->dbprefix('users');
        $roles_table = $this->db->dbprefix('roles');
        $team_table = $this->db->dbprefix('team');

        $sql = "SELECT $users_table.id, $users_table.user_type, $users_table.is_admin, $users_table.role_id, $users_table.email,$users_table.password,$users_table.work_mode,$users_table.user_timezone,
            $users_table.first_name, $users_table.last_name, $users_table.image, $users_table.message_checked_at, $users_table.g_message_checked_at, $users_table.notification_checked_at, $users_table.client_id,$users_table.vendor_id,$users_table.partner_id, $users_table.line_manager,$users_table.department,$users_table.designation, $users_table.signature, 
            $users_table.is_primary_contact, $users_table.sticky_note, $users_table.theme_color,
            $roles_table.title as role_title, $roles_table.permissions,
            (SELECT GROUP_CONCAT(id) team_ids FROM $team_table WHERE FIND_IN_SET('$user_id', `members`)) as team_ids
        FROM $users_table
        LEFT JOIN $roles_table ON $roles_table.id = $users_table.role_id AND $roles_table.deleted = 0
        WHERE $users_table.deleted=0 AND $users_table.id=$user_id";
        return $this->db->query($sql)->row();
    }

    function get_team_members_and_clients($user_type = "", $user_ids = "", $exlclude_user = 0) {

        $users_table = $this->db->dbprefix('users');
        $clients_table = $this->db->dbprefix('clients');


        $where = "";
        if ($user_type) {
            $where .= " AND $users_table.user_type='$user_type'";
        }

        if ($user_ids) {
            $where .= "  AND FIND_IN_SET($users_table.id, '$user_ids')";
        }

        if ($exlclude_user) {
            $where .= " AND $users_table.id !=$exlclude_user";
        }

        $sql = "SELECT $users_table.id,$users_table.client_id, $users_table.user_type, $users_table.first_name, $users_table.last_name, $clients_table.company_name,
            $users_table.image,  $users_table.job_title, $users_table.last_online
        FROM $users_table
        LEFT JOIN $clients_table ON $clients_table.id = $users_table.client_id AND $clients_table.deleted=0
        WHERE $users_table.deleted=0 AND $users_table.status='active' $where
        ORDER BY $users_table.user_type, $users_table.first_name ASC";
        return $this->db->query($sql);
    }

    /* return comma separated list of user names */

    function user_group_names($user_ids = "") {
        $users_table = $this->db->dbprefix('users');

        $sql = "SELECT GROUP_CONCAT(' ', $users_table.first_name, ' ', $users_table.last_name) AS user_group_name
        FROM $users_table
        WHERE FIND_IN_SET($users_table.id, '$user_ids')";
        return $this->db->query($sql)->row();
    }

    /* return list of ids of the online users */

    function get_online_user_ids() {
        $users_table = $this->db->dbprefix('users');
        $now = get_current_utc_time();

        $sql = "SELECT $users_table.id 
        FROM $users_table
        WHERE TIMESTAMPDIFF(MINUTE, users.last_online, '$now')<=0";
        return $this->db->query($sql)->result();
    }

    function get_active_members_and_clients($options = array()) {
        $users_table = $this->db->dbprefix('users');
        $clients_table = $this->db->dbprefix('clients');

        $where = "";

        $user_type = get_array_value($options, "user_type");
        if ($user_type) {
            $where .= " AND $users_table.user_type='$user_type'";
        }

        $exclude_user_id = get_array_value($options, "exclude_user_id");
        if ($exclude_user_id) {
            $where .= " AND $users_table.id!=$exclude_user_id";
        }

        $sql = "SELECT CONCAT($users_table.first_name, ' ',$users_table.last_name) AS member_name, $users_table.last_online, $users_table.id, $users_table.image, $users_table.job_title, $users_table.user_type, $clients_table.company_name
        FROM $users_table
        LEFT JOIN $clients_table ON $clients_table.id = $users_table.client_id AND $clients_table.deleted=0
        WHERE $users_table.deleted=0 $where
        ORDER BY $users_table.last_online DESC";
        return $this->db->query($sql);
    }
function get_item_info_suggestion($branch = "",$designation = "",$country = "",$department = "",$company = "") {

        $items_table = $this->db->dbprefix('users');
        

        $sql = "SELECT $items_table.*
        FROM $items_table
        WHERE $items_table.deleted=0 
 AND $items_table.branch = '$branch' AND $items_table.designation = '$designation' AND $items_table.country ='$country' AND $items_table.department = '$department'  AND $items_table.company_id = '$company'
        ORDER BY id DESC 
        ";
        
        $result = $this->db->query($sql); 

        if ($result->num_rows()) {
            return $result->num_rows();
        }}
function get_item_info_suggestion_id($branch = "",$company = "") {

        $items_table = $this->db->dbprefix('users');
        $sql = "SELECT $items_table.*
        FROM $items_table
        WHERE $items_table.deleted=0 
 AND $items_table.branch = '$branch' AND $items_table.company_id = '$company'
        ORDER BY id DESC 
        ";
        
        $result = $this->db->query($sql); 

        if ($result->num_rows()) {
            return $result->num_rows();
        }

    }
    function get_client_contacts($item_name = "") {

        $items_table = $this->db->dbprefix('users');
        

        $sql = "SELECT $items_table.*
        FROM $items_table
        WHERE $items_table.deleted=0  AND $items_table.client_id = '$item_name'
        ORDER BY id
        ";
        
       return $this->db->query($sql)->result();


    }
    function get_vendor_contacts($item_name = "") {

        $items_table = $this->db->dbprefix('users');
        

        $sql = "SELECT $items_table.*
        FROM $items_table
        WHERE $items_table.deleted=0  AND $items_table.vendor_id = '$item_name'
        ORDER BY id
        ";
        
       return $this->db->query($sql)->result();


    }
 }
