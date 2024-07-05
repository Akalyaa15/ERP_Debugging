<?php

namespace App\Controllers;

use App\Models\UsersModel;
use App\Models\PartnersModel;
use App\Models\ClientsModel;
use CodeIgniter\API\ResponseTrait;

class Signup extends BaseController
{
    use ResponseTrait;

    protected $usersModel;
    protected $partnersModel;
    protected $clientsModel;

    public function __construct()
    {
        $this->usersModel = new UsersModel();
        $this->partnersModel = new PartnersModel();
        $this->clientsModel = new ClientsModel();
    }
   public function index()
    {
        // By default only client can signup directly
        // If client login/signup is disabled then show 404 page
        if (get_setting("disable_client_signup")) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $view_data = [
            'type' => 'client',
            'signup_type' => 'new_client',
            'signup_message' => lang("create_an_account_as_a_new_client")
        ];

        return view('signup/index', $view_data);
    }

    // Redirected from email
    public function accept_invitation($signup_key = '')
    {
        $valid_key = $this->is_valid_key($signup_key);

        if (!$valid_key) {
            $view_data = [
                'heading' => '406 Not Acceptable',
                'message' => lang("invitation_expaired_message")
            ];
            return view('errors/html/error_general', $view_data);
        }

        $email = $valid_key['email'];
        $type = $valid_key['type'];
        $client_id = $valid_key['client_id'];

        if ($this->usersModel->is_email_exists($email)) {
            $view_data = [
                'heading' => 'Account exists!',
                'message' => lang("account_already_exists_for_your_mail") . " " . anchor("signin", lang("signin"))
            ];
            return view("errors/html/error_general", $view_data);
        }

        if ($type === "staff") {
            $signup_message = lang("create_an_account_as_a_team_member");
        } else if ($type === "resource") {
            $signup_message = lang("create_an_account_as_a_outsource_member");
        } else if ($type === "client") {
            $signup_message = lang("create_an_account_as_a_client_contact");
        } else if ($type === "partner") {
            $signup_message = lang("create_an_account_as_a_new_partner");
        }

        $view_data = [
            'signup_message' => $signup_message,
            'signup_type' => 'invitation',
            'type' => $type,
            'signup_key' => $signup_key
        ];

        return view('signup/index', $view_data);
    }

