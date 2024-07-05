<?php

namespace App\Controllers;

class Signup extends BaseController {
    protected$usersmodel;
    protected$partnersmodel;
    protected$companysmodel;
    protected$clientsmodel;

    function __construct() {
        parent::__construct();
        $this->load->helper('email');
    }

    function index() {
        //by default only client can signup directly
        //if client login/signup is disabled then show 404 page
        if (get_setting("disable_client_signup")) {
            show_404();
        }

        $view_data["type"] = "client";
        $view_data["signup_type"] = "new_client";
        $view_data["signup_message"] = lang("create_an_account_as_a_new_client");
        $this->load->view("signup/index", $view_data);
    }

     function company() {
        //by default only company can signup directly
        //if company login/signup is disabled then show 404 page
        if (get_setting("disable_company_signup")) {
            show_404();
        }

        $view_data["type"] = "company";
        $view_data["signup_company"] = "signup_company";
        $view_data["signup_type"] = "new_company";
        $view_data["signup_message"] = lang("create_an_account_as_a_new_company");
        $this->load->view("signup/index", $view_data);
    }

    //redirected from email
    function accept_invitation($signup_key = "") {
        $valid_key = $this->is_valid_key($signup_key);
        if ($valid_key) {
            $email = get_array_value($valid_key, "email");
            $type = get_array_value($valid_key, "type");
            if ($this->Users_model->is_email_exists($email)) {
                $view_data["heading"] = "Account exists!";
                $view_data["message"] = lang("account_already_exists_for_your_mail") . " " . anchor("signin", lang("signin"));
                $this->load->view("errors/html/error_general", $view_data);
                return false;
            }

            if ($type === "staff") {
                $view_data["signup_message"] = lang("create_an_account_as_a_team_member");
            }if ($type === "resource") {
                $view_data["signup_message"] = lang("create_an_account_as_a_outsource_member");
            } else if ($type === "client") {
                $view_data["signup_message"] = lang("create_an_account_as_a_client_contact");
            }else if ($type === "partner") {
                $view_data["signup_message"] = lang("create_an_account_as_a_new_partner");
            }else if ($type === "company") {
                $view_data["signup_message"] = lang("create_an_account_as_a_company_contact");
            }

            $view_data["signup_type"] = "invitation";
            $view_data["type"] = $type;
            $view_data["signup_key"] = $signup_key;
            $this->load->view("signup/index", $view_data);
        } else {
            $view_data["heading"] = "406 Not Acceptable";
            $view_data["message"] = lang("invitation_expaired_message");
            $this->load->view("errors/html/error_general", $view_data);
        }
    }

    private function is_valid_key($signup_key = "") {
        $signup_key = decode_id($signup_key, "signup");
        $signup_key = $this->encryption->decrypt($signup_key);
        $signup_key = explode('|', $signup_key);
        $type = get_array_value($signup_key, "0");
        $email = get_array_value($signup_key, "1");
        $expire_time = get_array_value($signup_key, "2");
        $client_id = get_array_value($signup_key, "3");
        if ($type && $email && valid_email($email) && $expire_time && $expire_time > time()) {
            return array("type" => $type, "email" => $email, "client_id" => $client_id);
        }
    }

