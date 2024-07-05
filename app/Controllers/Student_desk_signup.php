<?php

namespace App\Controllers;

use App\Models\StudentDeskModel;
use App\Models\CountriesModel;
use CodeIgniter\API\ResponseTrait;

class StudentDeskSignup extends BaseController
{
    use ResponseTrait;

    protected $vapCategoryModel;
    protected $usersModel;
    protected $studentDeskModel;
    protected $countriesModel;

    public function __construct()
    {
        $this->vapCategoryModel = new VapCategoryModel(); // Replace with your VapCategoryModel class
        $this->usersModel = new UsersModel(); // Replace with your UsersModel class
        $this->studentDeskModel = new StudentDeskModel(); // Replace with your StudentDeskModel class
        $this->countriesModel = new CountriesModel(); // Replace with your CountriesModel class
    }

    public function index()
    {
        if (get_setting("disable_student_desk_registration")) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $viewData = [
            "type" => "vendor",
            "signup_type" => "new_vendor",
            "signup_message" => lang("create_an_account_as_a_new_student_desk"),
            "vap_category_dropdown" => $this->vapCategoryModel->getDropdownList(["title"], "id", ["status" => "active"]),
            "time_format_24_hours" => get_setting("time_format") == "24_hours"
        ];

        return view("student_desk_signup/index", $viewData);
    }

    public function accept_invitation($signup_key = "")
    {
        $valid_key = $this->is_valid_key($signup_key);

        if ($valid_key) {
            $email = $valid_key['email'];
            $type = $valid_key['type'];

            if ($this->usersModel->isEmailExists($email)) {
                $viewData = [
                    "heading" => "Account exists!",
                    "message" => lang("account_already_exists_for_your_mail") . " " . anchor("signin", lang("signin"))
                ];
                return view("errors/html/error_general", $viewData);
            }

            $viewData = [
                "signup_message" => $type === "staff" ? lang("create_an_account_as_a_team_member") : lang("create_an_account_as_a_vendor_contact"),
                "signup_type" => "invitation",
                "type" => $type,
                "signup_key" => $signup_key
            ];

            return view("vendor_signup/index", $viewData);
        } else {
            $viewData = [
                "heading" => "406 Not Acceptable",
                "message" => lang("invitation_expaired_message")
            ];
            return view("errors/html/error_general", $viewData);
        }
    }

    private function is_valid_key($signup_key)
    {
        $signup_key = decode_id($signup_key, "student_desk_signup");
        $signup_key = $this->encryption->decrypt($signup_key);
        $signup_key = explode('|', $signup_key);

        $type = get_array_value($signup_key, 0);
        $email = get_array_value($signup_key, 1);
        $expire_time = get_array_value($signup_key, 2);
        $vendor_id = get_array_value($signup_key, 3);

        if ($type && $email && valid_email($email) && $expire_time && $expire_time > time()) {
            return [
                "type" => $type,
                "email" => $email,
                "vendor_id" => $vendor_id
            ];
        }

        return null;
    }

    private function is_valid_recaptcha($recaptcha_post_data)
    {
        // Load reCaptcha library
        require_once(APPPATH . "third_party/recaptcha/autoload.php");
        $recaptcha = new \ReCaptcha\ReCaptcha(get_setting("re_captcha_secret_key"));
        $resp = $recaptcha->verify($recaptcha_post_data, $_SERVER['REMOTE_ADDR']);

        if ($resp->isSuccess()) {
            return true;
        } else {
            $error = "";
            foreach ($resp->getErrorCodes() as $code) {
                $error = $code;
            }
            return $error;
        }
    }

