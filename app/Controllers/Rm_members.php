<?php

namespace App\Controllers;

use App\Models\CustomFieldsModel;
use App\Models\UsersModel;
use App\Models\RolesModel;
use CodeIgniter\API\ResponseTrait;

class Rm_members extends BaseController
{
    use ResponseTrait;

    protected $Custom_fields_model;
    protected $Users_model;
    protected $Roles_model;

    public function __construct()
    {
        $this->Custom_fields_model = new CustomFieldsModel();
        $this->Users_model = new UsersModel();
        $this->Roles_model = new RolesModel();
        $this->init_permission_checker("outsource_members");
    }

    private function can_view_team_members_contact_info()
    {
        $user = $this->authenticateUser();
        if ($user && ($user->user_type == "staff" || $user->user_type == "resource")) {
            if ($user->is_admin || get_array_value($user->permissions, "can_view_team_members_contact_info") == "1") {
                return true;
            }
        }
        return false;
    }

    private function can_view_team_members_social_links()
    {
        $user = $this->authenticateUser();
        if ($user && $user->user_type == "resource") {
            if ($user->is_admin || get_array_value($user->permissions, "can_view_team_members_social_links") == "1") {
                return true;
            }
        }
        return false;
    }

    private function can_update_team_members_info($user_id)
    {
        $user = $this->authenticateUser();
        if (!$user) {
            return false;
        }

        $access_info = $this->get_access_info("team_member_update_permission");

        if ($user->id === $user_id) {
            return true; //own profile
        } elseif ($access_info->access_type == "all") {
            return true; //has access to change all user's profile
        } elseif ($user_id && in_array($user_id, $access_info->allowed_members)) {
            return true; //has permission to update this user's profile
        }

        return false;
    }

    private function authenticateUser()
    {
        // Implement your user authentication logic here.
        // Example: Fetch user details from session or database
        return session()->get('logged_in_user'); // Example implementation using session
    }

    public function index()
    {
        $this->check_module_availability("module_outsource_members");

        $view_data = [
            "show_contact_info" => $this->can_view_team_members_contact_info(),
            "custom_field_headers" => $this->Custom_fields_model->get_custom_field_headers_for_table("team_members", $this->authenticateUser()->is_admin, $this->authenticateUser()->user_type)
        ];

        if ($this->authenticateUser()->is_admin) {
            return view('rm_members/index', $view_data);
        } elseif ($this->authenticateUser()->user_type == "staff") {
            if ($this->access_type != "all" && !in_array($this->authenticateUser()->id, $this->allowed_members)) {
                return redirect()->to('forbidden');
            }
        }

        return view('rm_members/index', $view_data);
    }


    /* open new member modal */

    public function modal_form()
    {
        $this->access_only_admin();

        // Validate input data
        $id = $this->request->getPost('id');
        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'numeric'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            // Handle validation errors if needed
            return;
        }

        $view_data['role_dropdown'] = $this->_get_roles_dropdown();

        $options = [
            "id" => $id,
        ];

        $view_data['model_info'] = $this->Users_model->get_details($options)->getRow();

        $view_data["custom_fields"] = $this->Custom_fields_model->get_combined_details("team_members", 0, $this->authenticateUser()->is_admin, $this->authenticateUser()->user_type)->getResult();