    private function is_valid_recaptcha($recaptcha_post_data) {
        //load recaptcha lib
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

    function create_account() {

        $signup_key = $this->input->post("signup_key");
        $signup_company = $this->input->post("signup_company");

        validate_submitted_data(array(
            "first_name" => "required",
            "last_name" => "required",
            "password" => "required"
        ));


        //check if there reCaptcha is enabled
        //if reCaptcha is enabled, check the validation
        if (get_setting("re_captcha_secret_key")) {

            $response = $this->is_valid_recaptcha($this->input->post("g-recaptcha-response"));

            if ($response !== true) {

                if ($response) {
                    echo json_encode(array('success' => false, 'message' => lang("re_captcha_error-" . $response)));
                } else {
                    echo json_encode(array('success' => false, 'message' => lang("re_captcha_expired")));
                }

                return false;
            }
        }

        $user_data = array(
            "first_name" => $this->input->post("first_name"),
            "last_name" => $this->input->post("last_name"),
            "job_title" => $this->input->post("job_title") ? $this->input->post("job_title") : "Untitled",
            "gender" => $this->input->post("gender"),
            "created_at" => get_current_utc_time()
        );

        $user_data = clean_data($user_data);

        // don't clean password since there might be special characters 
        $user_data["password"] = md5($this->input->post("password"));


        if ($signup_key) {
            //it is an invitation, validate the invitation key
            $valid_key = $this->is_valid_key($signup_key);

            if ($valid_key) {

                $email = get_array_value($valid_key, "email");
                $type = get_array_value($valid_key, "type");
                $clent_id = get_array_value($valid_key, "client_id");

                //show error message if email already exists
                if ($this->Users_model->is_email_exists($email)) {
                    echo json_encode(array("success" => false, 'message' => lang("account_already_exists_for_your_mail") . " " . anchor("signin", lang("signin"))));
                    return false;
                }

                $user_data["email"] = $email;
                $user_data["user_type"] = $type;

                if ($type === "staff") {
                    //create a team member account
                    $user_id = $this->Users_model->save($user_data);
                    if ($user_id) {
                        //save team members job info
                        $job_data = array(
                            "user_id" => $user_id,
                            "salary" => 0,
                            "salary_term" => 0,
                            "date_of_hire" => ""
                        );
                        $this->Users_model->save_job_info($job_data);
                    }
                }else if ($type === "resource") {
                    //create a team member account
                    $user_id = $this->Users_model->save($user_data);
                    if ($user_id) {
                        //save team members job info
                        $job_data = array(
                            "user_id" => $user_id,
                            "salary" => 0,
                            "salary_term" => 0,
                            "date_of_hire" => ""
                        );
                        $this->Users_model->save_job_info($job_data);
                    }
                     }else if ($type === "partner") {
                    //create a team member account
                    $client = $this->Partners_model->get_one($clent_id);
                    $DB1 = $this->load->database('default', TRUE);
 $DB1->select ("id");
 $DB1->from('clients');
  $DB1->where('partner_id',$clent_id);
 $query1=$DB1->get();
 $s=$query1->result();  
foreach ($query1->result() as $rows)
    {
    $b=$rows->id;
}
                    if (isset($client->id) && $client->deleted == 0) {
                        $user_data["partner_id"] = $clent_id;
$user_data["user_type"] = 'client';
$user_data["client_id"] = $b;
$user_data["job_title"] = "";

                        //has any primary contact for this clinet? if not, make this contact as a primary contact
                        $primary_contact = $this->Partners_model->get_primary_contact($clent_id);
                        if (!$primary_contact) {
                            $user_data['is_primary_contact'] = 1;
                        }

                        //create a client contact account
                        $user_id = $this->Users_model->save($user_data);
                    } else {
                        //invalid client
                        echo json_encode(array("success" => false, 'message' => lang("something_went_wrong")));
                        return false;
                    }
                
                    
                }else if ($type === "company") {
                    //create a company contact account
                    $client = $this->Companys_model->get_one($clent_id);
                    
                    if (isset($client->id) && $client->deleted == 0) {

                       $user_data["user_type"] = 'company';
                       $user_data["company_id"] = $clent_id;
                       $user_data["job_title"] = "";


                        //has any primary contact for this clinet? if not, make this contact as a primary contact
                        $primary_contact = $this->Companys_model->get_primary_contact($clent_id);
                        if (!$primary_contact) {
                            $user_data['is_primary_contact'] = 1;
                        }

                        //create a client contact account
                        $user_id = $this->Users_model->save($user_data);
                    } else {
                        //invalid client
                        echo json_encode(array("success" => false, 'message' => lang("something_went_wrong")));
                        return false;
                    }
                
                    
                }



                 else {
                    //check client id and create client contact account
                    $client = $this->Clients_model->get_one($clent_id);
                    if (isset($client->id) && $client->deleted == 0) {
                        $user_data["client_id"] = $clent_id;

                        //has any primary contact for this clinet? if not, make this contact as a primary contact
                        $primary_contact = $this->Clients_model->get_primary_contact($clent_id);
                        if (!$primary_contact) {
                            $user_data['is_primary_contact'] = 1;
                        }

                        //create a client contact account
                        $user_id = $this->Users_model->save($user_data);
                    } else {
                        //invalid client
                        echo json_encode(array("success" => false, 'message' => lang("something_went_wrong")));
                        return false;
                    }
                }
            } else {
                //invalid key. show an error message
                echo json_encode(array("success" => false, 'message' => lang("invitation_expaired_message")));
                return false;
            }
        }else if($signup_company=="signup_company") {
            //create a client directly
            if (get_setting("disable_company_signup")) {
                show_404();
            }

            validate_submitted_data(array(
                "email" => "required|valid_email",
                "company_name" => "required"
            ));

            $email = $this->input->post("email");
            $company_name = $this->input->post("company_name");

       
            if ($this->Users_model->is_email_exists($email)) {
                echo json_encode(array("success" => false, 'message' => lang("account_already_exists_for_your_mail") . " " . anchor("signin", lang("signin"))));
                return false;
            }

            $client_data = array("company_name" => $company_name);

            $client_data = clean_data($client_data);

            //check duplicate company name, if found then show an error message
            if ($this->Companys_model->is_duplicate_company_name($company_name)) {
                echo json_encode(array("success" => false, 'message' => lang("account_already_exists_for_your_company_name") . " " . anchor("signin", lang("signin"))));
                return false;
            }

            //create a client
            $client_id = $this->Companys_model->save($client_data);
            if($client_id){
                $crid_string = "CR";
               if($client_id>99){
                  $cr_id =   $crid_string.$client_id;
               }else if($client_id>9){
                  $cr_id =   $crid_string.'0'.$client_id;
               }if($client_id<10){
                  $cr_id =   $crid_string.'00'.$client_id;
               }
               $data = array(
                   
                    "cr_id" => $cr_id,
                );
                $invoice_prefix_id = $this->Companys_model->save($data , $client_id);
            }
            if ($client_id) {
                //client created, now create the client contact
                $company_info = $this->Companys_model->get_one($client_id);
                $user_data["user_type"] = "company";
                $user_data["email"] = $email;
                $user_data["company_id"] = $company_info->cr_id;
                $user_data["is_primary_contact"] = 1;
                $user_data["job_title"] = "";
                 $user_id = $this->Users_model->save($user_data);

                log_notification("company_signup", array("company_id" => $client_id), $user_id);
            } else {
                echo json_encode(array("success" => false, 'message' => lang('error_occurred')));
                return false;
            }
        }
        else {
            //create a client directly
            if (get_setting("disable_client_signup")) {
                show_404();
            }

            validate_submitted_data(array(
                "email" => "required|valid_email",
                "company_name" => "required"
            ));

            $email = $this->input->post("email");
            $company_name = $this->input->post("company_name");

       
            if ($this->Users_model->is_email_exists($email)) {
                echo json_encode(array("success" => false, 'message' => lang("account_already_exists_for_your_mail") . " " . anchor("signin", lang("signin"))));
                return false;
            }

            $client_data = array("company_name" => $company_name);

            $client_data = clean_data($client_data);

            //check duplicate company name, if found then show an error message
            if (get_setting("disallow_duplicate_client_company_name") == "1" && $this->Clients_model->is_duplicate_company_name($company_name)) {
                echo json_encode(array("success" => false, 'message' => lang("account_already_exists_for_your_company_name") . " " . anchor("signin", lang("signin"))));
                return false;
            }

            
            //create a client
            $client_id = $this->Clients_model->save($client_data);
            if ($client_id) {
                //client created, now create the client contact
                $user_data["user_type"] = "client";
                $user_data["email"] = $email;
                $user_data["client_id"] = $client_id;
                $user_data["is_primary_contact"] = 1;
                $user_id = $this->Users_model->save($user_data);

                log_notification("client_signup", array("client_id" => $client_id), $user_id);
            } else {
                echo json_encode(array("success" => false, 'message' => lang('error_occurred')));
                return false;
            }
        }


        if ($user_id) {
            echo json_encode(array("success" => true, 'message' => lang('account_created') . " " . anchor("signin", lang("signin"))));
        } else {
            echo json_encode(array("success" => false, 'message' => lang('error_occurred')));
        }
    }

}
