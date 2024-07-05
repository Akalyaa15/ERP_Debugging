<?php

namespace App\Controllers;

use App\Models\CountriesModel;
use App\Models\VatTypesModel;
use App\Models\UsersModel;
use CodeIgniter\API\ResponseTrait;
use DateTimeZone;

class CountriesController extends BaseController
{
    use ResponseTrait;

    protected $countriesModel;
    protected $vatTypesModel;
    protected $usersModel;

    public function __construct()
    {
        $this->countriesModel = new CountriesModel();
        $this->vatTypesModel = new VatTypesModel();
        $this->usersModel = new UsersModel();
        $this->init_permission_checker("country"); // Assuming this method exists for permission checking
    }

    public function index()
    {
        $this->check_module_availability("module_country");

        if ($this->login_user->is_admin == "1") {
            return view('countries/index');
        } elseif ($this->login_user->user_type == "staff" || $this->login_user->user_type == "resource") {
            if ($this->access_type != "all" && !in_array($this->login_user->id, $this->allowed_members)) {
                return redirect()->to('forbidden');
            }
        }

        return view('countries/index');
    }

    public function modal_form()
    {
        validate([
            'id' => 'numeric'
        ]);

        $viewData['model_info'] = $this->countriesModel->getOne($this->request->getPost('id'));

        $tzList = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
        $viewData['timezone_dropdown'] = array_combine($tzList, $tzList);

        $viewData['vat_dropdown'] = ['' => '-'] + $this->vatTypesModel->getDropdownList(['title'], 'id', ['status' => 'active']);
        $viewData['language_dropdown'] = get_language_list(); // Assuming this function exists
        $viewData['holiday_of_week_dropdown'] = json_encode([
            ['id' => 0, 'text' => 'Sunday'],
            ['id' => 1, 'text' => 'Monday'],
            ['id' => 2, 'text' => 'Tuesday'],
            ['id' => 3, 'text' => 'Wednesday'],
            ['id' => 4, 'text' => 'Thursday'],
            ['id' => 5, 'text' => 'Friday'],
            ['id' => 6, 'text' => 'Saturday']
        ]);

        return view('countries/modal_form', $viewData);
    }

    public function save()
    {
        validate([
            'id' => 'numeric'
        ]);

        $id = $this->request->getPost('id');

        // Check for duplicates based on conditions
        if ($id) {
            $countryInfo = $this->countriesModel->getOne($this->request->getPost('id'));

            if (strtoupper($countryInfo->iso) != strtoupper($this->request->getPost('iso_code'))) {
                if ($this->countriesModel->isCountryIsoExists($this->request->getPost('iso_code'))) {
                    return $this->fail(json_encode(['success' => false, 'message' => lang('duplicate_country_iso')]));
                }
            }

            if ($countryInfo->numberCode != $this->request->getPost('number_code')) {
                if ($this->countriesModel->isCountryExists($this->request->getPost('number_code'))) {
                    return $this->fail(json_encode(['success' => false, 'message' => lang('duplicate_country_code')]));
                }
            }

            if ($countryInfo->countryName != strtoupper($this->request->getPost('country_name'))) {
                if ($this->countriesModel->isCountryNameExists(strtoupper($this->request->getPost('country_name')))) {
                    return $this->fail(json_encode(['success' => false, 'message' => lang('duplicate_country_name')]));
                }
            }
        }

        if (!$id) {
            if ($this->countriesModel->isCountryIsoExists($this->request->getPost('iso_code'))) {
                return $this->fail(json_encode(['success' => false, 'message' => lang('duplicate_country_iso')]));
            }

            if ($this->countriesModel->isCountryExists($this->request->getPost('number_code'))) {
                return $this->fail(json_encode(['success' => false, 'message' => lang('duplicate_country_code')]));
            }

            if ($this->countriesModel->isCountryNameExists(strtoupper($this->request->getPost('country_name')))) {
                return $this->fail(json_encode(['success' => false, 'message' => lang('duplicate_country_name')]));
            }
        }

        // Prepare data to save
        $data = [
            'iso' => $this->request->getPost('iso_code'),
            'countryName' => strtoupper($this->request->getPost('country_name')),
            'numberCode' => $this->request->getPost('number_code'),
            'currency_symbol' => $this->request->getPost('currency_symbol'),
            'currency' => $this->request->getPost('currency'),
            'currency_name' => $this->request->getPost('currency_name'),
            'timezone' => $this->request->getPost('timezone'),
            'date_format' => $this->request->getPost('date_format'),
            'time_format' => $this->request->getPost('time_format'),
            'first_day_of_week' => $this->request->getPost('first_day_of_week'),
            'language' => $this->request->getPost('language'),
            'vat_type' => $this->request->getPost('vat_type'),
            'last_activity_user' => $this->login_user->id,
            'last_activity' => get_current_utc_time()
        ];

        $saveId = $this->countriesModel->save($data, $id);

        if ($saveId) {
            return $this->respond([
                'success' => true,
                'data' => $this->_rowData($saveId),
                'id' => $saveId,
                'message' => lang('record_saved')
            ]);
        } else {
            return $this->fail([
                'success' => false,
                'message' => lang('error_occurred')
            ]);
        }
    }

