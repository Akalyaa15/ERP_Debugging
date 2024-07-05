<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;

class Vendor_signup extends BaseController
{
    use ResponseTrait;

    protected $usersmodel;
    protected $vendorsmodel;

    public function __construct()
    {
        parent::__construct();

        // Load helper and models
        helper(['email']);
        $this->usersmodel = new \App\Models\Users_model(); // Adjust model namespace as per your application
        $this->vendorsmodel = new \App\Models\Vendors_model(); // Adjust model namespace as per your application
    }

    public function index()
    {
        // Check if vendor signup is disabled
        if (get_setting("disable_vendor_signup")) {
            return view('errors/html/error_404'); // Assuming a 404 view exists
        }

        $viewData = [
            'type' => 'vendor',
            'signup_type' => 'new_vendor',
            'signup_message' => lang('create_an_account_as_a_new_vendor')
        ];

        return view('vendor_signup/index', $viewData);
    }

    public function accept_invitation($signup_key = "")
    {
        $valid_key = $this->is_valid_key($signup_key);
        if ($valid_key) {
            $email = $valid_key['email'];
            $type = $valid_key['type'];

            // Check if email already exists
            if ($this->usersmodel->is_email_exists($email)) {
                $viewData = [
                    'heading' => 'Account exists!',
                    'message' => lang('account_already_exists_for_your_mail') . ' ' . anchor('signin', lang('signin'))
                ];
                return view('errors/html/error_general', $viewData);
            }

            // Determine signup message based on type
            if ($type === 'staff') {
                $signup_message = lang('create_an_account_as_a_team_member');
            } elseif ($type === 'vendor') {
                $signup_message = lang('create_an_account_as_a_vendor_contact');
            }

            $viewData = [
                'signup_message' => $signup_message,
                'signup_type' => 'invitation',
                'type' => $type,
                'signup_key' => $signup_key
            ];

            return view('vendor_signup/index', $viewData);
        } else {
            $viewData = [
                'heading' => '406 Not Acceptable',
                'message' => lang('invitation_expaired_message')
            ];
            return view('errors/html/error_general', $viewData);
        }
    }

    private function is_valid_key($signup_key = "")
    {
        $signup_key = decode_id($signup_key, 'vendor_signup');
        $signup_key = $this->encryption->decrypt($signup_key);
        $signup_key = explode('|', $signup_key);

        $type = get_array_value($signup_key, 0);
        $email = get_array_value($signup_key, 1);
        $expire_time = get_array_value($signup_key, 2);

        if ($type && $email && valid_email($email) && $expire_time && $expire_time > time()) {
            return [
                'type' => $type,
                'email' => $email,
                'expire_time' => $expire_time
            ];
        }
        return false;
    }

    private function is_valid_recaptcha($recaptcha_post_data)
    {
        // Load reCAPTCHA library
        require_once(APPPATH . 'ThirdParty/recaptcha/autoload.php');
        $recaptcha = new \ReCaptcha\ReCaptcha(get_setting('re_captcha_secret_key'));
        $resp = $recaptcha->verify($recaptcha_post_data, $_SERVER['REMOTE_ADDR']);

        if ($resp->isSuccess()) {
            return true;
        } else {
            $error = '';
            foreach ($resp->getErrorCodes() as $code) {
                $error = $code;
            }
            return $error;
        }
    }

