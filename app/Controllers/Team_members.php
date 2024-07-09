<?php

namespace App\Controllers;

use App\Models\CountriesModel;
use App\Models\CustomFieldsModel;
use App\Models\UsersModel;
use App\Models\EmailTemplatesModel;
use App\Models\SocialLinksModel;
use App\Models\BranchesModel;
use App\Models\DesignationModel;
use App\Models\KycInfoModel;
use App\Models\SettingsModel;
use App\Models\RolesModel;
use App\Models\CompanysModel;
use App\Models\DepartmentsModel;
use App\Models\BankNameModel;
use App\Models\GeneralFilesModel;
use CodeIgniter\API\ResponseTrait;

class TeamMembers extends BaseController
{
    use ResponseTrait;

    protected $countriesModel;
    protected $customFieldsModel;
    protected $usersModel;
    protected $emailTemplatesModel;
    protected $socialLinksModel;
    protected $branchesModel;
    protected $designationModel;
    protected $kycInfoModel;
    protected $settingsModel;
    protected $rolesModel;
    protected $companysModel;
    protected $departmentsModel;
    protected $bankNameModel;
    protected $generalFilesModel;

    public function __construct()
    {
        $this->countriesModel = new CountriesModel();
        $this->customFieldsModel = new CustomFieldsModel();
        $this->usersModel = new UsersModel();
        $this->emailTemplatesModel = new EmailTemplatesModel();
        $this->socialLinksModel = new SocialLinksModel();
        $this->branchesModel = new BranchesModel();
        $this->designationModel = new DesignationModel();
        $this->kycInfoModel = new KycInfoModel();
        $this->settingsModel = new SettingsModel();
        $this->rolesModel = new RolesModel();
        $this->companysModel = new CompanysModel();
        $this->departmentsModel = new DepartmentsModel();
        $this->bankNameModel = new BankNameModel();
        $this->generalFilesModel = new GeneralFilesModel();

        helper(['form', 'url']);
    }
    private function canViewTeamMembersContactInfo()
    {
        if ($this->login_user->user_type == "staff") {
            if ($this->login_user->is_admin || get_array_value($this->login_user->permissions, "can_view_team_members_contact_info") == "1") {
                return true;
            }
        }
        return false;
    }
    private function canViewTeamMembersSocialLinks()
    {
        if ($this->login_user->user_type == "staff") {
            if ($this->login_user->is_admin || get_array_value($this->login_user->permissions, "can_view_team_members_social_links") == "1") {
                return true;
            }
        }
        return false;
    }
    public function getCurrencySymbol()
    {
        $options = [
            "numberCode" => $this->request->getPost("item_name")
        ];
        
        $item = $this->countriesModel->getDetails($options)->getRow();

        if ($item) {
            $status = $item->currency_symbol;
            return $this->respond([
                "success" => true,
                "item_status" => $status
            ]);
        } else {
            return $this->respond([
                "success" => false
            ]);
        }
    }
    private function updateOnlyAllowedMembers($user_id)
    {
        if ($this->canUpdateTeamMembersInfo($user_id)) {
            return true; // Own profile
        } else {
            return redirect()->to(site_url('forbidden'));
        }
    }//only admin can change other user's info
    //none admin users can only change his/her own info
    //allowed members can update other members info
    private function canUpdateTeamMembersInfo($user_id)
    {
        $access_info = $this->getAccessInfo("team_member_update_permission");

        if ($this->login_user->id === $user_id) {
            return true; // Own profile
        } elseif ($access_info->access_type == "all") {
            return true; // Has access to change all user's profile
        } elseif ($user_id && in_array($user_id, $access_info->allowed_members)) {
            return true; // Has permission to update this user's profile
        } else {
            return false;
        }
    }

    //only admin can change other user's info
    //none admin users can only change his/her own info
    private function onlyAdminOrOwn($user_id)
    {
        if ($user_id && ($this->login_user->is_admin || $this->login_user->id === $user_id)) {
            return true;
        } else {
            return redirect()->to(site_url('forbidden'));
        }
    }
    public function index()
    {
        if (!$this->canViewTeamMembersList()) {
            return redirect()->to(site_url('forbidden'));
        }

        $view_data = [
            "show_contact_info" => $this->canViewTeamMembersContactInfo(),
            "custom_field_headers" => $this->customFieldsModel->getCustomFieldHeadersForTable("team_members", $this->login_user->is_admin, $this->login_user->user_type)
        ];

        // $this->template->render("team_members/index", $view_data);
        return view("team_members/index", $view_data);
    }

    /* open new member modal */

    public function modalForm()
    {
        $this->accessOnlyAdmin();

        // Validate submitted data
        helper('form');
        $rules = [
            'id' => 'numeric'
        ];
        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $viewData = [
            'role_dropdown' => $this->_getRolesDropdown(),
            'country_dropdown' => $this->_getCountriesDropdown(),
            'company_dropdown' => $this->_getCompaniesDropdown(),
            'branches_dropdown' => $this->_getBranchesDropdown(),
            'designation_dropdown' => $this->_getDesignationDropdown(),
            'department_dropdown' => array("" => "-") + $this->_getDepartmentDropdown(),
        ];

        $id = $this->request->getPost('id');
        $options = ["id" => $id];
        $viewData['model_info'] = $this->usersModel->getDetails($options)->getRow();
        $options = ["user_type" => 'staff'];
        $viewData['custom_fields'] = $this->customFieldsModel->getCombinedDetails("team_members", 0, $this->login_user->is_admin, $this->login_user->user_type)->getResult();

        // Annual leave dropdown
        $annual_leave_dropdown = range(1, 365);
        $viewData['annual_leave_dropdown'] = array_combine($annual_leave_dropdown, $annual_leave_dropdown);

        return view('team_members/modal_form', $viewData);
    }

