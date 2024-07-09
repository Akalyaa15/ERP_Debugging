<?php

namespace App\Controllers;

use App\Models\BranchesModel;
use App\Models\GstStateCodeModel;
use App\Models\CountriesModel;
use App\Models\StatesModel;
use App\Models\CompanysModel;
use App\Models\UsersModel;
use App\Models\GeneralFilesModel;
use App\Models\CountryEarningsModel;
use App\Models\EarningsModel;
use App\Models\CountryDeductionsModel;
use CodeIgniter\API\ResponseTrait;

class Branches extends BaseController
{
    use ResponseTrait;

    protected $branchesModel;
    protected $gstStateCodeModel;
    protected $countriesModel;
    protected $statesModel;
    protected $companysModel;
    protected $usersModel;
    protected $generalFilesModel;
    protected $countryEarningsModel;
    protected $earningsModel;
    protected $countryDeductionsModel;

    public function __construct()
    {
        $this->branchesModel = new BranchesModel();
        $this->gstStateCodeModel = new GstStateCodeModel();
        $this->countriesModel = new CountriesModel();
        $this->statesModel = new StatesModel();
        $this->companysModel = new CompanysModel();
        $this->usersModel = new UsersModel();
        $this->generalFilesModel = new GeneralFilesModel();
        $this->countryEarningsModel = new CountryEarningsModel();
        $this->earningsModel = new EarningsModel();
        $this->countryDeductionsModel = new CountryDeductionsModel();

        // Uncomment the lines below if you want to initialize permissions or access checks
        // $this->access_only_admin();
        // $this->init_permission_checker("master_data");
        // $this->access_only_allowed_members();
        $this->init_permission_checker("branch");
    }

    public function index()
    {
        $this->check_module_availability("module_branch");

        if ($this->login_user->is_admin == "1") {
            return view('branches/index');
        } else if ($this->login_user->user_type == "staff" || $this->login_user->user_type == "resource") {
            if ($this->access_type != "all" && !in_array($this->login_user->id, $this->allowed_members)) {
                return redirect()->to("forbidden");
            }
            return view('branches/index');
        } else {
            return view('branches/index');
        }
    }

    public function modal_form()
    {
        $id = $this->request->getPost('id');
        // Validate input data
        if (!is_numeric($id)) {
            return $this->failValidationErrors(['id' => 'Invalid ID']);
        }

        $viewData['model_info'] = $this->branchesModel->find($id);

        // Fetch GST state codes and countries
        $gstCodes = $this->gstStateCodeModel->findAll();
        $companyGstStateCodeDropdown = [];
        foreach ($gstCodes as $code) {
            $companyGstStateCodeDropdown[] = [
                'id' => $code['gstin_number_first_two_digits'],
                'text' => $code['title']
            ];
        }
        $viewData['company_gst_state_code_dropdown'] = json_encode($companyGstStateCodeDropdown);

        $companySetupCountries = $this->countriesModel->findAll();
        $companySetupCountryDropdown = [];
        foreach ($companySetupCountries as $country) {
            $companySetupCountryDropdown[] = [
                'id' => $country['numberCode'],
                'text' => $country['countryName']
            ];
        }
        $viewData['company_setup_country_dropdown'] = json_encode($companySetupCountryDropdown);

        // Fetch states based on the selected country
        $companyStateDropdown = $this->statesModel->getDropdownList('title', 'id', ['country_code' => $viewData['model_info']['company_setup_country']]);
        $viewData['company_state_dropdown'] = json_encode($companyStateDropdown);

        // Fetch company names
        $companyNames = $this->companysModel->findAll();
        $companyNameDropdown = [];
        foreach ($companyNames as $company) {
            $companyNameDropdown[] = [
                'id' => $company['cr_id'],
                'text' => $company['company_name']
            ];
        }
        $viewData['company_name_dropdown'] = json_encode($companyNameDropdown);

        // Days of the week dropdown
        $viewData['holiday_of_week_dropdown'] = json_encode([
            ['id' => 0, 'text' => 'Sunday'],
            ['id' => 1, 'text' => 'Monday'],
            ['id' => 2, 'text' => 'Tuesday'],
            ['id' => 3, 'text' => 'Wednesday'],
            ['id' => 4, 'text' => 'Thursday'],
            ['id' => 5, 'text' => 'Friday'],
            ['id' => 6, 'text' => 'Saturday']
        ]);

        return view('branches/modal_form', $viewData);
    }