    public function create_account()
    {
        $signup_key = $this->request->getPost('signup_key');

        // Validate required fields
        $this->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'password' => 'required'
        ]);

        // Check if reCAPTCHA is enabled
        if (get_setting('re_captcha_secret_key')) {
            $response = $this->is_valid_recaptcha($this->request->getPost('g-recaptcha-response'));

            if ($response !== true) {
                if ($response) {
                    return $this->fail(lang('re_captcha_error-' . $response));
                } else {
                    return $this->fail(lang('re_captcha_expired'));
                }
            }
        }

        // Prepare user data
        $user_data = [
            'first_name' => $this->request->getPost('first_name'),
            'last_name' => $this->request->getPost('last_name'),
            'job_title' => $this->request->getPost('job_title') ?: 'Untitled',
            'gender' => $this->request->getPost('gender'),
            'created_at' => get_current_utc_time(),
            'password' => md5($this->request->getPost('password'))
        ];

        // Clean user data
        $user_data = clean_data($user_data);

        if ($signup_key) {
            // Validate invitation key
            $valid_key = $this->is_valid_key($signup_key);

            if ($valid_key) {
                $email = $valid_key['email'];
                $type = $valid_key['type'];
                $vendor_id = $valid_key['vendor_id'];

                // Check if email already exists
                if ($this->usersmodel->is_email_exists($email)) {
                    return $this->fail(lang('account_already_exists_for_your_mail') . ' ' . anchor('signin', lang('signin')));
                }

                $user_data['email'] = $email;
                $user_data['user_type'] = $type;

                if ($type === 'staff') {
                    // Create team member account
                    $user_id = $this->usersmodel->save($user_data);

                    if ($user_id) {
                        // Save team member job info
                        $job_data = [
                            'user_id' => $user_id,
                            'salary' => 0,
                            'salary_term' => 0,
                            'date_of_hire' => ''
                        ];
                        $this->usersmodel->save_job_info($job_data);
                    }
                } elseif ($type === 'vendor') {
                    // Check vendor existence and create client contact account
                    $vendor = $this->vendorsmodel->get_one($vendor_id);

                    if ($vendor && !$vendor->deleted) {
                        $user_data['vendor_id'] = $vendor_id;

                        // Check if primary contact exists
                        $primary_contact = $this->vendorsmodel->get_primary_contact($vendor_id);

                        if (!$primary_contact) {
                            $user_data['is_primary_contact'] = 1;
                        }

                        // Create client contact account
                        $user_id = $this->usersmodel->save($user_data);
                    } else {
                        // Invalid vendor
                        return $this->fail(lang('something_went_wrong'));
                    }
                }
            } else {
                // Invalid key
                return $this->fail(lang('invitation_expaired_message'));
            }
        } else {
            // Direct client creation
            if (get_setting('disable_vendor_signup')) {
                return view('errors/html/error_404'); // Assuming a 404 view exists
            }

            // Validate required fields
            $this->validate([
                'email' => 'required|valid_email',
                'company_name' => 'required'
            ]);

            $email = $this->request->getPost('email');
            $company_name = $this->request->getPost('company_name');

            // Check if email already exists
            if ($this->usersmodel->is_email_exists($email)) {
                return $this->fail(lang('account_already_exists_for_your_mail') . ' ' . anchor('signin', lang('signin')));
            }

            $vendor_data = ['company_name' => $company_name];
            $vendor_data = clean_data($vendor_data);

            // Check duplicate company name
            if (get_setting('disallow_duplicate_client_company_name') === '1' && $this->vendorsmodel->is_duplicate_company_name($company_name)) {
                return $this->fail(lang('account_already_exists_for_your_company_name') . ' ' . anchor('signin', lang('signin')));
            }

            // Create client
            $vendor_id = $this->vendorsmodel->save($vendor_data);

            if ($vendor_id) {
                // Client created, now create client contact
                $user_data['user_type'] = 'vendor';
                $user_data['email'] = $email;
                $user_data['vendor_id'] = $vendor_id;
                $user_data['is_primary_contact'] = 1;

                $user_id = $this->usersmodel->save($user_data);

                // Log notification
                log_notification('client_signup', ['client_id' => $client_id], $user_id);
            } else {
                return $this->fail(lang('error_occurred'));
            }
        }

        // Respond with success or failure message
        if ($user_id) {
            return $this->respond([
                'success' => true,
                'message' => lang('account_created') . ' ' . anchor('signin', lang('signin'))
            ]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

}