    public function create_account()
    {
        $signup_key = $this->request->getPost("signup_key");

        if ($this->studentDeskModel->isStudentDeskEmailExists($this->request->getPost('email'))) {
            return $this->respond(["success" => false, "message" => lang('duplicate_email')]);
        }

        $rules = [
            "name" => "required"
        ];

        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors());
        }

        // Check reCaptcha if enabled
        if (get_setting("re_captcha_secret_key")) {
            $response = $this->is_valid_recaptcha($this->request->getPost("g-recaptcha-response"));

            if ($response !== true) {
                if ($response) {
                    return $this->fail(lang("re_captcha_error-" . $response));
                } else {
                    return $this->fail(lang("re_captcha_expired"));
                }
            }
        }

        // Process form data
        $user_data = [
            "name" => $this->request->getPost('name'),
            "college_name" => $this->request->getPost('college_name'),
            "department" => $this->request->getPost('department'),
            "date" => $this->request->getPost('date'),
            "phone" => $this->request->getPost('phone'),
            "communication_address" => $this->request->getPost('communication_address'),
            "pincode" => $this->request->getPost('pincode'),
            "state" => $this->request->getPost('state'),
            "district" => $this->request->getPost('district'),
            "alternative_phone" => $this->request->getPost('alternative_phone'),
            "gender" => $this->request->getPost('gender'),
            "email" => $this->request->getPost('email'),
            "dob" => $this->request->getPost('dob'),
            "year" => $this->request->getPost('year'),
            "vap_category" => $this->request->getPost('vap_category'),
            "program_title" => $this->request->getPost('program_title'),
            "start_date" => $this->request->getPost('start_date'),
            "end_date" => $this->request->getPost('end_date'),
            "start_time" => $this->request->getPost('start_time'),
            "end_time" => $this->request->getPost('end_time'),
            "parent_name" => $this->request->getPost('parent_name'),
            "permanent_address" => $this->request->getPost('permanent_address'),
            "last_name" => $this->request->getPost('last_name'),
            "aadhar_no" => $this->request->getPost('aadhar_no'),
            "country" => $this->request->getPost('country')
        ];
        $user_data = clean_data($user_data);
        $user_id = $this->studentDeskModel->save($user_data);

        if ($user_id) {
            return $this->respond(["success" => true, "message" => lang('account_created') . " " . anchor("signin", lang("signin")) . "<br> " . anchor(get_uri("student_desk_signup/download_pdf/" . $user_id), "<i class='fa fa-download'></i>" . lang('download_pdf'), ["class" => "btn btn-default", "title" => lang('download_pdf')]), "id" => $user_id]);
        } else {
            return $this->fail(["message" => lang('error_occurred')]);
        }
    }

    public function download_pdf($student_desk_id = 0)
    {
        if ($student_desk_id) {
            $student_desk_data = get_student_making_data($student_desk_id); // Replace with your logic to get student data

            if ($student_desk_data) {
                prepare_student_desk_pdf($student_desk_data, "download");
            } else {
                return $this->fail(lang("error_occurred"));
            }
        } else {
            return $this->fail(lang("error_occurred"));
        }
    }

    public function get_country_item_suggestion()
    {
        $key = $this->request->getVar("q");
        $suggestion = [];
        $items = $this->countriesModel->get_country_suggestion($key);

        foreach ($items as $item) {
            $suggestion[] = ["id" => $item->id, "text" => $item->countryName];
        }

        return $this->respond($suggestion);
    }

    public function get_country_item_info_suggestion()
    {
        $itemName = $this->request->getPost("item_name");
        $item = $this->countriesModel->get_country_info_suggestion($itemName);

        if ($item) {
            return $this->respond(["success" => true, "item_info" => $item]);
        } else {
            return $this->fail(lang("error_occurred"));
        }
    }

    public function get_country_code_suggestion()
    {
        $itemName = $this->request->getPost("item_name");
        $item = $this->countriesModel->get_country_code_suggestion($itemName);

        if ($item) {
            return $this->respond(["success" => true, "item_info" => $item]);
        } else {
            return $this->fail(lang("error_occurred"));
        }
    }

    public function get_state_suggestion()
    {
        $key = $this->request->getVar("q");
        $ss = $this->request->getVar("ss");
        $itemss = $this->countriesModel->get_item_suggestions_country_name($key, $ss);
        $suggestions = [];

        foreach ($itemss as $items) {
            $suggestions[] = ["id" => $items->id, "text" => $items->title];
        }

        return $this->respond($suggestions);
    }

    public function get_state_suggestionss()
    {
        $key = $this->request->getVar("q");
        $ss = $this->request->getVar("ss");
        $itemss = $this->countriesModel->get_country_suggestionss($key, $ss);
        $suggestions = [];

        foreach ($itemss as $items) {
            $suggestions[] = ["id" => $items->id, "text" => $items->title];
        }

        return $this->respond($suggestions);
    }
}
