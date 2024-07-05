<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use App\Models\UsersModel;
use App\Models\EmailTemplatesModel;

class Signin extends Controller
{
    protected $usersModel;
    protected $emailTemplatesModel;

    public function __construct()
    {
        $this->usersModel = new UsersModel();
        $this->emailTemplatesModel = new EmailTemplatesModel();

        helper(['email', 'url', 'form']);
    }

    public function index()
    {
        if ($this->usersModel->login_user_id()) {
            return redirect()->to('dashboard/view');
        } else {
            $view_data = [
                'redirect' => $this->request->getVar('redirect') ?? ''
            ];

            // Check if reCaptcha is enabled
            if (get_setting("re_captcha_secret_key")) {
                $this->validate([
                    'g-recaptcha-response' => 'callback_check_recaptcha'
                ]);
            }

            $this->validate([
                'email' => 'callback_authenticate'
            ]);

            if ($this->validator->hasError()) {
                return view('signin/index', $view_data);
            } else {
                if (!empty($view_data['redirect'])) {
                    return redirect()->to($view_data['redirect']);
                } else {
                    return redirect()->to('dashboard/view');
                }
            }
        }
    }

    public function check_recaptcha($recaptcha_post_data)
    {
        $response = $this->is_valid_recaptcha($recaptcha_post_data);

        if ($response === true) {
            return true;
        } else {
            $this->validator->setError('g-recaptcha-response', lang("re_captcha_error-" . $response));
            return false;
        }
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

    public function authenticate($email)
    {
        if ($this->validator->hasError()) {
            return false;
        }

        $password = $this->request->getPost("password");
        if (!$this->usersModel->authenticate($email, $password)) {
            $this->validator->setError('email', lang("authentication_failed"));
            return false;
        }
        return true;
    }

    public function sign_out()
    {
        $this->usersModel->sign_out();
    }

    public function send_reset_password_mail()
    {
        $this->validate([
            'email' => 'required|valid_email'
        ]);

        // Check if reCaptcha is enabled
        if (get_setting("re_captcha_secret_key")) {
            $response = $this->is_valid_recaptcha($this->request->getPost("g-recaptcha-response"));
            if ($response !== true) {
                return json_encode(['success' => false, 'message' => lang("re_captcha_error-" . $response)]);
            }
        }

        $email = $this->request->getPost("email");
        $existing_user = $this->usersModel->is_email_exists($email);

        if ($existing_user) {
            $email_template = $this->emailTemplatesModel->get_final_template("reset_password");

            $parser_data = [
                "ACCOUNT_HOLDER_NAME" => $existing_user->first_name . " " . $existing_user->last_name,
                "SIGNATURE" => $email_template->signature,
                "LOGO_URL" => get_logo_url(),
                "SITE_URL" => base_url(),
                "RESET_PASSWORD_URL" => site_url("signin/new_password/" . encode_id($this->encryption->encrypt($existing_user->email . '|' . (time() + (24 * 60 * 60))), "reset_password"))
            ];

            $message = $this->parser->setData($parser_data)->renderString($email_template->message);

            if (send_app_mail($email, $email_template->subject, $message)) {
                return json_encode(['success' => true, 'message' => lang("reset_info_send")]);
            } else {
                return json_encode(['success' => false, 'message' => lang('error_occurred')]);
            }
        } else {
            return json_encode(["success" => false, 'message' => lang("no_acount_found_with_this_email")]);
        }
    }

    public function request_reset_password()
    {
        $view_data["form_type"] = "request_reset_password";
        return view('signin/index', $view_data);
    }

    public function new_password($key)
    {
        $valid_key = $this->is_valid_reset_password_key($key);

        if ($valid_key) {
            $email = $valid_key["email"];

            if ($this->usersModel->is_email_exists($email)) {
                $view_data = [
                    'key' => $key,
                    'form_type' => 'new_password'
                ];
                return view('signin/index', $view_data);
            }
        }

        // Show error if invalid key
        $view_data = [
            'heading' => 'Invalid Request',
            'message' => 'The key has expired or something went wrong!'
        ];
        return view("errors/html/error_general", $view_data);
    }

    public function do_reset_password()
    {
        $this->validate([
            'key' => 'required',
            'password' => 'required'
        ]);

        $key = $this->request->getPost("key");
        $password = $this->request->getPost("password");
        $valid_key = $this->is_valid_reset_password_key($key);

        if ($valid_key) {
            $email = $valid_key["email"];
            $user = $this->usersModel->is_email_exists($email);
            $user_data = ["password" => md5($password)];

            if ($user && $this->usersModel->save($user_data, $user->id)) {
                return json_encode(["success" => true, 'message' => lang("password_reset_successfully") . " " . anchor("signin", lang("signin"))]);
            }
        }

        return json_encode(["success" => false, 'message' => lang("error_occurred")]);
    }

    private function is_valid_reset_password_key($key)
    {
        if ($key) {
            $key = decode_id($key, "reset_password");
            $key = $this->encryption->decrypt($key);
            $key = explode('|', $key);

            $email = get_array_value($key, "0");
            $expire_time = get_array_value($key, "1");

            if ($email && valid_email($email) && $expire_time && $expire_time > time()) {
                return ["email" => $email];
            }
        }

        return false;
    }
}