    public function delete()
    {
        validate([
            'id' => 'numeric|required'
        ]);

        $id = $this->request->getPost('id');

        // Additional logic for undo option and deleting record
        $data = [
            'last_activity_user' => $this->login_user->id,
            'last_activity' => get_current_utc_time()
        ];

        $saveId = $this->countriesModel->save($data, $id);

        if ($this->request->getPost('undo')) {
            if ($this->countriesModel->delete($id, true)) {
                return $this->respond([
                    'success' => true,
                    'data' => $this->_rowData($id),
                    'message' => lang('record_undone')
                ]);
            } else {
                return $this->fail(['success' => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($this->countriesModel->delete($id)) {
                return $this->respond(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->fail(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }

    public function list_data()
    {
        $listData = $this->countriesModel->getDetails()->getResult();
        $result = [];

        foreach ($listData as $data) {
            $result[] = $this->_makeRow($data);
        }

        return $this->respond(['data' => $result]);
    }

    private function _rowData($id)
    {
        $options = ['id' => $id];
        $data = $this->countriesModel->getDetails($options)->getRow();
        return $this->_makeRow($data);
    }

    private function _makeRow($data)
    {
        // Modify as per your view and data requirements
        $userAvatar = ''; // Logic to fetch user avatar
        $lastActivityByUserName = ''; // Logic to fetch last activity user name
        $lastActivityDate = ''; // Logic to format last activity date

        return [
            $userAvatar,
            anchor('countries/view/' . $data->id, $data->countryName),
            $data->numberCode,
            $data->iso,
            $data->currency_name,
            $data->currency,
            $data->currency_symbol,
            $lastActivityByUserName,
            $lastActivityDate,
            modal_anchor('countries/modal_form', '<i class="fa fa-pencil"></i>', ['class' => 'edit', 'title' => lang('edit_country'), 'data-post-id' => $data->id]) .
                js_anchor('<i class="fa fa-times fa-fw"></i>', ['title' => lang('delete_tax'), 'class' => 'delete', 'data-id' => $data->id, 'data-action-url' => 'countries/delete', 'data-action' => 'delete-confirmation'])
        ];
    }

    public function view($id = 0, $tab = '')
    {
        $options = ['id' => $id];
        $countryInfo = $this->countriesModel->getDetails($options)->getRow();

        if ($countryInfo) {
            $viewData['show_general_info'] = $countryInfo;
            $viewData['tab'] = $tab;
            $viewData['country_info'] = $countryInfo;

            return view('countries/view', $viewData);
        }
    }

public function country_info($country_id)
{
    //$this->update_only_allowed_members($user_id);

    $view_data['country_info'] = $this->Countries_model->getOne($country_id);
    $tzlist = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
    $view_data['timezone_dropdown'] = array_combine($tzlist, $tzlist);

    $view_data['language_dropdown'] = get_language_list();
    $view_data['holiday_of_week_dropdown'] = json_encode([
        ["id" => 0, "text" => "Sunday"],
        ["id" => 1, "text" => "Monday"],
        ["id" => 2, "text" => "Tuesday"],
        ["id" => 3, "text" => "Wednesday"],
        ["id" => 4, "text" => "Thursday"],
        ["id" => 5, "text" => "Friday"],
        ["id" => 6, "text" => "Saturday"]
    ]);
    //$view_data["custom_fields"] = $this->Custom_fields_model->get_combined_details("team_members", $user_id, $this->login_user->is_admin, $this->login_user->user_type)->result();

    $view_data['vat_dropdown'] = ['' => '-'] + $this->Vat_types_model->getDropdownList(["title"], "id", ["status" => "active"]);

    return view("countries/country_info", $view_data);
}


    //save counntry info
    public function save_country_info($country_id)
    {
        //$this->update_only_allowed_members($user_id);
    
        $id = $country_id;
    
        if ($id) {
            $ree = $this->Countries_model->get_one($id);
    
            if (strtoupper($ree->iso) != strtoupper($this->request->getPost('iso_code'))) {
                if ($this->Countries_model->is_country_iso_exists($this->request->getPost('iso_code'))) {
                    return $this->response->setJSON([
                        "success" => false,
                        'message' => lang('duplicate_country_iso')
                    ]);
                }
            }
    
            if ($ree->numberCode != $this->request->getPost('number_code')) {
                if ($this->Countries_model->is_country_exists($this->request->getPost('number_code'))) {
                    return $this->response->setJSON([
                        "success" => false,
                        'message' => lang('duplicate_country_code')
                    ]);
                }
            }
    
            if ($ree->countryName != strtoupper($this->request->getPost('country_name'))) {
                if ($this->Countries_model->is_country_name_exists(strtoupper($this->request->getPost('country_name')))) {
                    return $this->response->setJSON([
                        "success" => false,
                        'message' => lang('duplicate_country_name')
                    ]);
                }
            }
        }
    
        $county_data = [
            "iso" => $this->request->getPost('iso_code'),
            "countryName" => $this->request->getPost('country_name'),
            "numberCode" => $this->request->getPost('number_code'),
            "currency_symbol" => $this->request->getPost('currency_symbol'),
            "currency" => $this->request->getPost('currency'),
            "currency_name" => $this->request->getPost('currency_name'),
            "timezone" => $this->request->getPost('timezone'),
            "date_format" => $this->request->getPost('date_format'),
            "time_format" => $this->request->getPost('time_format'),
            "first_day_of_week" => $this->request->getPost('first_day_of_week'),
            "language" => $this->request->getPost('language'),
            "last_activity_user" => $this->login_user->id,
            "last_activity" => get_current_utc_time(),
            "vat_type" => $this->request->getPost('vat_type'),
        ];
    
        $county_data = clean_data($county_data);
    
        $country_info_updated = $this->Countries_model->save($county_data, $country_id);
    
        if ($country_info_updated) {
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
    public function save_payslip_info()
{
    //$this->access_only_admin();

    helper(['form', 'file']);

    $validationRules = [
        'country_id' => 'required|numeric'
    ];

    if (!$this->validate($validationRules)) {
        return $this->response->setJSON([
            'success' => false,
            'message' => $this->validator->getErrors()
        ]);
    }

    $country_id = $this->request->getPost('country_id');

    $client_logo = $this->request->getPost('site_logo');
    $target_path = WRITEPATH . 'uploads/' . "country/" . $country_id . "/";
    $value = move_uploaded_file("country-logo.png", $target_path, "", $client_logo);

    $payslip_data = [
        "payslip_color" => $this->request->getPost('payslip_color'),
        "payslip_footer" => decode_ajax_post_data($this->request->getPost('payslip_footer')),
        "payslip_prefix" => $this->request->getPost('payslip_prefix'),
        "maximum_no_of_casual_leave_per_month" => $this->request->getPost('maximum_no_of_casual_leave_per_month'),
        "payslip_ot_status" => $this->request->getPost('payslip_ot_status'),
        "payslip_generate_date" => $this->request->getPost('payslip_generate_date'),
        "company_working_hours_for_one_day" => $this->request->getPost('company_working_hours_for_one_day'),
        "ot_permission" => $this->request->getPost('ot_permission'),
        "ot_permission_specific" => $this->request->getPost('ot_permission_specific'),
        "payslip_created_status" => $this->request->getPost('payslip_created_status'),
    ];

    $client_info_logo = $this->Countries_model->get_one($country_id);
    $client_logo_file = $client_info_logo->payslip_logo;
    
    if ($client_logo && !$client_logo_file) {
        $payslip_data["payslip_logo"] = $value;
    } else if ($client_logo && $client_logo_file) {
        delete_file_from_directory(get_general_file_path("country", $country_id) . $client_logo_file);
        $payslip_data["payslip_logo"] = $value;
    }

    $payslip_save = $this->Countries_model->save($payslip_data, $country_id);

    if ($payslip_save) {
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
public function payslip_earnings_info($country_id)
{
    //$this->update_only_allowed_members($user_id);

    //$options = ["country_id" => $country_id];
    //$view_data['files'] = $this->General_files_model->get_details($options)->getResult();
    
    $view_data['country_id'] = $country_id;
    return view("countries/payslip_earnings/index", $view_data);
}
public function earnings_modal_form()
{
    $view_data['model_info'] = $this->Country_earnings_model->find($this->request->getPost('id'));
    $country_id = $this->request->getPost('country_id') ? $this->request->getPost('country_id') : $view_data['model_info']->country_id;

    //$this->update_only_allowed_members($user_id);

    $view_data['country_id'] = $country_id;
    return view('countries/payslip_earnings/modal_form', $view_data);
}

public function save_earnings()
{
    helper('form');

    $validationRules = [
        'id' => 'numeric',
        'title' => 'required',
        'percentage' => 'required'
    ];

    if (!$this->validate($validationRules)) {
        return $this->response->setJSON([
            'success' => false,
            'message' => $this->validator->getErrors()
        ]);
    }

    $id = $this->request->getPost('id');
    $country_id = $this->request->getPost('country_id');
    $percentage = $this->request->getPost('percentage');
    $status = $this->request->getPost('status');

    $data = [
        'title' => $this->request->getPost('title'),
        'percentage' => unformat_currency($this->request->getPost('percentage')),
        'status' => $this->request->getPost('status'),
        'description' => $this->request->getPost('description'),
        'country_id' => $country_id
    ];

    if ($status == 'active' && !$id) {
        $basic_percentage = $this->Country_earnings_model->where(['deleted' => 0, 'status' => 'active', 'key_name' => 'basic_salary', 'country_id' => $country_id])->first();
        $other_percentages = $this->Country_earnings_model->where(['deleted' => 0, 'status' => 'active', 'country_id' => $country_id])->findAll();

        $salary_default = 10000;
        $salary = $salary_default / 100;
        $basic_salary_value = $salary * $basic_percentage->percentage;
        $total = 0;

        foreach ($other_percentages as $other_per) {
            $a = $basic_salary_value * $other_per->percentage / 100;
            $total += $a;
        }

        $current_percentage = $basic_salary_value * $percentage / 100;
        $g = $basic_salary_value + $total + $current_percentage;

        if ($g > $salary_default) {
            return $this->response->setJSON([
                'success' => false,
                'message' => lang('earnings_percentage')
            ]);
        }
    }

    if ($id) {
        $country_payslip_key_name = $this->Country_earnings_model->find($id);

        if ($country_payslip_key_name->key_name != 'basic_salary') {
            $basic_percentage = $this->Country_earnings_model->where(['deleted' => 0, 'status' => 'active', 'key_name' => 'basic_salary', 'country_id' => $country_id])->first();
            $other_percentages = $this->Country_earnings_model->where(['deleted' => 0, 'status' => 'active', 'country_id' => $country_id])->findAll();

            $salary_default = 10000;
            $salary = $salary_default / 100;
            $basic_salary_value = $salary * $basic_percentage->percentage;
            $total = 0;

            foreach ($other_percentages as $other_per) {
                $a = $basic_salary_value * $other_per->percentage / 100;
                $total += $a;
            }

            $current_percentage = $basic_salary_value * $percentage / 100;
            $g = $basic_salary_value + $total + $current_percentage;

            if ($g > $salary_default) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => lang('earnings_percentage')
                ]);
            }
        } else {
            $basic_percentage = $this->Country_earnings_model->where(['deleted' => 0, 'status' => 'active', 'key_name' => 'basic_salary', 'country_id' => $country_id])->first();

            $salary_default = 10000;
            $salary = $salary_default / 100;
            $basic_salary_value = $salary * $percentage;

            $total = 0;

            foreach ($other_percentages as $other_per) {
                $a = $basic_salary_value * $other_per->percentage / 100;
                $total += $a;
            }

            $g = $basic_salary_value + $total;

            if ($g > $salary_default) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => lang('earnings_percentage')
                ]);
            }
        }
    }

    $save_id = $this->Country_earnings_model->save($data, $id);

    if ($save_id) {
        return $this->response->setJSON([
            'success' => true,
            'data' => $this->_make_earnings_row($save_id),
            'id' => $save_id,
            'message' => lang('record_saved')
        ]);
    } else {
        return $this->response->setJSON([
            'success' => false,
            'message' => lang('error_occurred')
        ]);
    }
}

public function earnings_list_data($country_id = 0)
{
    //$this->update_only_allowed_members($user_id);

    $options = ["country_id" => $country_id];
    $list_data = $this->Country_earnings_model->get_details($options)->getResult();
    $result = [];
    foreach ($list_data as $data) {
        $result[] = $this->_make_earnings_row($data);
    }
    return $this->response->setJSON(["data" => $result]);
}
private function _make_earnings_row($data)
{
    $delete = "";
    $edit = "";
    if ($data->key_name) {
        $edit = modal_anchor(route_to('earnings_modal_form'), "<i class='fa fa-pencil'></i>", ['class' => 'edit', 'title' => lang('edit'), 'data-post-id' => $data->id]);
    }
    if (!$data->key_name) {
        $edit = modal_anchor(route_to('earnings_modal_form'), "<i class='fa fa-pencil'></i>", ['class' => 'edit', 'title' => lang('edit'), 'data-post-id' => $data->id]);
        $delete = js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete'), 'class' => 'delete', 'data-id' => $data->id, 'data-action-url' => route_to('delete_earnings'), 'data-action' => 'delete-confirmation']);
    }
    return [
        $data->title,
        $data->description ? $data->description : "-",
        to_decimal_format($data->percentage) . "%",
        lang($data->status),
        $edit . $delete,
    ];
} 


/* delete a file */
public function delete_earnings()
{
    $rules = [
        'id' => 'required|numeric'
    ];

    if (!$this->validate($rules)) {
        return $this->response->setJSON(['success' => false, 'message' => $this->validator->getErrors()]);
    }

    $id = $this->request->getPost('id');
    if ($this->request->getPost('undo')) {
        if ($this->Country_earnings_model->delete($id, true)) {
            return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_undone')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    } else {
        if ($this->Country_earnings_model->delete($id)) {
            return $this->response->setJSON(['success' => true, 'message' => lang('record_deleted')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
        }
    }
}
public function payslip_deductions_info($country_id)
{
    //$this->update_only_allowed_members($user_id);

    /*$options = ['country_id' => $country_id];
    $view_data['files'] = $this->General_files_model->get_details($options)->getResult();*/
    $view_data['country_id'] = $country_id;
    return view('countries/payslip_deductions/index', $view_data);
}


        /* file upload modal */
 public function deductions_modal_form()
        {
            $view_data['model_info'] = $this->Country_deductions_model->find($this->request->getPost('id'));
            //$user_id = $this->request->getPost('user_id') ? $this->request->getPost('user_id') : $view_data['model_info']->user_id;
            $country_id = $this->request->getPost('country_id') ? $this->request->getPost('country_id') : $view_data['model_info']->country_id;
        
            //$this->update_only_allowed_members($user_id);
        
            $view_data['country_id'] = $country_id;
            return view('countries/payslip_deductions/modal_form', $view_data);
        }
        
        public function save_deductions()
        {
            $rules = [
                'id' => 'numeric',
                'title' => 'required',
                'percentage' => 'required'
            ];
        
            if (!$this->validate($rules)) {
                return $this->response->setJSON(['success' => false, 'message' => $this->validator->getErrors()]);
            }
        
            $id = $this->request->getPost('id');
            $country_id = $this->request->getPost('country_id');
            $percentage = $this->request->getPost('percentage');
            $status = $this->request->getPost('status');
            $data = [
                'title' => $this->request->getPost('title'),
                'percentage' => unformat_currency($this->request->getPost('percentage')),
                'status' => $this->request->getPost('status'),
                'description' => $this->request->getPost('description'),
                'country_id' => $country_id
            ];
        
            $save_id = $this->Country_deductions_model->save($data, $id);
            if ($save_id) {
                return $this->response->setJSON(['success' => true, 'data' => $this->_make_deductions_row($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
            }
        }

        /* list of files, prepared for datatable  */

        public function deductions_list_data($country_id = 0)
        {
            $options = ['country_id' => $country_id];
        
            $list_data = $this->Country_deductions_model->get_details($options)->getResult();
            $result = [];
            foreach ($list_data as $data) {
                $result[] = $this->_make_deductions_row($data);
            }
            return $this->response->setJSON(['data' => $result]);
        }
        
        private function _make_deductions_row($data)
        {
            $delete = '';
            $edit = '';
        
            if ($data->key_name) {
                $edit = modal_anchor(route_to('deductions_modal_form'), "<i class='fa fa-pencil'></i>", ['class' => 'edit', 'title' => lang('edit'), 'data-post-id' => $data->id]);
            } else {
                $edit = modal_anchor(route_to('deductions_modal_form'), "<i class='fa fa-pencil'></i>", ['class' => 'edit', 'title' => lang('edit'), 'data-post-id' => $data->id]);
                $delete = js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete'), 'class' => 'delete', 'data-id' => $data->id, 'data-action-url' => route_to('delete_deductions'), 'data-action' => 'delete-confirmation']);
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
    public function delete_deductions()
    {
        $rules = [
            'id' => 'required|numeric'
        ];
    
        if (!$this->validate($rules)) {
            return $this->response->setJSON(['success' => false, 'message' => $this->validator->getErrors()]);
        }
    
        $id = $this->request->getPost('id');
        if ($this->request->getPost('undo')) {
            if ($this->Country_deductions_model->delete($id, true)) {
                return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_undone')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($this->Country_deductions_model->delete($id)) {
                return $this->response->setJSON(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }
    

    //country logo 
    public function save_profile_image($country_id = 0)
    {
        $client_logo = str_replace("~", ":", $this->request->getPost("profile_image"));
        $target_path = FCPATH . get_general_file_path("country_profile_image", $country_id);
        $value = move_temp_file("country-logo.png", $target_path, "", $client_logo);
    
        $client_info_logo = $this->Countries_model->find($country_id);
        $client_logo_file = $client_info_logo->image;
    
        if ($client_logo && !$client_logo_file) {
            $image_data = ['image' => $value];
        } elseif ($client_logo && $client_logo_file) {
            delete_file_from_directory(get_general_file_path("country_profile_image", $country_id) . $client_logo_file);
            $image_data = ['image' => $value];
        }
    
        $payslip_save = $this->Countries_model->save($image_data, $country_id);
    
        if ($payslip_save) {
            return $this->response->setJSON(['success' => true, 'message' => lang('profile_image_changed')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }
}
    
/* End of file taxes.php */
/* Location: ./application/controllers/taxes.php */