    public function save()
    {
        $id = $this->request->getPost('id');
        // Validate input data
        $validationRules = [
            'title' => 'required'
            // Add more validation rules as needed
        ];
        if (!$this->validate($validationRules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        // Check for existing branch and branch name
        if ($id) {
            $branchData = $this->branchesModel->find($id);
            if ($branchData['branch_code'] != $this->request->getPost('branch_code')) {
                if ($this->branchesModel->is_branch_exists($this->request->getPost('branch_code'))) {
                    return $this->fail(['message' => lang('duplicate_branch')]);
                }
            }
            if (strtoupper($branchData['title']) != strtoupper($this->request->getPost('title'))) {
                if ($this->branchesModel->is_branch_name_exists($this->request->getPost('title'), $this->request->getPost('company_name'))) {
                    return $this->fail(['message' => lang('duplicate_branch_name')]);
                }
            }
        } else {
            if ($this->branchesModel->is_branch_exists($this->request->getPost('branch_code'))) {
                return $this->fail(['message' => lang('duplicate_branch')]);
            }
            if ($this->branchesModel->is_branch_name_exists($this->request->getPost('title'), $this->request->getPost('company_name'))) {
                return $this->fail(['message' => lang('duplicate_branch_name')]);
            }
        }

        // Calculate branch code
        $branchCount = $this->branchesModel->branch_count($this->request->getPost('company_name'));
        $branchCode = $branchCount ? ($branchCount + 1) : '01';
        if ($branchCode <= 9) {
            $branchCode = '0' . $branchCode;
        }

        // Generate branch UID
        $crCode = $this->request->getPost('company_name');
        $buid = $crCode . $branchCode;

        // Prepare data for saving
        $data = [
            "title" => $this->request->getPost('title'),
            "description" => $this->request->getPost('description'),
            "company_name" => $this->request->getPost('company_name'),
            "company_address" => $this->request->getPost('company_address'),
            "company_phone" => $this->request->getPost('company_phone'),
            "company_email" => $this->request->getPost('company_email'),
            "company_website" => $this->request->getPost('company_website'),
            "company_gst_number" => $this->request->getPost('company_gst_number'),
            "company_gstin_number_first_two_digits" => $this->request->getPost('company_gstin_number_first_two_digits'),
            "company_state" => $this->request->getPost('company_state'),
            "company_setup_country" => $this->request->getPost('company_setup_country'),
            "company_city" => $this->request->getPost('company_city'),
            "company_pincode" => $this->request->getPost('company_pincode'),
            "holiday_of_week" => $this->request->getPost('holiday_of_week'),
            "last_activity_user" => $this->login_user->id,
            "last_activity" => get_current_utc_time(),
            "state_mandatory" => $this->request->getPost('state_mandatory'),
        ];
        if (!$id) {
            $data["branch_code"] = $branchCode;
            $data["buid"] = $buid;
        }

        // Save data
        $saveId = $this->branchesModel->save($data, $id);

        if ($saveId) {
            return $this->respondCreated(['id' => $saveId, 'message' => lang('record_saved')]);
        } else {
            return $this->fail(['message' => lang('error_occurred')]);
        }
    }

    public function delete()
    {
        $id = $this->request->getPost('id');
        // Validate input data
        if (!is_numeric($id)) {
            return $this->failValidationErrors(['id' => 'Invalid ID']);
        }

        $data = [
            "last_activity_user" => $this->login_user->id,
            "last_activity" => get_current_utc_time(),
        ];

        if ($this->request->getPost('undo')) {
            if ($this->branchesModel->delete($id, true)) {
                return $this->respondDeleted(['id' => $id, 'message' => lang('record_undone')]);
            } else {
                return $this->fail(['message' => lang('error_occurred')]);
            }
        } else {
            if ($this->branchesModel->delete($id)) {
                return $this->respondDeleted(['message' => lang('record_deleted')]);
            } else {
                return $this->fail(['message' => lang('record_cannot_be_deleted')]);
            }
        }
    }

    public function list_data()
    {
        $listData = $this->branchesModel->get_details();
        $result = [];
        foreach ($listData as $data) {
            $result[] = $this->_make_row($data);
        }
        return $this->respond(['data' => $result]);
    }
    private function _row_data($id)
    {
        $options = ["id" => $id];
        $data = $this->branchesModel->get_details($options)->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $country_dummy_name = "";
        if ($data->company_setup_country) {
            $country_no = is_numeric($data->company_setup_country);
            if (!$country_no) {
                $data->company_setup_country = 0;
            }
            $options = ["numberCode" => $data->company_setup_country];
            $country_id_name = $this->countriesModel->get_details($options)->getRow();
            $country_dummy_name = $country_id_name ? $country_id_name->countryName : "";
        }

        // Branch logo 
        $image_url = $data->image ? get_file_uri(get_general_file_path("branch_profile_image", $data->id) . $data->image) : get_avatar($data->image);
        $user_avatar = "<span class='avatar avatar-xs'><img src='$image_url' alt='...'></span>";

        // Last activity user name and date start 
        $last_activity_by_user_name = "-";
        if ($data->last_activity_user) {
            $last_activity_user_data = $this->usersModel->find($data->last_activity_user);
            $last_activity_image_url = get_avatar($last_activity_user_data->image);
            $last_activity_user = "<span class='avatar avatar-xs mr10'><img src='$last_activity_image_url' alt='...'></span> $last_activity_user_data->first_name $last_activity_user_data->last_name";

            switch ($last_activity_user_data->user_type) {
                case "resource":
                    $last_activity_by_user_name = get_rm_member_profile_link($data->last_activity_user, $last_activity_user);
                    break;
                case "client":
                    $last_activity_by_user_name = get_client_contact_profile_link($data->last_activity_user, $last_activity_user);
                    break;
                case "staff":
                    $last_activity_by_user_name = get_team_member_profile_link($data->last_activity_user, $last_activity_user);
                    break;
                case "vendor":
                    $last_activity_by_user_name = get_vendor_contact_profile_link($data->last_activity_user, $last_activity_user);
                    break;
            }
        }

        $last_activity_date = $data->last_activity ? format_to_relative_time($data->last_activity) : "-";

        // Return formatted data
        return [
            $user_avatar,
            $data->buid,
            $data->company,
            anchor('branches/view/' . $data->id, $data->title),
            $data->branch_code,
            $data->description,
            $country_dummy_name,
            $data->company_email,
            $data->company_phone,
            $last_activity_by_user_name,
            $last_activity_date,
            modal_anchor('branches/modal_form', '<i class="fa fa-pencil"></i>', ['class' => 'edit', 'title' => lang('edit_branch'), 'data-post-id' => $data->id]) .
                js_anchor('<i class="fa fa-times fa-fw"></i>', ['title' => lang('delete_tax'), 'class' => 'delete', 'data-id' => $data->id, 'data-action-url' => 'branches/delete', 'data-action' => 'delete-confirmation'])
        ];
    }
    public function get_state_suggestion()
    {
        $key = $this->request->getVar('q');
        $ss = $this->request->getVar('ss');
        $itemss = $this->branchesModel->get_item_suggestions_country_name($key, $ss);

        $suggestions = [];
        foreach ($itemss as $items) {
            $suggestions[] = ['id' => $items->id, 'text' => $items->title];
        }

        return $this->response->setJSON($suggestions);
    }


    public function view($id = 0, $tab = "")
    {
        $branch_info = $this->branchesModel->find($id);
        if ($branch_info) {
            $view_data = [
                'show_general_info' => $branch_info,
                'tab' => $tab,
                'branch_info' => $branch_info
            ];

            return view('branches/view', $view_data);
        }
    }


    //show general information of a team member
    public function branch_info($branch_id)
    {
        $view_data['branch_info'] = $this->branchesModel->find($branch_id);

        $company_gst_state_code_dropdown = [];
        foreach ($this->gstStateCodeModel->findAll() as $code) {
            $company_gst_state_code_dropdown[] = ['id' => $code->gstin_number_first_two_digits, 'text' => $code->title];
        }
        $view_data['company_gst_state_code_dropdown'] = json_encode($company_gst_state_code_dropdown);

        $company_setup_country_dropdown = [];
        foreach ($this->countriesModel->findAll() as $country) {
            $company_setup_country_dropdown[] = ['id' => $country->numberCode, 'text' => $country->countryName];
        }
        $view_data['company_setup_country_dropdown'] = json_encode($company_setup_country_dropdown);

        $company_state_dropdown = [['id' => '', 'text' => '-']];
        foreach ($this->statesModel->get_dropdown_list('title', 'id', ['country_code' => $view_data['branch_info']->company_setup_country]) as $key => $value) {
            $company_state_dropdown[] = ['id' => $key, 'text' => $value];
        }
        $view_data['company_state_dropdown'] = json_encode($company_state_dropdown);

        $company_name_dropdown = [];
        foreach ($this->companysModel->findAll() as $company) {
            $company_name_dropdown[] = ['id' => $company->cr_id, 'text' => $company->company_name];
        }
        $view_data['company_name_dropdown'] = json_encode($company_name_dropdown);

        $view_data['holiday_of_week_dropdown'] = json_encode([
            ['id' => 0, 'text' => 'Sunday'],
            ['id' => 1, 'text' => 'Monday'],
            ['id' => 2, 'text' => 'Tuesday'],
            ['id' => 3, 'text' => 'Wednesday'],
            ['id' => 4, 'text' => 'Thursday'],
            ['id' => 5, 'text' => 'Friday'],
            ['id' => 6, 'text' => 'Saturday']
        ]);

        return view('branches/branch_info', $view_data);
    }
    public function save_branch_info($branch_id)
    {
        $id = $branch_id;
        if ($id) {
            $existing_branch = $this->branchesModel->find($branch_id);
            if ($existing_branch->branch_code != $this->request->getPost('branch_code')) {
                if ($this->branchesModel->is_branch_exists($this->request->getPost('branch_code'))) {
                    return $this->response->setJSON(['success' => false, 'message' => lang('duplicate_branch')]);
                }
            }
            if (strtoupper($existing_branch->title) != strtoupper($this->request->getPost('title'))) {
                if ($this->branchesModel->is_branch_name_exists($this->request->getPost('title'))) {
                    return $this->response->setJSON(['success' => false, 'message' => lang('duplicate_branch_name')]);
                }
            }
        }

        $data = [
            "title" => $this->request->getPost('title'),
            "branch_code" => $this->request->getPost('branch_code'),
            "description" => $this->request->getPost('description'),
            "company_name" => $this->request->getPost('company_name'),
            "company_address" => $this->request->getPost('company_address'),
            "company_phone" => $this->request->getPost('company_phone'),
            "company_email" => $this->request->getPost('company_email'),
            "company_website" => $this->request->getPost('company_website'),
            "company_gst_number" => $this->request->getPost('company_gst_number'),
            "company_gstin_number_first_two_digits" => $this->request->getPost('company_gstin_number_first_two_digits'),
            "company_state" => $this->request->getPost('company_state'),
            "company_setup_country" => $this->request->getPost('company_setup_country'),
            "company_city" => $this->request->getPost('company_city'),
            "company_pincode" => $this->request->getPost('company_pincode'),
            "holiday_of_week" => $this->request->getPost('holiday_of_week'),
            "last_activity_user" => $this->login_user->id,
            "last_activity" => get_current_utc_time(),
            "state_mandatory" => $this->request->getPost('state_mandatory'),
        ];

        $save_id = $this->branchesModel->save($data, $branch_id);
        if ($save_id) {
            return $this->response->setJSON(['success' => true, 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }


    public function payslip_info($branch_id)
    {
        $branch_info = $this->branchesModel->find($branch_id);
        if (!$branch_info) {
            // Handle case where branch info is not found
            return redirect()->back()->with('error', 'Branch not found.');
        }

        $view_data = [
            'branch_id' => $branch_id,
            'members_and_teams_dropdown' => json_encode([]), // Replace with your method or data
            'annual_leave_dropdown' => $this->_generateAnnualLeaveDropdown(),
            'branch_info' => $branch_info
        ];

        return view('branches/payslip_info', $view_data);
    }
    public function save_payslip_info()
    {
        // Validate input
        $rules = [
            'branch_id' => 'required|numeric',
            // Add more validation rules as needed
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $branch_id = $this->request->getPost('branch_id');

        // Example file upload handling
        $client_logo = $this->request->getPost('site_logo');
        $target_path = WRITEPATH . 'uploads/branch/' . $branch_id . '/';
        $value = $this->_moveTempFile('branch-logo.png', $target_path, '', $client_logo);

        // Prepare payslip data
        $payslip_data = [
            'payslip_color' => $this->request->getPost('payslip_color'),
            'payslip_footer' => decode_ajax_post_data($this->request->getPost('payslip_footer')),
            'payslip_prefix' => $this->request->getPost('payslip_prefix'),
            // Add more fields as needed
        ];

        // Handle payslip logo update
        $client_info_logo = $this->branchesModel->find($branch_id);
        $client_logo_file = $client_info_logo->payslip_logo;

        if ($client_logo && !$client_logo_file) {
            $payslip_data['payslip_logo'] = $value;
        } elseif ($client_logo && $client_logo_file) {
            delete_file_from_directory(WRITEPATH . 'uploads/branch/' . $branch_id . '/' . $client_logo_file);
            $payslip_data['payslip_logo'] = $value;
        }

        // Save payslip data
        if ($this->branchesModel->save($payslip_data, $branch_id)) {
            return $this->respondUpdated(['success' => true, 'message' => lang('record_updated')]);
        } else {
            return $this->fail(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function payslip_earnings_info($branch_id)
    {
        // Load view with necessary data
        $view_data = [
            'branch_id' => $branch_id,
        ];

        return view('branches/payslip_earnings/index', $view_data);
    }

        /* file upload modal */

        public function earnings_modal_form()
        {
            $branch_id = $this->request->getPost('branch_id');
            $view_data = [
                'model_info' => $this->Country_earnings_model->find($this->request->getPost('id')),
                'branch_id' => $branch_id,
            ];
    
            return view('branches/payslip_earnings/modal_form', $view_data);
        }

        public function save_earnings()
        {
            $validation = \Config\Services::validation();
    
            // Validate input data
            $validation->setRules([
                'id' => 'required|numeric',
                'title' => 'required',
                'percentage' => 'required|numeric',
            ]);
    
            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON(['success' => false, 'message' => $validation->getErrors()]);
            }
    
            $id = $this->request->getPost('id');
            $branch_id = $this->request->getPost('branch_id');
            $percentage = $this->request->getPost('percentage');
            $status = $this->request->getPost('status');
    
            $data = [
                'title' => $this->request->getPost('title'),
                'percentage' => unformat_currency($this->request->getPost('percentage')),
                'status' => $this->request->getPost('status'),
                'description' => $this->request->getPost('description'),
                'country_id' => $branch_id,
            ];
    
            $countryEarningsModel = new CountryEarningsModel();
    
            if ($status == 'active') {
                // Calculate earnings percentage and validate
                if (!$id) {
                    // Handle new record validation
                    $basicPercentage = $countryEarningsModel
                        ->where(['deleted' => 0, 'status' => 'active', 'key_name' => 'basic_salary', 'country_id' => $branch_id])
                        ->first();
                    $otherPercentage = $countryEarningsModel
                        ->where(['deleted' => 0, 'status' => 'active', 'key_name' => '', 'country_id' => $branch_id])
                        ->findAll();
    
                    $salaryDefault = 10000;
                    $salary = $salaryDefault / 100;
                    $basicSalaryValue = $salary * $basicPercentage->percentage;
                    $c = $basicSalaryValue / 100;
                    $total = 0;
    
                    foreach ($otherPercentage as $otherPer) {
                        $a = $c * $otherPer->percentage;
                        $total += $a;
                    }
    
                    $currentPercentage = $c * $percentage;
                    $g = $basicSalaryValue + $total + $currentPercentage;
    
                    if ($g > $salaryDefault) {
                        return $this->response->setJSON(['success' => false, 'message' => lang('earnings_percentage')]);
                    }
                } elseif ($id) {
                    // Handle existing record validation
                    $countryPayslipKeyName = $countryEarningsModel->find($id);
    
                    if ($countryPayslipKeyName->key_name != 'basic_salary') {
                        $basicPercentage = $countryEarningsModel
                            ->where(['deleted' => 0, 'status' => 'active', 'key_name' => 'basic_salary', 'country_id' => $branch_id])
                            ->first();
                        $options = ['id' => $id, 'country_id' => $branch_id];
                        $otherPercentage = $countryEarningsModel->get_detailss($options)->getResult();
                        $basicPercentageValue = $basicPercentage->percentage;
                        $salaryDefault = 10000;
                        $salary = $salaryDefault / 100;
                        $basicSalaryValue = $salary * $basicPercentageValue;
                        $c = $basicSalaryValue / 100;
                        $total = 0;
    
                        foreach ($otherPercentage as $otherPer) {
                            $a = $c * $otherPer->percentage;
                            $total += $a;
                        }
    
                        $currentPercentage = $c * $percentage;
                        $g = $basicSalaryValue + $total + $currentPercentage;
    
                        if ($g > $salaryDefault) {
                            return $this->response->setJSON(['success' => false, 'message' => lang('earnings_percentage')]);
                        }
                    } elseif ($countryPayslipKeyName->key_name == 'basic_salary') {
                        $basicPercentage = $countryEarningsModel
                            ->where(['deleted' => 0, 'status' => 'active', 'key_name' => 'basic_salary', 'country_id' => $branch_id])
                            ->first();
                        $options = ['id' => $id, 'country_id' => $branch_id];
                        $otherPercentage = $countryEarningsModel->get_detailss($options)->getResult();
                        $salaryDefault = 10000;
                        $salary = $salaryDefault / 100;
                        $basicSalaryValue = $salary * $percentage;
                        $c = $basicSalaryValue / 100;
                        $total = 0;
    
                        foreach ($otherPercentage as $otherPer) {
                            $a = $c * $otherPer->percentage;
                            $total += $a;
                        }
    
                        $g = $basicSalaryValue + $total;
    
                        if ($g > $salaryDefault) {
                            return $this->response->setJSON(['success' => false, 'message' => lang('earnings_percentage')]);
                        }
                    }
                }
            }
    
            // Save data
            $saveId = $countryEarningsModel->save($data, $id);
    
            if ($saveId) {
                return $this->response->setJSON(['success' => true, 'data' => $this->_make_earnings_row($saveId), 'id' => $saveId, 'message' => lang('record_saved')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
            }
        }
    

    /* list of files, prepared for datatable  */

    public function earnings_list_data($branch_id = 0)
    {
        $options = ["country_id" => $branch_id];
        $list_data = $this->CountryEarningsModel->getDetails($options)->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_makeEarningsRow($data);
        }
        return $this->response->setJSON(["data" => $result]);
    }

  
    private function _makeEarningsRow($data)
    {
        $edit = modal_anchor(route_to('earnings_modal_form'), "<i class='fa fa-pencil'></i>", ["class" => "edit", "title" => lang('edit'), "data-post-id" => $data->id]);
        
        $delete = "";
        if (!$data->key_name) {
            $delete = js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete'), "class" => "delete", "data-id" => $data->id, "data-action-url" => route_to('delete_earnings'), "data-action" => "delete-confirmation"]);
        }
        
        return [
            $data->title,
            $data->description ?: "-",
            to_decimal_format($data->percentage) . "%",
            lang($data->status),
            $edit . $delete,
        ];
    }
    /* delete a file */
     function delete_earnings() {
        validate_submitted_data(array(
            "id" => "numeric|required"
        ));


        $id = $this->input->post('id');
        if ($this->input->post('undo')) {
            if ($this->Country_earnings_model->delete($id, true)) {
                echo json_encode(array("success" => true, "data" => $this->_row_data($id), "message" => lang('record_undone')));
            } else {
                echo json_encode(array("success" => false, lang('error_occurred')));
            }
        } else {
            if ($this->Country_earnings_model->delete($id)) {
                echo json_encode(array("success" => true, 'message' => lang('record_deleted')));
            } else {
                echo json_encode(array("success" => false, 'message' => lang('record_cannot_be_deleted')));
            }
        }
    }
    function payslip_deductions_info($branch_id) {


        /*$options = array("country_id" => $country_id);
        $view_data['files'] = $this->General_files_model->get_details($options)->result();*/
        $view_data['branch_id'] = $branch_id;
        $this->load->view("branches/payslip_deductions/index", $view_data);
    }

        /* file upload modal */

    function deductions_modal_form() {
        $view_data['model_info'] = $this->Country_deductions_model->get_one($this->input->post('id'));
        //$user_id = $this->input->post('user_id') ? $this->input->post('user_id') : $view_data['model_info']->user_id;
    $branch_id = $this->input->post('branch_id') ? $this->input->post('branch_id') : $view_data['model_info']->country_id;

       // $this->update_only_allowed_members($user_id);

        $view_data['branch_id'] = $branch_id;
        $this->load->view('branches/payslip_deductions/modal_form', $view_data);
    }

    function save_deductions() {

        validate_submitted_data(array(
            "id" => "numeric",
            "title" => "required",
            "percentage" => "required"
        ));

        $id = $this->input->post('id');
        $branch_id = $this->input->post('branch_id');
        $percentage = $this->input->post('percentage');
        $status = $this->input->post('status');
        $data = array(
           "title" => $this->input->post('title'),
            "percentage" => unformat_currency($this->input->post('percentage')),
            "status" => $this->input->post('status'),
            "description" => $this->input->post('description'),
            "country_id" =>  $branch_id 
        );
        
        $save_id = $this->Country_deductions_model->save($data, $id);
        if ($save_id) {
            echo json_encode(array("success" => true, "data" => $this->_make_deductions_row($save_id), 'id' => $save_id, 'message' => lang('record_saved')));
        } else {
            echo json_encode(array("success" => false, 'message' => lang('error_occurred')));
        }
    }



        /* list of files, prepared for datatable  */

    function deductions_list_data($branch_id = 0) {
        $options = array("country_id" => $branch_id);

        //$this->update_only_allowed_members($user_id);

        $list_data = $this->Country_deductions_model->get_details($options)->result();
        $result = array();
        foreach ($list_data as $data) {
            $result[] = $this->_make_deductions_row($data);
        }
        echo json_encode(array("data" => $result));
    }


  
        private function _make_deductions_row($data) {
        $delete = "";
        $edit = "";
        if ($data->key_name) {
            $edit = modal_anchor(get_uri("branches/deductions_modal_form"), "<i class='fa fa-pencil'></i>", array("class" => "edit", "title" => lang('edit'), "data-post-id" => $data->id));
            
        }
        if (!$data->key_name) {
            $edit = modal_anchor(get_uri("branches/deductions_modal_form"), "<i class='fa fa-pencil'></i>", array("class" => "edit", "title" => lang('edit'), "data-post-id" => $data->id));
            $delete = js_anchor("<i class='fa fa-times fa-fw'></i>", array('title' => lang('delete'), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("branches/delete_deductions"), "data-action" => "delete-confirmation"));
        }
        return array($data->title,
            $data->description ? $data->description : "-",
            to_decimal_format($data->percentage)."%",
            lang($data->status),
            /*modal_anchor(get_uri("earnings/modal_form"), "<i class='fa fa-pencil'></i>", array("class" => "edit", "title" => lang('edit_tax'), "data-post-id" => $data->id))
            . js_anchor("<i class='fa fa-times fa-fw'></i>", array('title' => lang('delete_tax'), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("earnings/delete"), "data-action" => "delete-confirmation")) */
            $edit.$delete,
        );
    }

    

    /* delete a file */

   

     function delete_deductions() {
        validate_submitted_data(array(
            "id" => "numeric|required"
        ));


        $id = $this->input->post('id');
        if ($this->input->post('undo')) {
            if ($this->Country_deductions_model->delete($id, true)) {
                echo json_encode(array("success" => true, "data" => $this->_row_data($id), "message" => lang('record_undone')));
            } else {
                echo json_encode(array("success" => false, lang('error_occurred')));
            }
        } else {
            if ($this->Country_deductions_model->delete($id)) {
                echo json_encode(array("success" => true, 'message' => lang('record_deleted')));
            } else {
                echo json_encode(array("success" => false, 'message' => lang('record_cannot_be_deleted')));
            }
        }
    }
}

/* End of file taxes.php */
/* Location: ./application/controllers/taxes.php */