    private function is_valid_key($signup_key = '')
    {
        $signup_key = decode_id($signup_key, "signup");
        $signup_key = $this->encryption->decrypt($signup_key);
        $signup_key = explode('|', $signup_key);

        $type = get_array_value($signup_key, "0");
        $email = get_array_value($signup_key, "1");
        $expire_time = get_array_value($signup_key, "2");
        $client_id = get_array_value($signup_key, "3");

        if ($type && $email && valid_email($email) && $expire_time && $expire_time > time()) {
            return [
                'type' => $type,
                'email' => $email,
                'client_id' => $client_id
            ];
        }

        return false;
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

        helper(['form', 'url']);

        // Validate submitted data
        $validationRules = [
            'first_name' => 'required',
            'last_name' => 'required',
            'password' => 'required'
        ];

        if (get_setting("re_captcha_secret_key")) {
            $validationRules['g-recaptcha-response'] = 'callback_check_recaptcha';
        }

        if (!$this->validate($validationRules)) {
            return $this->fail($this->validator->getErrors());
        }

        $user_data = [
            'first_name' => $this->request->getPost("first_name"),
            'last_name' => $this->request->getPost("last_name"),
            'job_title' => $this->request->getPost("job_title") ? $this->request->getPost("job_title") : "Untitled",
            'gender' => $this->request->getPost("gender"),
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Don't clean password since there might be special characters
        $user_data['password'] = md5($this->request->getPost("password"));

        if ($signup_key) {
            // It is an invitation, validate the invitation key
            $valid_key = $this->is_valid_key($signup_key);

            if (!$valid_key) {
                return $this->fail(lang("invitation_expaired_message"));
            }

            $email = $valid_key['email'];
            $type = $valid_key['type'];
            $client_id = $valid_key['client_id'];

            // Show error message if email already exists
            if ($this->usersModel->is_email_exists($email)) {
                return $this->fail(lang("account_already_exists_for_your_mail") . " " . anchor("signin", lang("signin")));
            }

            $user_data['email'] = $email;
            $user_data['user_type'] = $type;

            if ($type === "staff" || $type === "resource") {
                // Create a team member account
                $user_id = $this->usersModel->save($user_data);

                if ($user_id) {
                    // Save team member's job info
                    $job_data = [
                        'user_id' => $user_id,
                        'salary' => 0,
                        'salary_term' => 0,
                        'date_of_hire' => ""
                    ];
                    $this->usersModel->save_job_info($job_data);
                }
            } else if ($type === "partner") {
                // Create a partner account
                $partner = $this->partnersModel->find($client_id);

                if ($partner && !$partner->deleted) {
                    $user_data['partner_id'] = $client_id;
                    $user_data['user_type'] = 'client';

                    // Check if there's any primary contact for this client
                    $primary_contact = $this->partnersModel->get_primary_contact($client_id);
                    if (!$primary_contact) {
                        $user_data['is_primary_contact'] = 1;
                    }

                    // Create a client contact account
                    $user_id = $this->usersModel->save($user_data);
                } else {
                    // Invalid client
                    return $this->fail(lang("something_went_wrong"));
                }
            } else {
                // Check client ID and create client contact account
                $client = $this->clientsModel->find($client_id);

                if ($client && !$client->deleted) {
                    $user_data['client_id'] = $client_id;

                    // Check if there's any primary contact for this client
                    $primary_contact = $this->clientsModel->get_primary_contact($client_id);
                    if (!$primary_contact) {
                        $user_data['is_primary_contact'] = 1;
                    }

                    // Create a client contact account
                    $user_id = $this->usersModel->save($user_data);
                } else {
                    // Invalid client
                    return $this->fail(lang("something_went_wrong"));
                }
            }
        } else {
            // Create a client directly
            if (get_setting("disable_client_signup")) {
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
            }

            // Validate submitted data for direct client creation
            $validationRules = [
                'email' => 'required|valid_email',
                'company_name' => 'required'
            ];

            if (!$this->validate($validationRules)) {
                return $this->fail($this->validator->getErrors());
            }

            $email = $this->request->getPost("email");
            $company_name = $this->request->getPost("company_name");

            // Check if email already exists
            if ($this->usersModel->is_email_exists($email)) {
                return $this->fail(lang("account_already_exists_for_your_mail") . " " . anchor("signin", lang("signin")));
            }

            $client_data = [
                'company_name' => $company_name
            ];

            // Clean data before saving
            $client_data = clean_data($client_data);

            // Check duplicate company name if disallowed
            if (get_setting("disallow_duplicate_client_company_name") == "1" && $this->clientsModel->is_duplicate_company_name($company_name)) {
                return $this->fail(lang("account_already_exists_for_your_company_name") . " " . anchor("signin", lang("signin")));
            }

            // Create a client
            $client_id = $this->clientsModel->save($client_data);

            if ($client_id) {
                // Client created, now create the client contact
                $user_data['user_type'] = "client";
                $user_data['email'] = $email;
                $user_data['client_id'] = $client_id;
                $user_data['is_primary_contact'] = 1;
                $user_id = $this->usersModel->save($user_data);

                // Log notification for client signup
                log_notification("client_signup", ['client_id' => $client_id], $user_id);
            } else {
                return $this->fail(lang('error_occurred'));
            }
        }

        if ($user_id) {
            return $this->respondCreated(['success' => true, 'message' => lang('account_created') . " " . anchor("signin", lang("signin"))]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

    // Callback function to check reCaptcha
    public function check_recaptcha($recaptcha_post_data)
    {
        $response = $this->is_valid_recaptcha($recaptcha_post_data);

        if ($response !== true) {
            if ($response) {
                $this->validator->setError('re_captcha_response', lang("re_captcha_error-" . $response));
            } else {
                $this->validator->setError('re_captcha_response', lang("re_captcha_expired"));
            }
            return false;
        }

        return true;
    }
}