    public function addTeamMember()
    {
        $this->accessOnlyAdmin();

        // Validate submitted data
        helper('form');
        $rules = [
            'email' => 'required|valid_email',
            'first_name' => 'required',
            'last_name' => 'required',
            'job_title' => 'required',
            'role' => 'required'
        ];
        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        // Check duplicate email address
        if ($this->usersModel->isEmailExists($this->request->getPost('email'))) {
            return $this->fail(json_encode(array("success" => false, 'message' => lang('duplicate_email'))));
        }

        // Prepare user data
        $userData = [
            "email" => $this->request->getPost('email'),
            "password" => md5($this->request->getPost('password')),
            "first_name" => $this->request->getPost('first_name'),
            "last_name" => $this->request->getPost('last_name'),
            "is_admin" => $this->request->getPost('is_admin'),
            "address" => $this->request->getPost('address'),
            "phone" => $this->request->getPost('phone'),
            "gender" => $this->request->getPost('gender'),
            "job_title" => $this->request->getPost('job_title'),
            "employee_id" => $this->request->getPost('employee_id'),
            "virtual_id" => $this->request->getPost('virtual_id'),
            "country" => $this->request->getPost('country'),
            "department" => $this->request->getPost('department'),
            "designation" => $this->request->getPost('designation'),
            "branch" => $this->request->getPost('branch'),
            "user_type" => "staff",
            "work_mode" => $this->request->getPost('work_mode'),
            "created_at" => gmdate('Y-m-d H:i:s'),
            "annual_leave" => $this->request->getPost('annual_leave'),
            "company_id" => $this->request->getPost('company'),
            "buid" => $this->request->getPost('buid'),
        ];

        // Set role id or admin permission
        $role = $this->request->getPost('role');
        $role_id = $role === "admin" ? 0 : $role;
        $userData["is_admin"] = $role === "admin" ? 1 : 0;
        $userData["role_id"] = $role_id;

        // Add a new team member
        $userId = $this->usersModel->save($userData);
        if ($userId) {
            // Save job info for the user
            $jobData = [
                "user_id" => $userId,
                "salary" => $this->request->getPost('salary') ? $this->request->getPost('salary') : 0,
                "salary_term" => $this->request->getPost('salary_term'),
                "date_of_hire" => $this->request->getPost('date_of_hire'),
                "currency_symbol" => $this->request->getPost('currency_symbol'),
                "currency" => $this->request->getPost('currency')
            ];
            $this->usersModel->saveJobInfo($jobData);

            // Save custom fields
            save_custom_fields("team_members", $userId, $this->login_user->is_admin, $this->login_user->user_type);

            // Send login details to user
            if ($this->request->getPost('email_login_details')) {
                // Get the login details template
                $emailTemplate = $this->emailTemplatesModel->getFinalTemplate("login_info");

                // Parse email template data
                $parserData = [
                    "SIGNATURE" => $emailTemplate->signature,
                    "USER_FIRST_NAME" => $userData["first_name"],
                    "USER_LAST_NAME" => $userData["last_name"],
                    "USER_LOGIN_EMAIL" => $userData["email"],
                    "USER_LOGIN_PASSWORD" => $this->request->getPost('password'),
                    "DASHBOARD_URL" => base_url(),
                    "LOGO_URL" => get_logo_url()
                ];

                $message = $this->parser->setData($parserData)->renderString($emailTemplate->message);
                send_app_mail($this->request->getPost('email'), $emailTemplate->subject, $message);
            }

            return $this->respondCreated([
                "success" => true,
                "data" => $this->_rowData($userId),
                'id' => $userId,
                'message' => lang('record_saved')
            ]);
        } else {
            return $this->fail([
                "success" => false,
                'message' => lang('error_occurred')
            ]);
        }
    }
    public function getEmployeeDetails()
    {
        $branch = $this->request->getPost('branch');
        $designation = $this->request->getPost('designation');
        $country = $this->request->getPost('country');
        $department = $this->request->getPost('department');
        $company = $this->request->getPost('company');

        $item = 1 + $this->usersModel->getItemInfoSuggestion($branch, $designation, $country, $department, $company);
        $items = 1 + $this->usersModel->getItemInfoSuggestionId($branch, $company);

        $str2 = substr($company, 2);

        if ($item) {
            return $this->respond([
                "success" => true,
                "item_info" => $item,
                "e_id" => $items,
                "company_id" => $str2
            ]);
        } else {
            return $this->respond(["success" => false]);
        }
    }
    public function invitationModal()
    {
        $this->accessOnlyAdmin();
        return view('team_members/invitation_modal');
    }
    public function sendInvitation()
    {
        $this->accessOnlyAdmin();

        $validation = \Config\Services::validation();
        $validation->setRules([
            'email[]' => 'required|valid_email'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $emailArray = array_unique($this->request->getPost('email'));

        $emailTemplate = $this->emailTemplatesModel->getFinalTemplate("team_member_invitation");

        $parserData = [
            "INVITATION_SENT_BY" => $this->login_user->first_name . " " . $this->login_user->last_name,
            "SIGNATURE" => $emailTemplate->signature,
            "SITE_URL" => base_url(),
            "LOGO_URL" => get_logo_url()
        ];

        $sendEmail = [];

        foreach ($emailArray as $email) {
            $key = encode_id($this->encryption->encrypt('staff|' . $email . '|' . (time() + (24 * 60 * 60))), "signup");
            $parserData['INVITATION_URL'] = site_url("signup/accept_invitation/" . $key);

            $message = $this->parser->setData($parserData)->renderString($emailTemplate->message);
            $sendEmail[] = send_app_mail($email, $emailTemplate->subject, $message);
        }

        if (!in_array(false, $sendEmail)) {
            if (count($sendEmail) != 0 && count($sendEmail) == 1) {
                return $this->respond([
                    'success' => true,
                    'message' => lang("invitation_sent")
                ]);
            } else {
                return $this->respond([
                    'success' => true,
                    'message' => lang("invitations_sent")
                ]);
            }
        } else {
            return $this->respond([
                'success' => false,
                'message' => lang('error_occurred')
            ]);
        }
    }
    public function listData()
    {
        if (!$this->canViewTeamMembersList()) {
            return redirect()->to("forbidden");
        }

        $customFields = $this->customFieldsModel->getAvailableFieldsForTable("team_members", $this->login_user->is_admin, $this->login_user->user_type);

        $options = [
            "status" => $this->request->getPost("status"),
            "user_type" => "staff",
            "custom_fields" => $customFields
        ];

        if (!$this->login_user->is_admin) {
            $options["is_admin"] = "1";
        }

        $listData = $this->usersModel->getDetails($options)->getResult();
        $result = [];

        foreach ($listData as $data) {
            $result[] = $this->_makeRow($data, $customFields);
        }

        return $this->respond([
            "data" => $result
        ]);
    }
    public function rowData($id)
    {
        $customFields = $this->customFieldsModel->getAvailableFieldsForTable("team_members", $this->login_user->is_admin, $this->login_user->user_type);

        $options = [
            "id" => $id,
            "custom_fields" => $customFields
        ];

        $data = $this->usersModel->getDetails($options)->getRow();
        return $this->respond($this->_makeRow($data, $customFields));
    }

    //prepare team member list row
    private function _makeRow($data, $customFields)
    {
        $imageUrl = get_avatar($data->image);
        $userAvatar = "<span class='avatar avatar-xs'><img src='$imageUrl' alt='...'></span>";
        $fullName = $data->first_name . " " . $data->last_name . " ";
    
        // Check contact info view permissions
        $showContactInfo = $this->canViewTeamMembersContactInfo();
    
        $workMode = ($data->work_mode == 0) ? "Indoor" : "Outdoor";
    
        $rowData = [
            $userAvatar,
            get_team_member_profile_link($data->id, $fullName),
            $data->job_title,
            $data->role_title,
            $workMode,
            $showContactInfo ? $data->email : "",
            $showContactInfo && $data->phone ? $data->phone : "-",
            $showContactInfo && $data->alternative_phone ? $data->alternative_phone : "-"
        ];
    
        foreach ($customFields as $field) {
            $cfId = "cfv_" . $field->id;
            $rowData[] = view("custom_fields/output_" . $field->field_type, ["value" => $data->$cfId]);
        }
    
        $deleteLink = "";
        if ($this->loginUser->is_admin && $this->loginUser->id != $data->id) {
            $deleteLink = anchor(
                "<i class='fa fa-times fa-fw'></i>",
                '',
                [
                    'title' => lang('delete_team_member'),
                    'class' => 'delete',
                    'data-id' => $data->id,
                    'data-action-url' => site_url("team_members/delete"),
                    'data-action' => 'delete-confirmation'
                ]
            );
        }
    
        $rowData[] = $deleteLink;
    
        return $rowData;
    }
    //delete a team member
    public function delete()
    {
        $this->access_only_admin();

        $id = $this->request->getPost('id');

        if ($id != $this->login_user->id && $this->usersModel->delete($id)) {
            return $this->response->setJSON(['success' => true, 'message' => lang('record_deleted')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
        }
    }

    //show team member's details view
    public function view($id = 0, $tab = "")
    {
        if ($id) {
            if (!$this->can_view_team_members_list() && $this->login_user->id != $id) {
                return redirect()->to('forbidden');
            }

            $user_info = $this->usersModel->getDetails(['id' => $id, 'user_type' => 'staff'])->getRow();

            if ($user_info) {
                $view_data = [
                    'show_timeline' => get_setting("module_timeline") ? true : false,
                    'show_general_info' => $this->can_update_team_members_info($id),
                    'show_job_info' => false,
                    'show_account_settings' => false,
                    'show_expense_info' => get_setting("module_expense") == "1" && $this->get_access_info("expense")->access_type == "all"
                ];

                $show_attendance = false;
                $show_leave = false;
                $show_payslip = false;
                $show_bank_statement = false;

                if ($this->login_user->is_admin || $user_info->id === $this->login_user->id) {
                    $show_attendance = true;
                    $show_leave = true;
                    $show_payslip = true;
                    $access_bank_statement = $this->get_access_info("bank_statement");
                    if ($access_bank_statement->access_type === "all" || in_array($this->login_user->id, $access_bank_statement->allowed_members)) {
                        $show_bank_statement = true;
                    }
                    $view_data['show_job_info'] = true;
                    $view_data['show_account_settings'] = true;
                } else {
                    $access_timecard = $this->get_access_info("attendance");
                    if ($access_timecard->access_type === "all" || in_array($user_info->id, $access_timecard->allowed_members)) {
                        $show_attendance = true;
                    }

                    $access_leave = $this->get_access_info("leave");
                    if ($access_leave->access_type === "all" || in_array($user_info->id, $access_leave->allowed_members)) {
                        $show_leave = true;
                    }

                    $access_payslip = $this->get_access_info("payslip");
                    if ($access_payslip->access_type === "all" || in_array($user_info->id, $access_payslip->allowed_members)) {
                        $show_payslip = true;
                    }
                }

                $view_data['show_attendance'] = $show_attendance && get_setting("module_attendance") ? true : false;
                $view_data['show_leave'] = $show_leave && get_setting("module_leave") ? true : false;
                $view_data['show_payslip'] = $show_payslip && get_setting("module_payslip") ? true : false;

                $show_contact_info = $this->can_view_team_members_contact_info();
                $show_social_links = $this->can_view_team_members_social_links();

                if ($id == $this->login_user->id) {
                    $show_contact_info = true;
                    $show_social_links = true;
                }

                $view_data['show_bank_statement'] = $show_bank_statement;
                $view_data['show_contact_info'] = $show_contact_info;
                $view_data['show_social_links'] = $show_social_links;
                $view_data['show_projects'] = $this->login_user->is_admin;
                $view_data['tab'] = $tab;
                $view_data['user_info'] = $user_info;
                $view_data['social_link'] = $this->socialLinksModel->find($id);

                return view('team_members/view', $view_data);
            } else {
                throw new \CodeIgniter\Exceptions\PageNotFoundException();
            }
        } else {
            if (!$this->can_view_team_members_list()) {
                return redirect()->to('forbidden');
            }

            $view_data['team_members'] = $this->usersModel->getDetails(['user_type' => 'staff', 'status' => 'active'])->getResult();
            return view('team_members/profile_card', $view_data);
        }
    }
      //show the job information of a team member
      public function job_info($user_id)
      {
          $this->only_admin_or_own($user_id);
  
          $user_info = $this->usersModel->getDetails(['id' => $user_id])->getRow();
  
          $view_data = [
              'user_id' => $user_id,
              'job_info' => $this->usersModel->getJobInfo($user_id),
              'user_info' => $user_info,
              'company_dropdown' => $this->_get_companies_dropdown(),
              'country_dropdown' => [""] + $this->_get_countries_dropdown(),
              'department_dropdown' => [""] + $this->_get_department_dropdown(),
          ];
          $view_data['job_info']->job_title = $user_info->job_title;
  
          $company_branch = $this->branchesModel->where(['deleted' => 0, 'company_name' => $view_data['user_info']->company_id])->findAll();
          $company_branch_dropdown = [['id' => "", 'text' => "-"]];
          foreach ($company_branch as $branch) {
              $company_branch_dropdown[] = ['id' => $branch['branch_code'], 'text' => $branch['title']];
          }
          $view_data['company_branch_dropdown'] = json_encode($company_branch_dropdown);
  
          $designation_dropdown = [['id' => "", 'text' => "-"]];
          $designations = $this->designationModel->where(['deleted' => 0, 'department_code' => $view_data['user_info']->department])->findAll();
          foreach ($designations as $designation) {
              $designation_dropdown[] = ['id' => $designation['designation_code'], 'text' => $designation['title']];
          }
          $view_data['designation_dropdown'] = json_encode($designation_dropdown);
  
          $annual_leave_dropdown = array_combine(range(1, 365), range(1, 365));
          $view_data['annual_leave_dropdown'] = $annual_leave_dropdown;
  
          return view('team_members/job_info', $view_data);
      }

    //save job information of a team member
   
    public function save_job_info()
    {
        $this->access_only_admin();

        $rules = [
            'user_id' => 'required|numeric'
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON(['success' => false, 'message' => $this->validator->getErrors()]);
        }

        $user_id = $this->request->getPost('user_id');

        $job_data = [
            'user_id' => $user_id,
            'salary' => unformat_currency($this->request->getPost('salary')),
            'salary_term' => $this->request->getPost('salary_term'),
            'date_of_hire' => $this->request->getPost('date_of_hire'),
            'currency_symbol' => $this->request->getPost('currency_symbol'),
            'currency' => $this->request->getPost('currency'),
        ];

        $user_data = [
            'job_title' => $this->request->getPost('job_title'),
            'country' => $this->request->getPost('country'),
            'department' => $this->request->getPost('department'),
            'designation' => $this->request->getPost('designation'),
            'branch' => $this->request->getPost('branch'),
            'annual_leave' => $this->request->getPost('annual_leave'),
            'buid' => $this->request->getPost('buid'),
            'company_id' => $this->request->getPost('company_id'),
        ];

        $this->usersModel->update($user_id, $user_data);
        if ($this->usersModel->saveJobInfo($job_data)) {
            return $this->response->setJSON(['success' => true, 'message' => lang('record_updated')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }


    public function general_info($user_id)
    {
        $this->update_only_allowed_members($user_id);

        $view_data['user_info'] = $this->usersModel->find($user_id);
        $view_data['custom_fields'] = $this->customFieldsModel->get_combined_details('team_members', $user_id, $this->login_user->is_admin, $this->login_user->user_type)->getResult();

        return view('team_members/general_info', $view_data);
    }
    public function save_general_info($user_id)
    {
        $this->update_only_allowed_members($user_id);

        $rules = [
            'first_name' => 'required',
            'last_name' => 'required',
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON(['success' => false, 'message' => $this->validator->getErrors()]);
        }

        // Check personal email
        if ($this->request->getPost('personal_email') && $this->usersModel->is_personal_email_exists($this->request->getPost('personal_email'), $user_id)) {
            return $this->response->setJSON(['success' => false, 'message' => lang('duplicate_email')]);
        }

        $sign = str_replace("~", ":", $this->request->getPost('file_names'));
        $sign = move_temp_file($sign[0], get_setting('profile_image_path') . '/signature/', '', null, $user_id . '.jpg');

        $user_data = [
            'first_name' => $this->request->getPost('first_name'),
            'last_name' => $this->request->getPost('last_name'),
            'address' => $this->request->getPost('address'),
            'phone' => $this->request->getPost('phone'),
            'skype' => $this->request->getPost('skype'),
            'gender' => $this->request->getPost('gender'),
            'alternative_address' => $this->request->getPost('alternative_address'),
            'alternative_phone' => $this->request->getPost('alternative_phone'),
            'dob' => $this->request->getPost('dob'),
            'ssn' => $this->request->getPost('ssn'),
            'blood_group' => $this->request->getPost('blood_group'),
            'personal_email' => $this->request->getPost('personal_email'),
            'signature' => $sign
        ];
        $user_data = clean_data($user_data);

        $user_info_updated = $this->usersModel->update($user_id, $user_data);

        save_custom_fields('team_members', $user_id, $this->login_user->is_admin, $this->login_user->user_type);

        if ($user_info_updated) {
            return $this->response->setJSON(['success' => true, 'message' => lang('record_updated')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }
    //kycinfo
    public function kyc_info($user_id)
    {
        $this->update_only_allowed_members($user_id);

        $view_data = [
            'user_id' => $user_id,
            'model_info' => $this->kycInfoModel->find($user_id)
        ];

        return view('users/kyc_info', $view_data);
    }

    //save social links of a team member
    public function save_kyc_info($user_id)
    {
        $this->update_only_allowed_members($user_id);

        $id = 0;
        $has_kyc_info = $this->kycInfoModel->find($user_id);
        if ($has_kyc_info) {
            $id = $has_kyc_info['id'];
        }

        $kyc_info_data = [
            'aadhar_no' => $this->request->getPost('aadhar_no'),
            'passportno' => $this->request->getPost('passportno'),
            'drivinglicenseno' => $this->request->getPost('drivinglicenseno'),
            'panno' => $this->request->getPost('panno'),
            'voterid' => $this->request->getPost('voterid'),
            'name' => $this->request->getPost('name'),
            'accountnumber' => $this->request->getPost('accountnumber'),
            'bankname' => $this->request->getPost('bankname'),
            'branch' => $this->request->getPost('branch'),
            'ifsc' => $this->request->getPost('ifsc'),
            'micr' => $this->request->getPost('micr'),
            'epf_no' => $this->request->getPost('epf_no'),
            'uan_no' => $this->request->getPost('uan_no'),
            'swift_code' => $this->request->getPost('swift_code'),
            'iban_code' => $this->request->getPost('iban_code'),
            'user_id' => $user_id,
            'id' => $id ?: $user_id
        ];

        $kyc_info_data = clean_data($kyc_info_data);

        $this->kycInfoModel->save($kyc_info_data);

        return $this->response->setJSON(['success' => true, 'message' => lang('record_updated')]);
    }

    private function _get_line_manager_dropdown()
    {
        $post_dropdown = [
            // '0' => lang('team_member'),  // Commented out as it was not active in the original method
            // 'admin' => lang('admin')    // Commented out as it was not active in the original method
        ];
        
        $options = ['user_type' => 'staff', 'status' => 'active'];
        $line_managers = $this->usersModel->where($options)->findAll();

        foreach ($line_managers as $line_manager) {
            $post_dropdown[$line_manager['id']] = $line_manager['first_name'] . ' ' . $line_manager['last_name'];
        }

        return $post_dropdown;
    }

    //show account settings of a team member
    public function account_settings($user_id)
    {
        $this->only_admin_or_own($user_id);

        $view_data = [
            'user_info' => $this->usersModel->find($user_id),
            'role_dropdown' => $this->_get_roles_dropdown(),
            'line_manager' => ['' => '-'] + ['admin' => 'Admin'] + $this->_get_line_manager_dropdown()
        ];

        return view('users/account_settings', $view_data);
    }
    public function my_preferences()
    {
        $view_data = [
            'user_info' => $this->usersModel->find($this->login_user->id),
            'language_dropdown' => [],
            'timezone_dropdown' => array_combine(DateTimeZone::listIdentifiers(DateTimeZone::ALL), DateTimeZone::listIdentifiers(DateTimeZone::ALL)),
            'hidden_topbar_menus_dropdown' => $this->get_hidden_topbar_menus_dropdown()
        ];

        if (!get_setting("disable_language_selector_for_team_members")) {
            $view_data['language_dropdown'] = get_language_list();
        }

        return view('team_members/my_preferences', $view_data);
    }

    public function save_my_preferences()
    {
        $settings = ['notification_sound_volume', 'hidden_topbar_menus', 'disable_keyboard_shortcuts'];

        if (!get_setting("disable_language_selector_for_team_members")) {
            $settings[] = 'personal_language';
        }

        foreach ($settings as $setting) {
            $value = $this->request->getPost($setting);

            if (is_null($value)) {
                $value = "";
            }

            $this->settingsModel->save_setting("user_{$this->login_user->id}_{$setting}", $value, "user");
        }

        $user_data = [
            "enable_web_notification" => $this->request->getPost("enable_web_notification"),
            "enable_email_notification" => $this->request->getPost("enable_email_notification"),
            "user_timezone" => $this->request->getPost("user_timezone"),
        ];

        $this->usersModel->save($user_data, $this->login_user->id);

        return $this->response->setJSON(['success' => true, 'message' => lang('settings_updated')]);
    }

    public function save_personal_language($language)
    {
        if (!get_setting("disable_language_selector_for_team_members") && ($language || $language === "0")) {
            $language = clean_data($language);
            $this->settingsModel->save_setting("user_{$this->login_user->id}_personal_language", strtolower($language), "user");
        }
    }
    //prepare the dropdown list of roles
    private function _get_roles_dropdown()
    {
        $role_dropdown = [
            "0" => lang('team_member'),
            "admin" => lang('admin') //static role
        ];

        $roles = $this->rolesModel->findAll();
        foreach ($roles as $role) {
            $role_dropdown[$role['id']] = $role['title'];
        }

        return $role_dropdown;
    }
    private function _get_countries_dropdown()
    {
        $country_dropdown = [
            //"0" => lang('team_member'),
            //"admin" => lang('admin') //static role
        ];

        $countries = $this->countriesModel->findAll();
        foreach ($countries as $country) {
            $country_dropdown[$country['numberCode']] = $country['countryName'];
        }

        return $country_dropdown;
    }
    private function _get_companies_dropdown()
    {
        $company_dropdown = [
            "" => "--Select the Employer--",
            //"admin" => lang('admin') //static role
        ];

        $countries = $this->companysModel->findAll();
        foreach ($countries as $country) {
            $company_dropdown[$country['cr_id']] = $country['company_name'];
        }

        return $company_dropdown;
    }
    private function _get_branches_dropdown()
    {
        $branches_dropdown = [
            //"0" => lang('team_member'),
            //"admin" => lang('admin') //static role
        ];

        $branches = $this->branchesModel->findAll();
        foreach ($branches as $branch) {
            $branches_dropdown[$branch['branch_code']] = $branch['title'];
        }

        return $branches_dropdown;
    }

    private function _get_branchess_dropdown($company_name)
    {
        $branches_dropdown = [
            //"0" => lang('team_member'),
            //"admin" => lang('admin') //static role
        ];

        $options = ["company_name" => 'cr001'];
        $branches = $this->branchesModel->where($options)->findAll();
        foreach ($branches as $branch) {
            $branches_dropdown[$branch['branch_code']] = $branch['title'];
        }

        return $branches_dropdown;
    }
    private function _get_designation_dropdown()
    {
        $designation_dropdown = [
            //"0" => lang('team_member'),
            //"admin" => lang('admin') //static role
        ];

        $designations = $this->designationModel->findAll();
        foreach ($designations as $designation) {
            $designation_dropdown[$designation['designation_code']] = $designation['title'];
        }

        return $designation_dropdown;
    }
    private function _get_department_dropdown()
    {
        $department_dropdown = [
            //"0" => lang('team_member'),
            //"admin" => lang('admin') //static role
        ];

        $departments = $this->departmentModel->findAll();
        foreach ($departments as $department) {
            $department_dropdown[$department['department_code']] = $department['title'];
        }

        return $department_dropdown;
    }

public function save_account_settings($user_id)
{
    $this->only_admin_or_own($user_id);

    if ($this->UsersModel->isEmailExists($this->request->getPost('email'), $user_id)) {
        return $this->response->setJSON([
            "success" => false,
            'message' => lang('duplicate_email')
        ]);
    }

    $account_data = [
        "email" => $this->request->getPost('email')
    ];

    if ($this->currentUser->isAdmin && $this->currentUser->id != $user_id) {
        // Admin can update team member's role
        $role = $this->request->getPost('role');
        $role_id = $role;

        if ($role === "admin") {
            $account_data["is_admin"] = 1;
            $account_data["role_id"] = 0;
        } else {
            $account_data["is_admin"] = 0;
            $account_data["role_id"] = $role_id;
        }

        $account_data['work_mode'] = $this->request->getPost('work_mode');
        $account_data['disable_login'] = $this->request->getPost('disable_login');
        $account_data['status'] = $this->request->getPost('status') === "inactive" ? "inactive" : "active";
    }

    $account_data['line_manager'] = $this->request->getPost('line_manager');

    // Update password if provided
    $password = $this->request->getPost('password');
    if ($password) {
        $account_data['password'] = md5($password);
    }

    // Save account settings
    if ($this->UsersModel->save($account_data, $user_id)) {
        return $this->response->setJSON([
            "success" => true,
            'message' => lang('record_updated')
        ]);
    } else {
        return $this->response->setJSON([
            "success" => false,
            'message' => lang('error_occurred')
        ]);
    }
}

    public function save_profile_image($user_id = 0)
    {
        $this->update_only_allowed_members($user_id);

        // Process uploaded file from dropzone
        $profile_image = str_replace("~", ":", $this->request->getPost("profile_image"));

        if ($profile_image) {
            $profile_image = move_temp_file("avatar.png", get_setting("profile_image_path"), "", $profile_image);

            $image_data = ["image" => $profile_image];

            $this->Users_model->save($image_data, $user_id);
            return $this->respond(["success" => true, 'message' => lang('profile_image_changed')]);
        }

        // Process file uploaded using manual file submit
        $profile_image_file = $this->request->getFiles('profile_image_file');
        if ($profile_image_file) {
            $image_file_name = $profile_image_file->getTempName();
            if ($image_file_name) {
                $profile_image = move_temp_file("avatar.png", get_setting("profile_image_path"), "", $image_file_name);
                $image_data = ["image" => $profile_image];
                $this->Users_model->save($image_data, $user_id);
                return $this->respond(["success" => true, 'message' => lang('profile_image_changed')]);
            }
        }

        return $this->failValidationError('No profile image uploaded.');
    }

    public function projectsInfo($userId)
    {
        if ($userId) {
            $data = [
                'userId' => $userId,
                'customFieldHeaders' => $this->CustomFieldsModel->getCustomFieldHeadersForTable("projects", $this->loggedInUser->isAdmin, $this->loggedInUser->userType)
            ];
            return view("team_members/projects_info", $data);
        }
    }


    //show attendance list of a team member
    public function attendanceInfo($userId)
    {
        if ($userId) {
            $data = ['userId' => $userId];
            return view("team_members/attendance_info", $data);
        }
    }


    //show weekly attendance list of a team member
    public function weeklyAttendance()
    {
        return view("team_members/weekly_attendance");
    }

    //show weekly attendance list of a team member
    public function customRangeAttendance()
    {
        return view("team_members/custom_range_attendance");
    }

    //show attendance summary of a team member
    public function attendanceSummary($userId)
    {
        $data = ['userId' => $userId];
        return view("team_members/attendance_summary", $data);
    }


    //show leave list of a team member
    public function leaveInfo($applicantId)
    {
        if ($applicantId) {
            $data = ['applicantId' => $applicantId];
            return view("team_members/leave_info", $data);
        }
    }


    //show yearly leave list of a team member
    public function yearlyLeaves()
    {
        return view("team_members/yearly_leaves");
    }

    //show yearly leave list of a team member
    public function expenseInfo($userId)
    {
        $data = ['userId' => $userId];
        return view("team_members/expenses", $data);
    }

//show monthly payslip list of a team member
public function payslipInfo($userId)
    {
        if ($userId) {
            $data = ['userId' => $userId];
            return view("team_members/payslip_info", $data);
        }
    }
    public function bankStatementInfo($userId)
    {
        $data = [
            'userId' => $userId,
            'bankListDropdown' => ['' => '-'] + $this->BankNameModel->getDropdownList(['title'])
        ];
        return view("team_members/bank_statement", $data);
    }
    //show yearly payslip list of a team member
    public function yearlyPayslip()
    {
        return view("team_members/yearly_payslip");
    }


    /* load files tab */

    public function files($userId)
    {
        $this->updateOnlyAllowedMembers($userId);

        $options = ['user_id' => $userId];
        $data = [
            'files' => $this->General_files_model->getDetails($options)->getResult(),
            'userId' => $userId
        ];
        return view("team_members/files/index", $data);
    }

    /* file upload modal */

    public function fileModalForm()
    {
        $fileId = $this->request->getPost('id');
        $userId = $this->request->getPost('user_id') ?: $this->General_files_model->getOne($fileId)->user_id;

        $this->updateOnlyAllowedMembers($userId);

        $data = ['model_info' => $this->General_files_model->getOne($fileId), 'userId' => $userId];
        return view('team_members/files/modal_form', $data);
    }

    /* save file data and move temp file to parmanent file directory */

   
    public function saveFile()
    {
        $rules = [
            'id' => 'numeric',
            'user_id' => 'required|numeric'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $userId = $this->request->getPost('user_id');
        $this->updateOnlyAllowedMembers($userId);

        $files = $this->request->getPost('files');
        $success = false;
        $now = date('Y-m-d H:i:s');

        $targetPath = WRITEPATH . 'uploads/team_members/' . $userId . '/';

        if ($files && count($files) > 0) {
            foreach ($files as $file) {
                $fileName = $this->request->getPost('file_name_' . $file);
                $newFileName = moveUploadedFile($fileName, $targetPath);
                if ($newFileName) {
                    $data = [
                        'user_id' => $userId,
                        'file_name' => $newFileName,
                        'description' => $this->request->getPost('description_' . $file),
                        'file_size' => $this->request->getPost('file_size_' . $file),
                        'created_at' => $now,
                        'uploaded_by' => $this->loggedInUser->id
                    ];
                    $success = $this->General_files_model->save($data);
                } else {
                    $success = false;
                }
            }
        }

        if ($success) {
            return $this->respondCreated(['success' => true, 'message' => lang('record_saved')]);
        } else {
            return $this->failServerError(lang('error_occurred'));
        }
    }
    /* list of files, prepared for datatable  */

    
    public function filesListData($userId = 0)
    {
        $this->updateOnlyAllowedMembers($userId);

        $options = ['user_id' => $userId];
        $listData = $this->General_files_model->getDetails($options)->getResult();
        $result = [];
        foreach ($listData as $data) {
            $result[] = $this->makeFileRow($data);
        }
        return $this->respond(['data' => $result]);
    }
    
    private function makeFileRow($data)
    {
        $fileIcon = getFileIcon(strtolower(pathinfo($data->file_name, PATHINFO_EXTENSION)));

        $imageURL = getAvatar($data->uploaded_by_user_image);
        $uploadedBy = "<span class='avatar avatar-xs mr10'><img src='$imageURL' alt='...'></span> $data->uploaded_by_user_name";
        $uploadedBy = getTeamMemberProfileLink($data->uploaded_by, $uploadedBy);

        $description = "<div class='pull-left'>" . jsAnchor(removeFilePrefix($data->file_name), [
            'title' => '',
            'data-toggle' => 'app-modal',
            'data-sidebar' => '0',
            'data-url' => route_to('team_members/view_file/' . $data->id)
        ]);

        if ($data->description) {
            $description .= "<br /><span>" . $data->description . "</span></div>";
        } else {
            $description .= "</div>";
        }

        $options = anchor(route_to('team_members/download_file/' . $data->id), "<i class='fa fa fa-cloud-download'></i>", ['title' => lang('download')]);
        $options .= jsAnchor("<i class='fa fa-times fa-fw'></i>", [
            'title' => lang('delete_file'),
            'class' => 'delete',
            'data-id' => $data->id,
            'data-action-url' => route_to('team_members/delete_file'),
            'data-action' => 'delete-confirmation'
        ]);

        return [
            $data->id,
            "<div class='fa fa-$fileIcon font-22 mr10 pull-left'></div>" . $description,
            convertFileSize($data->file_size),
            $uploadedBy,
            formatToDateTime($data->created_at),
            $options
        ];
    }

    public function viewFile($fileId = 0)
    {
        $fileInfo = $this->General_files_model->getDetails(['id' => $fileId])->getRow();

        if ($fileInfo) {
            if (!$fileInfo->user_id) {
                return redirect()->to('forbidden');
            }

            $this->updateOnlyAllowedMembers($fileInfo->user_id);

            $viewData = [
                'canCommentOnFiles' => false,
                'fileUrl' => getFileUri(getGeneralFilePath('team_members', $fileInfo->user_id) . $fileInfo->file_name),
                'isImageFile' => isImageFile($fileInfo->file_name),
                'isGooglePreviewAvailable' => isGooglePreviewAvailable($fileInfo->file_name),
                'fileInfo' => $fileInfo,
                'fileId' => $fileId
            ];
            return view("team_members/files/view", $viewData);
        } else {
            return $this->failNotFound();
        }
    }

    /* download a file */

    public function downloadFile($id)
    {
        $file_info = $this->General_files_model->getOne($id);

        if (!$file_info->user_id) {
            return redirect()->to('forbidden');
        }

        $this->updateOnlyAllowedMembers($file_info->user_id);

        $file_data = serialize([['file_name' => $file_info->file_name]]);
        downloadAppFiles(getGeneralFilePath('team_members', $file_info->user_id), $file_data);
    }


    /* upload a post file */
    public function uploadFile()
    {
        uploadFileToTemp();
    }
    /* check valid file for user */

    public function validateFile()
    {
        return validatePostFile($this->request->getPost('file_name'));
    }
    /* delete a file */

    public function deleteFile()
    {
        $id = $this->request->getPost('id');
        $info = $this->General_files_model->getOne($id);

        if (!$info->user_id) {
            return redirect()->to('forbidden');
        }

        $this->updateOnlyAllowedMembers($info->user_id);

        if ($this->General_files_model->delete($id)) {
            deleteFileFromDirectory(getGeneralFilePath('team_members', $info->user_id) . $info->file_name);
            return $this->respondDeleted(['success' => true, 'message' => lang('record_deleted')]);
        } else {
            return $this->failServerError(lang('record_cannot_be_deleted'));
        }
    }
    public function saveThemeColor()
    {
        $theme_color = $this->request->getPost("theme_color") . '.css';
        $this->Users_model->save(['theme_color' => $theme_color], $this->loginUser->id);
        return $this->respond(['success' => true, 'message' => lang('profile_image_changed')]);
    }


       
       // get annual leave country bases
       public function getCountryAnnualLeaveInfoSuggestion()
       {
           $item = $this->Countries_model->getCountryAnnualLeaveInfoSuggestion($this->request->getPost('item_name'));
           if ($item) {
               return $this->respond(['success' => true, 'item_info' => $item]);
           } else {
               return $this->failNotFound();
           }
       }

       // get country and branch
       public function getCountryBranch()
       {
           $options = ['cr_id' => $this->request->getPost('item_name')];
           $item = $this->Companies_model->getDetails($options)->getRow();
           if ($item) {
               return $this->respond(['success' => true, 'item_info' => $item]);
           } else {
               return $this->failNotFound();
           }
       }

       // get country and branch
       public function getCountry()
       {
           $options = [
               'branch_code' => $this->request->getPost('item_name'),
               'company_name' => $this->request->getPost('company_name')
           ];
           $item = $this->Branches_model->getDetails($options)->getRow();
           if ($item) {
               return $this->respond(['success' => true, 'item_info' => $item]);
           } else {
               return $this->failNotFound();
           }
       }

       public function getBranchCurrency()
       {
           $options = ['numberCode' => $this->request->getPost('item_name')];
           $item = $this->Countries_model->getDetails($options)->getRow();
           if ($item) {
               return $this->respond(['success' => true, 'item_info' => $item]);
           } else {
               return $this->failNotFound();
           }
       }

    // get branches for official holidays
    public function getBranchesSuggestion()
    {
        $key = $this->request->getVar('q');
        $ss = $this->request->getVar('ss');
        $itemss = $this->Branches_model->getCompanyItemSuggestionsBranchName($key, $ss);
        $suggestions = [];
        foreach ($itemss as $items) {
            $suggestions[] = ['id' => $items->branch_code, 'text' => $items->title];
        }
        return $this->respond($suggestions);
    }
    public function keyboardShortcutModalForm()
    {
        return view('team_members/keyboard_shortcut_modal_form');
    }
}

/* End of file team_member.php */
/* Location: ./application/controllers/team_member.php */