        return view('rm_members/modal_form', $view_data);
    }

    public function add_rm_member()
    {
        $this->access_only_admin();

        // Validate input data
        $validation = \Config\Services::validation();
        $validation->setRules([
            'email' => 'required|valid_email',
            'first_name' => 'required',
            'last_name' => 'required',
            'job_title' => 'required',
            'role' => 'required'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            // Handle validation errors if needed
            return;
        }

        // Check for duplicate email
        if ($this->Users_model->is_email_exists($this->request->getPost('email'))) {
            return $this->response->setJSON([
                'success' => false,
                'message' => lang('duplicate_email')
            ]);
        }

        // Prepare user data
        $user_data = [
            "email" => $this->request->getPost('email'),
            "password" => md5($this->request->getPost('password')),
            "first_name" => $this->request->getPost('first_name'),
            "last_name" => $this->request->getPost('last_name'),
            "is_admin" => $this->request->getPost('is_admin') ? 1 : 0,
            "address" => $this->request->getPost('address'),
            "phone" => $this->request->getPost('phone'),
            "gender" => $this->request->getPost('gender'),
            "job_title" => $this->request->getPost('job_title'),
            "user_type" => "resource",
            "created_at" => date('Y-m-d H:i:s')
        ];

        // Determine role id
        $role = $this->request->getPost('role');
        $role_id = $role === "admin" ? 0 : $role;

        $user_data["role_id"] = $role_id;

        // Save user data
        $user_id = $this->Users_model->save($user_data);

        if ($user_id) {
            // Save job info
            $job_data = [
                "user_id" => $user_id,
                "salary" => $this->request->getPost('salary') ?: 0,
                "salary_term" => $this->request->getPost('salary_term'),
                "date_of_hire" => $this->request->getPost('date_of_hire')
            ];
            $this->Users_model->save_job_info($job_data);

            // Save custom fields
            save_custom_fields("team_members", $user_id, $this->authenticateUser()->is_admin, $this->authenticateUser()->user_type);

            // Send login details if requested
            if ($this->request->getPost('email_login_details')) {
                $email_template = $this->Email_templates_model->get_final_template("login_info");

                $parser_data = [
                    "SIGNATURE" => $email_template->signature,
                    "USER_FIRST_NAME" => $user_data["first_name"],
                    "USER_LAST_NAME" => $user_data["last_name"],
                    "USER_LOGIN_EMAIL" => $user_data["email"],
                    "USER_LOGIN_PASSWORD" => $this->request->getPost('password'),
                    "DASHBOARD_URL" => base_url(),
                    "LOGO_URL" => get_logo_url()
                ];

                $message = $this->parser->setData($parser_data)->renderString($email_template->message);
                send_app_mail($user_data["email"], $email_template->subject, $message);
            }

            return $this->response->setJSON([
                'success' => true,
                'data' => $this->_row_data($user_id),
                'id' => $user_id,
                'message' => lang('record_saved')
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => lang('error_occurred')
        ]);
    }

    public function invitation_modal()
    {
        $this->access_only_admin();
        return view('rm_members/invitation_modal');
    }

    public function send_invitation()
    {
        $this->access_only_admin();

        // Validate input data
        $validation = \Config\Services::validation();
        $validation->setRules([
            'email' => 'required|valid_emails'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            // Handle validation errors if needed
            return;
        }

        $email_array = array_unique($this->request->getPost('email'));

        $email_template = $this->Email_templates_model->get_final_template("team_member_invitation");

        $parser_data = [
            "INVITATION_SENT_BY" => $this->authenticateUser()->first_name . " " . $this->authenticateUser()->last_name,
            "SIGNATURE" => $email_template->signature,
            "SITE_URL" => site_url(),
            "LOGO_URL" => get_logo_url()
        ];

        $send_email = [];

        foreach ($email_array as $email) {
            $key = encode_id($this->encryption->encrypt('resource|' . $email . '|' . (time() + (24 * 60 * 60))), "signup");
            $parser_data['INVITATION_URL'] = site_url("signup/accept_invitation/" . $key);

            $message = $this->parser->setData($parser_data)->renderString($email_template->message);

            $send_email[] = send_app_mail($email, $email_template->subject, $message);
        }

        if (!in_array(false, $send_email)) {
            $message = count($send_email) != 0 && count($send_email) == 1 ? lang("invitation_sent") : lang("invitations_sent");
            return $this->response->setJSON(['success' => true, 'message' => $message]);
        }

        return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
    }


    //prepere the data for members list
    public function list_data()
    {
        if (!$this->can_view_team_members_list()) {
            return redirect()->to('forbidden');
        }

        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("team_members", $this->login_user->is_admin, $this->login_user->user_type);
        $options = [
            "status" => $this->request->getPost("status"),
            "user_type" => "resource",
            "custom_fields" => $custom_fields,
        ];

        $list_data = $this->Users_model->get_details($options)->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data, $custom_fields);
        }

        return $this->respond(['data' => $result]);
    }

    //get a row data for member list
    private function _row_data($id)
    {
        $custom_fields = $this->CustomFieldsModel->get_available_fields_for_table("team_members", $this->session->user->is_admin, $this->session->user->user_type);

        $options = [
            "id" => $id,
            "custom_fields" => $custom_fields
        ];

        $data = $this->UsersModel->get_details($options)->getRow();
        return $this->_make_row($data, $custom_fields);
    }

    //prepare team member list row
  private function _make_row($data, $custom_fields)
    {
        $image_url = get_avatar($data->image);
        $user_avatar = "<span class='avatar avatar-xs'><img src='$image_url' alt='...'></span>";
        $full_name = $data->first_name . " " . $data->last_name . " ";

        // Check contact info view permissions
        $show_contact_info = $this->can_view_team_members_contact_info();

        $row_data = [
            $user_avatar,
            anchor(route_to('view_team_member', $data->id), $full_name),
            $data->job_title,
            ($show_contact_info) ? $data->email : "",
            ($show_contact_info && $data->phone) ? $data->phone : "-",
            ($show_contact_info && $data->alternative_phone) ? $data->alternative_phone : "-"
        ];

        foreach ($custom_fields as $field) {
            $cf_id = "cfv_" . $field->id;
            $row_data[] = view("custom_fields/output_" . $field->field_type, ["value" => $data->$cf_id]);
        }

        $delete_link = "";
        if ($this->session->user->is_admin && $this->session->user->id != $data->id) {
            $delete_link = js_anchor("<i class='fa fa-times fa-fw'></i>", [
                'title' => lang('delete_team_member'),
                "class" => "delete",
                "data-id" => $data->id,
                "data-action-url" => route_to('delete_team_member'),
                "data-action" => "delete-confirmation"
            ]);
        }

        $row_data[] = $delete_link;

        return $row_data;
    }

    //delete a team member
    public function delete()
    {
        $this->accessOnlyAdmin();

        $id = $this->request->getPost('id');
        
        if ($id != $this->session->user->id && $this->UsersModel->delete($id)) {
            return $this->respond([
                "success" => true,
                'message' => lang('record_deleted')
            ]);
        } else {
            return $this->respond([
                "success" => false,
                'message' => lang('record_cannot_be_deleted')
            ]);
        }
    }

    //show team member's details view
    public function view($id = null, $tab = "")
    {
        if ($id) {
            // Check permissions
            if (!$this->can_view_team_members_list() && $this->login_user->id != $id) {
                return redirect()->to(site_url('forbidden'));
            }

            // Load user details
            $user_info = $this->UsersModel->find($id);
            if ($user_info) {
                $view_data['show_timeline'] = get_setting("module_timeline") ? true : false;
                $view_data['show_general_info'] = $this->can_update_team_members_info($id);
                $view_data['show_job_info'] = false;
                $view_data['show_account_settings'] = false;
                $view_data['show_expense_info'] = (get_setting("module_expense") == "1" && $this->get_access_info("expense")->access_type == "all") ? true : false;

                // Admin can access all members' attendance and leave
                // Non-admin users can only access their own information
                if ($this->login_user->is_admin || $user_info->id === $this->login_user->id) {
                    $view_data['show_attendance'] = get_setting("module_attendance") ? true : false;
                    $view_data['show_leave'] = get_setting("module_leave") ? true : false;
                    $view_data['show_job_info'] = true;
                    $view_data['show_account_settings'] = true;
                } else {
                    // Non-admin users who have access to this team member's attendance and leave can access this info
                    $access_timecard = $this->get_access_info("attendance");
                    $view_data['show_attendance'] = ($access_timecard->access_type === "all" || in_array($user_info->id, $access_timecard->allowed_members)) && get_setting("module_attendance") ? true : false;

                    $access_leave = $this->get_access_info("leave");
                    $view_data['show_leave'] = ($access_leave->access_type === "all" || in_array($user_info->id, $access_leave->allowed_members)) && get_setting("module_leave") ? true : false;
                }

                // Check contact info view permissions
                $view_data['show_contact_info'] = $this->can_view_team_members_contact_info();
                $view_data['show_social_links'] = $this->can_view_team_members_social_links();

                // Own info is always visible
                if ($id == $this->login_user->id) {
                    $view_data['show_contact_info'] = true;
                    $view_data['show_social_links'] = true;
                }

                // Show projects tab to admin
                $view_data['show_projects'] = $this->login_user->is_admin ? true : false;

                $view_data['tab'] = $tab; // Selected tab
                $view_data['user_info'] = $user_info;
                $view_data['social_link'] = $this->SocialLinksModel->get_one($id); // Adjust this based on your model method
                return view('rm_members/view', $view_data);
            } else {
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
            }
        } else {
            if (!$this->can_view_team_members_list()) {
                return redirect()->to(site_url('forbidden'));
            }

            // Load all team members
            $view_data['team_members'] = $this->UsersModel->where('user_type', 'resource')->where('status', 'active')->findAll();
            return view('rm_members/profile_card', $view_data);
        }
    }

    //show the job information of a team member
    public function job_info($user_id)
    {
        $this->only_admin_or_own($user_id);
    
        $options = ["id" => $user_id];
        $user_info = $this->Users_model->get_details($options)->getRow();
    
        $view_data = [
            'user_id' => $user_id,
            'job_info' => $this->Users_model->get_job_info($user_id)
        ];
    
        if ($user_info) {
            $view_data['job_info']->job_title = $user_info->job_title;
        }
    
        return view("rm_members/job_info", $view_data);
    }
    
    //save job information of a team member
    public function save_job_info()
    {
        $this->access_only_admin();
    
        $user_id = $this->request->getPost('user_id');
    
        $job_data = [
            "user_id" => $user_id,
            "salary" => unformat_currency($this->request->getPost('salary')),
            "salary_term" => $this->request->getPost('salary_term'),
            "date_of_hire" => $this->request->getPost('date_of_hire')
        ];
    
        // Save job title in users table
        $user_data = [
            "job_title" => $this->request->getPost('job_title')
        ];
    
        $this->Users_model->save($user_data, $user_id);
    
        if ($this->Users_model->save_job_info($job_data)) {
            return $this->response->setJSON(["success" => true, 'message' => lang('record_updated')]);
        } else {
            return $this->response->setJSON(["success" => false, 'message' => lang('error_occurred')]);
        }
    }
    

    //show general information of a team member
    public function general_info($user_id)
    {
        $this->update_only_allowed_members($user_id);
    
        $view_data = [
            'user_info' => $this->Users_model->get_one($user_id),
            'custom_fields' => $this->Custom_fields_model->get_combined_details("team_members", $user_id, $this->login_user->is_admin, $this->login_user->user_type)->getResult()
        ];
    
        return view("rm_members/general_info", $view_data);
    }
    

    //save general information of a team member
    public function save_general_info($user_id)
    {
        $this->update_only_allowed_members($user_id);
    
        $user_data = [
            "first_name" => $this->request->getPost('first_name'),
            "last_name" => $this->request->getPost('last_name'),
            "address" => $this->request->getPost('address'),
            "phone" => $this->request->getPost('phone'),
            "skype" => $this->request->getPost('skype'),
            "gender" => $this->request->getPost('gender'),
            "alternative_address" => $this->request->getPost('alternative_address'),
            "alternative_phone" => $this->request->getPost('alternative_phone'),
            "dob" => $this->request->getPost('dob'),
            "ssn" => $this->request->getPost('ssn')
        ];
    
        $user_data = clean_data($user_data);
    
        $user_info_updated = $this->Users_model->save($user_data, $user_id);
    
        save_custom_fields("team_members", $user_id, $this->login_user->is_admin, $this->login_user->user_type);
    
        if ($user_info_updated) {
            return $this->response->setJSON(["success" => true, 'message' => lang('record_updated')]);
        } else {
            return $this->response->setJSON(["success" => false, 'message' => lang('error_occurred')]);
        }
    }
    //show social links of a team member
    public function social_links($user_id)
    {
        // Important! Here id=user_id
        $this->update_only_allowed_members($user_id);
    
        $view_data = [
            'user_id' => $user_id,
            'user_type' => "resource",
            'model_info' => $this->Social_links_model->get_one($user_id)
        ];
    
        return view("users/social_links", $view_data);
    }
    
    public function save_social_links($user_id)
    {
        $this->update_only_allowed_members($user_id);
    
        $id = 0;
        $has_social_links = $this->Social_links_model->get_one($user_id);
        if ($has_social_links) {
            $id = $has_social_links->id;
        }
    
        $social_link_data = [
            "facebook" => $this->request->getPost('facebook'),
            "twitter" => $this->request->getPost('twitter'),
            "linkedin" => $this->request->getPost('linkedin'),
            "googleplus" => $this->request->getPost('googleplus'),
            "digg" => $this->request->getPost('digg'),
            "youtube" => $this->request->getPost('youtube'),
            "pinterest" => $this->request->getPost('pinterest'),
            "instagram" => $this->request->getPost('instagram'),
            "github" => $this->request->getPost('github'),
            "tumblr" => $this->request->getPost('tumblr'),
            "vine" => $this->request->getPost('vine'),
            "user_id" => $user_id,
            "id" => $id ? $id : $user_id
        ];
    
        $social_link_data = clean_data($social_link_data);
    
        $this->Social_links_model->save($social_link_data, $id);
    
        return $this->response->setJSON(["success" => true, 'message' => lang('record_updated')]);
    }
    
    //kycinfo
    public function kyc_info($user_id)
    {
        // Important! Here id=user_id
        $this->update_only_allowed_members($user_id);
    
        $view_data = [
            'user_id' => $user_id,
            'user_type' => "resource",
            'model_info' => $this->Kyc_info_model->get_one($user_id)
        ];
    
        return view("users/kyc_info", $view_data);
    }
    
    public function save_kyc_info($user_id)
    {
        $this->update_only_allowed_members($user_id);
    
        $id = 0;
        $has_kyc_info = $this->Kyc_info_model->get_one($user_id);
        if ($has_kyc_info) {
            $id = $has_kyc_info->id;
        }
    
        $kyc_info_data = [
            "aadhar_no" => $this->request->getPost('aadhar_no'),
            "passportno" => $this->request->getPost('passportno'),
            "drivinglicenseno" => $this->request->getPost('drivinglicenseno'),
            "panno" => $this->request->getPost('panno'),
            "voterid" => $this->request->getPost('voterid'),
            "name" => $this->request->getPost('name'),
            "accountnumber" => $this->request->getPost('accountnumber'),
            "bankname" => $this->request->getPost('bankname'),
            "branch" => $this->request->getPost('branch'),
            "ifsc" => $this->request->getPost('ifsc'),
            "micr" => $this->request->getPost('micr'),
            "epf_no" => $this->request->getPost('epf_no'),
            "uan_no" => $this->request->getPost('uan_no'),
            "swift_code" => $this->request->getPost('swift_code'),
            "iban_code" => $this->request->getPost('iban_code'),
            "user_id" => $user_id,
            "id" => $id ? $id : $user_id
        ];
    
        $kyc_info_data = clean_data($kyc_info_data);
    
        $this->Kyc_info_model->save($kyc_info_data, $id);
    
        return $this->response->setJSON(["success" => true, 'message' => lang('record_updated')]);
    }
    

    //show account settings of a team member
    public function account_settings($user_id)
    {
        $this->only_admin_or_own($user_id);
    
        $view_data['user_info'] = $this->Users_model->find($user_id);
        if ($view_data['user_info']->is_admin) {
            $view_data['user_info']->role_id = "admin";
            $view_data['user_info']->user_type = "resource";
        }
        $view_data['role_dropdown'] = $this->_get_roles_dropdown();
        return view("users/account_settings", $view_data);
    }
    //show my preference settings of a team member
    public function my_preferences()
    {
        $user_id = $this->session->get('user_id');
        $view_data["user_info"] = $this->Users_model->find($user_id);
    
        $view_data['language_dropdown'] = [];
        if (!get_setting("disable_language_selector_for_team_members")) {
            $view_data['language_dropdown'] = get_language_list();
        }
    
        $tzlist = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
        $view_data['timezone_dropdown'] = [];
        foreach ($tzlist as $zone) {
            $view_data['timezone_dropdown'][$zone] = $zone;
        }
    
        $view_data["hidden_topbar_menus_dropdown"] = $this->get_hidden_topbar_menus_dropdown();
    
        return view("team_members/my_preferences", $view_data);
    }
    public function save_my_preferences()
{
    $user_id = $this->session->get('user_id');

    $settings = ["notification_sound_volume", "hidden_topbar_menus"];
    if (!get_setting("disable_language_selector_for_team_members")) {
        $settings[] = "personal_language";
    }

    foreach ($settings as $setting) {
        $value = $this->request->getPost($setting);
        if (is_null($value)) {
            $value = "";
        }
        $this->Settings_model->save_setting("user_" . $user_id . "_" . $setting, $value, "user");
    }

    $user_data = [
        "enable_web_notification" => $this->request->getPost("enable_web_notification"),
        "enable_email_notification" => $this->request->getPost("enable_email_notification"),
        "user_timezone" => $this->request->getPost("user_timezone"),
    ];

    $user_data = clean_data($user_data);

    $this->Users_model->update($user_id, $user_data);

    return json_encode(["success" => true, 'message' => lang('settings_updated')]);
}

public function save_personal_language($language)
{
    $user_id = $this->session->get('user_id');

    if (!get_setting("disable_language_selector_for_team_members") && ($language || $language === "0")) {
        $language = clean_data($language);
        $this->Settings_model->save_setting("user_" . $user_id . "_personal_language", strtolower($language), "user");
    }
}
private function _get_roles_dropdown()
{
    $role_dropdown = [
        "0" => lang('team_member'),
        "admin" => lang('admin') //static role
    ];

    $roles = $this->Roles_model->findAll();
    foreach ($roles as $role) {
        $role_dropdown[$role['id']] = $role['title'];
    }
    return $role_dropdown;
}


    //save account settings of a team member
    public function save_account_settings($user_id)
    {
        $this->only_admin_or_own($user_id);
    
        if ($this->Users_model->is_email_exists($this->request->getPost('email'), $user_id)) {
            return json_encode(["success" => false, 'message' => lang('duplicate_email')]);
        }
    
        $account_data = [
            "email" => $this->request->getPost('email')
        ];
    
        if ($this->session->get('user_id') == $user_id || $this->session->get('is_admin')) {
            $role = $this->request->getPost('role');
            $role_id = $role === "admin" ? 0 : $role;
    
            $account_data["is_admin"] = $role === "admin" ? 1 : 0;
            $account_data["role_id"] = $role_id;
    
            $account_data['disable_login'] = $this->request->getPost('disable_login');
            $account_data['status'] = $this->request->getPost('status') === "inactive" ? "inactive" : "active";
    
            if ($this->request->getPost('password')) {
                $account_data['password'] = md5($this->request->getPost('password'));
            }
        }
    
        if ($this->Users_model->update($user_id, $account_data)) {
            return json_encode(["success" => true, 'message' => lang('record_updated')]);
        } else {
            return json_encode(["success" => false, 'message' => lang('error_occurred')]);
        }
    }

  
  
    //save profile image of a team member
    public function save_profile_image($user_id = 0)
    {
        $this->update_only_allowed_members($user_id);
    
        // Process the file uploaded by dropzone
        $profile_image = str_replace("~", ":", $this->request->getPost("profile_image"));
    
        if ($profile_image) {
            $profile_image = move_temp_file("avatar.png", get_setting("profile_image_path"), "", $profile_image);
    
            $image_data = ["image" => $profile_image];
    
            $this->Users_model->save($image_data, $user_id);
            return json_encode(["success" => true, 'message' => lang('profile_image_changed')]);
        }
    
        // Process the file uploaded using manual file submit
        if ($_FILES) {
            $profile_image_file = $this->request->getFiles('profile_image_file');
            $image_file_name = $profile_image_file->getTempName();
            if ($image_file_name) {
                $profile_image = move_temp_file("avatar.png", get_setting("profile_image_path"), "", $image_file_name);
                $image_data = ["image" => $profile_image];
                $this->Users_model->save($image_data, $user_id);
                return json_encode(["success" => true, 'message' => lang('profile_image_changed')]);
            }
        }
    } 
    public function projects_info($user_id)
    {
        if ($user_id) {
            $view_data['user_id'] = $user_id;
            $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("projects", $this->session->get('is_admin'), $this->session->get('user_type'));
            return view("rm_members/projects_info", $view_data);
        }
    }
    
    public function attendance_info($user_id)
    {
        if ($user_id) {
            $view_data['user_id'] = $user_id;
            return view("rm_members/attendance_info", $view_data);
        }
    }
    

    //show weekly attendance list of a team member
    public function weekly_attendance()
    {
        return view("rm_members/weekly_attendance");
    }
    
    public function custom_range_attendance()
    {
        return view("rm_members/custom_range_attendance");
    }
    

    public function attendance_summary($user_id)
    {
        $view_data["user_id"] = $user_id;
        return view("rm_members/attendance_summary", $view_data);
    }
    
    //show leave list of a team member
    public function leave_info($applicant_id)
    {
        if ($applicant_id) {
            $view_data['applicant_id'] = $applicant_id;
            return view("rm_members/leave_info", $view_data);
        }
    }
    
    public function yearly_leaves()
    {
        return view("rm_members/yearly_leaves");
    }
    //show yearly leave list of a team member
    public function expense_info($user_id) {
        $view_data["user_id"] = $user_id;
        return view("rm_members/expenses",$view_data);
    }

    /* load files tab */
    public function files($user_id)
    {
        $this->update_only_allowed_members($user_id);
    
        $options = ["user_id" => $user_id];
        $view_data['files'] = $this->General_files_model->get_details($options)->getResult();
        $view_data['user_id'] = $user_id;
        return view("rm_members/files/index", $view_data);
    }
    

    /* file upload modal */

    public function file_modal_form()
    {
        $view_data['model_info'] = $this->General_files_model->find($this->request->getPost('id'));
        $user_id = $this->request->getPost('user_id') ? $this->request->getPost('user_id') : $view_data['model_info']->user_id;
    
        $this->update_only_allowed_members($user_id);
    
        $view_data['user_id'] = $user_id;
        return view('rm_members/files/modal_form', $view_data);
    }
    
    /* save file data and move temp file to parmanent file directory */

    public function save_file()
{
    $user_id = $this->request->getPost('user_id');
    $this->update_only_allowed_members($user_id);

    $files = $this->request->getPost("files");
    $success = false;
    $now = date('Y-m-d H:i:s');

    $target_path = WRITEPATH . "uploads/team_members/" . $user_id . "/";

    if ($files && $files[0]) {
        foreach ($files as $file) {
            $file_name = $this->request->getPost('file_name_' . $file);
            $new_file_name = move_temp_file($file_name, $target_path);
            if ($new_file_name) {
                $data = [
                    "user_id" => $user_id,
                    "file_name" => $new_file_name,
                    "description" => $this->request->getPost('description_' . $file),
                    "file_size" => $this->request->getPost('file_size_' . $file),
                    "created_at" => $now,
                    "uploaded_by" => $this->session->get('user_id')
                ];
                $success = $this->General_files_model->save($data);
            } else {
                $success = false;
            }
        }
    }

    if ($success) {
        return json_encode(["success" => true, 'message' => lang('record_saved')]);
    } else {
        return json_encode(["success" => false, 'message' => lang('error_occurred')]);
    }
}

    /* list of files, prepared for datatable  */
    public function files_list_data($user_id = 0)
    {
        $this->update_only_allowed_members($user_id);
    
        $options = ["user_id" => $user_id];
        $list_data = $this->General_files_model->get_details($options)->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_file_row($data);
        }
        return json_encode(["data" => $result]);
    }
    

    private function _make_file_row($data)
    {
        $file_icon = get_file_icon(strtolower(pathinfo($data->file_name, PATHINFO_EXTENSION)));
        $uploaded_by = get_rm_member_profile_link($data->uploaded_by, $data->uploaded_by_user_name);
    
        $description = "<div class='pull-left'>" . js_anchor(remove_file_prefix($data->file_name), [
            'title' => "",
            "data-toggle" => "app-modal",
            "data-sidebar" => "0",
            "data-url" => get_uri("team_members/view_file/" . $data->id)
        ]);
    
        if ($data->description) {
            $description .= "<br /><span>" . $data->description . "</span></div>";
        } else {
            $description .= "</div>";
        }
    
        $options = anchor(get_uri("rm_members/download_file/" . $data->id), "<i class='fa fa-cloud-download'></i>", ["title" => lang("download")]);
        $options .= js_anchor("<i class='fa fa-times fa-fw'></i>", [
            'title' => lang('delete_file'),
            "class" => "delete",
            "data-id" => $data->id,
            "data-action-url" => get_uri("rm_members/delete_file"),
            "data-action" => "delete-confirmation"
        ]);
    
        return [
            $data->id,
            "<div class='fa fa-$file_icon font-22 mr10 pull-left'></div>" . $description,
            convert_file_size($data->file_size),
            $uploaded_by,
            format_to_datetime($data->created_at),
            $options
        ];
    }
    

    public function view_file($file_id = 0)
{
    $file_info = $this->General_files_model->find($file_id);

    if ($file_info) {
        if (!$file_info->user_id) {
            return redirect()->to("forbidden");
        }

        $this->update_only_allowed_members($file_info->user_id);

        $view_data['can_comment_on_files'] = false;
        $view_data["file_url"] = get_file_uri(WRITEPATH . "uploads/team_members/" . $file_info->user_id . "/" . $file_info->file_name);
        $view_data["is_image_file"] = is_image_file($file_info->file_name);
        $view_data["is_google_preview_available"] = is_google_preview_available($file_info->file_name);
        $view_data["file_info"] = $file_info;
        $view_data['file_id'] = $file_id;

        return view("rm_members/files/view", $view_data);
    } else {
        return show_404();
    }
}

    /* download a file */

    public function download_file($id)
    {
        $file_info = $this->General_files_model->find($id);
    
        if (!$file_info || !$file_info->user_id) {
            return redirect()->to("forbidden");
        }
    
        $this->update_only_allowed_members($file_info->user_id);
    
        $file_path = WRITEPATH . "uploads/team_members/" . $file_info->user_id . "/" . $file_info->file_name;
    
        return $this->response->download($file_path, null);
    }
    
    /* upload a post file */
    public function upload_file()
    {
        upload_file_to_temp();
    }
    /* check valid file for user */

     public  function validate_file() {
        return validate_post_file($this->input->post("file_name"));
    }

    /* delete a file */

    public function delete_file()
    {
        $id = $this->request->getPost('id');
        $info = $this->General_files_model->find($id);
    
        if (!$info || !$info->user_id) {
            return redirect()->to("forbidden");
        }
    
        $this->update_only_allowed_members($info->user_id);
    
        if ($this->General_files_model->delete($id)) {
            $file_path = WRITEPATH . "uploads/team_members/" . $info->user_id . "/" . $info->file_name;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            return json_encode(["success" => true, 'message' => lang('record_deleted')]);
        } else {
            return json_encode(["success" => false, 'message' => lang('record_cannot_be_deleted')]);
        }
    }
}
    
/* End of file team_member.php */
/* Location: ./application/controllers/team_member.php */