<?php

namespace App\Controllers;

use App\Models\UsersModel;
use CodeIgniter\Controller;
use ReCaptcha\ReCaptcha;

class SignupPartner extends BaseController
{
    protected $usersModel;
    protected $partnersModel;
    protected $clientsModel;

    public function __construct()
    {
        helper('email');
        $this->usersModel = new \App\Models\UsersModel();
        $this->partnersModel = new \App\Models\PartnersModel();
        $this->clientsModel = new \App\Models\ClientsModel();
    }

    public function index()
    {
        // By default only clients can sign up directly.
        // If client login/signup is disabled, then show a 404 page.
        if (get_setting("disable_client_signup")) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException();
        }

        $viewData = [
            "type" => "client",
            "signup_type" => "new_client",
            "signup_message" => lang("create_an_account_as_a_new_client")
        ];

        return view("signup/index", $viewData);
    }

    public function partner()
    {
        // By default only clients can sign up directly.
        // If partner login/signup is disabled, then show a 404 page.
        if (get_setting("disable_partner_signup")) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException();
        }

        $viewData = [
            "type" => "client",
            "signup_type_partner" => "new_partner",
            "signup_message_partner" => lang("create_an_account_as_a_new_partner")
        ];

        return view("signup/partner_index", $viewData);
    }

    // Redirected from email
    public function accept_invitation($signup_key = "")
    {
        $validKey = $this->is_valid_key($signup_key);
        if ($validKey) {
            $email = $validKey['email'];
            $type = $validKey['type'];
            if ($this->usersModel->isEmailExists($email)) {
                $viewData = [
                    "heading" => "Account exists!",
                    "message" => lang("account_already_exists_for_your_mail") . " " . anchor("signin", lang("signin"))
                ];

                return view("errors/html/error_general", $viewData);
            }

            switch ($type) {
                case "staff":
                    $viewData["signup_message"] = lang("create_an_account_as_a_team_member");
                    break;
                case "resource":
                    $viewData["signup_message"] = lang("create_an_account_as_a_outsource_member");
                    break;
                case "client":
                    $viewData["signup_message"] = lang("create_an_account_as_a_client_contact");
                    break;
                case "partner":
                    $viewData["signup_message"] = lang("create_an_account_as_a_new_partner");
                    break;
            }

            $viewData["signup_type"] = "invitation";
            $viewData["type"] = $type;
            $viewData["signup_key"] = $signup_key;

            return view("signup/index", $viewData);
        } else {
            $viewData = [
                "heading" => "406 Not Acceptable",
                "message" => lang("invitation_expaired_message")
            ];

            return view("errors/html/error_general", $viewData);
        }
    }

    private function is_valid_key($signup_key = "")
    {
        $signup_key = decode_id($signup_key, "signup");
        $encrypter = \Config\Services::encrypter();
        $signup_key = $encrypter->decrypt($signup_key);
        $signup_key = explode('|', $signup_key);
        $type = $signup_key[0] ?? null;
        $email = $signup_key[1] ?? null;
        $expire_time = $signup_key[2] ?? null;
        $client_id = $signup_key[3] ?? null;
        if ($type && $email && valid_email($email) && $expire_time && $expire_time > time()) {
            return ["type" => $type, "email" => $email, "client_id" => $client_id];
        }
        return false;
    }

    private function is_valid_recaptcha($recaptcha_post_data)
    {
        // Load recaptcha lib
        require_once(APPPATH . "ThirdParty/recaptcha/autoload.php");
        $recaptcha = new ReCaptcha(get_setting("re_captcha_secret_key"));
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

        $validation = \Config\Services::validation();
        $validation->setRules([
            "first_name" => "required",
            "last_name" => "required",
            "password" => "required"
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON(['success' => false, 'message' => $validation->getErrors()]);
        }

        // Check if reCaptcha is enabled and validate
        if (get_setting("re_captcha_secret_key")) {
            $response = $this->is_valid_recaptcha($this->request->getPost("g-recaptcha-response"));
            if ($response !== true) {
                $message = $response ? lang("re_captcha_error-" . $response) : lang("re_captcha_expired");
                return $this->response->setJSON(['success' => false, 'message' => $message]);
            }
        }

        $user_data = [
            "first_name" => $this->request->getPost("first_name"),
            "last_name" => $this->request->getPost("last_name"),
            "job_title" => $this->request->getPost("job_title") ? $this->request->getPost("job_title") : "Untitled",
            "gender" => $this->request->getPost("gender"),
            "created_at" => date('Y-m-d H:i:s')
        ];

        $user_data = clean_data($user_data);
        $user_data["password"] = md5($this->request->getPost("password"));

        if ($signup_key) {
            // it is an invitation, validate the invitation key
            $valid_key = $this->is_valid_key($signup_key);

            if ($valid_key) {
                $email = $valid_key['email'];
                $type = $valid_key['type'];
                $client_id = $valid_key['client_id'];

                // show error message if email already exists
                if ($this->usersModel->isEmailExists($email)) {
                    return $this->response->setJSON([
                        "success" => false,
                        'message' => lang("account_already_exists_for_your_mail") . " " . anchor("signin", lang("signin"))
                    ]);
                }

                $user_data["email"] = $email;
                $user_data["user_type"] = $type;

                if ($type === "staff" || $type === "resource") {
                    // create a team member account
                    $user_id = $this->usersModel->save($user_data);
                    if ($user_id) {
                        // save team members job info
                        $job_data = [
                            "user_id" => $user_id,
                            "salary" => 0,
                            "salary_term" => 0,
                            "date_of_hire" => ""
                        ];
                        $this->usersModel->saveJobInfo($job_data);
                    }
                } else if ($type === "partner") {
                    // create a team member account
                    $client = $this->partnersModel->find($client_id);
                    $db = \Config\Database::connect();
                    $query = $db->table('clients')->select('id')->where('partner_id', $client_id)->get();
                    $result = $query->getRow();

                    if ($result) {
                        $client_db_id = $result->id;
                    }

                    if ($client && $client->deleted == 0) {
                        $user_data["partner_id"] = $client_id;
                        $user_data["user_type"] = 'client';
                        $user_data["client_id"] = $client_db_id;
                        $user_data["job_title"] = "";

                        // has any primary contact for this client? if not, make this contact as a primary contact
                        $primary_contact = $this->partnersModel->getPrimaryContact($client_id);
                        if (!$primary_contact) {
                            $user_data['is_primary_contact'] = 1;
                        }

                        // create a client contact account
                        $user_id = $this->usersModel->save($user_data);
                    } else {
                        // invalid client
                        return $this->response->setJSON(['success' => false, 'message' => lang("something_went_wrong")]);
                    }
                } else {
                    // check client id and create client contact account
                    $client = $this->clientsModel->find($client_id);
                    if ($client && $client->deleted == 0) {
                        $user_data["client_id"] = $client_id;

                        // has any primary contact for this client? if not, make this contact as a primary contact
                        $primary_contact = $this->clientsModel->getPrimaryContact($client_id);
                        if (!$primary_contact) {
                            $user_data['is_primary_contact'] = 1;
                        }

                        // create a client contact account
                        $user_id = $this->usersModel->save($user_data);
                    } else {
                        // invalid client
                        return $this->response->setJSON(['success' => false, 'message' => lang("something_went_wrong")]);
                    }
                }
            } else {
                // invalid key. show an error message
                return $this->response->setJSON(['success' => false, 'message' => lang("invitation_expaired_message")]);
            }
        } else {
            // create a client directly
            if (get_setting("disable_partner_signup")) {
                throw new \CodeIgniter\Exceptions\PageNotFoundException();
            }

            $validation->setRules([
                "email" => "required|valid_email",
                "company_name" => "required"
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON(['success' => false, 'message' => $validation->getErrors()]);
            }

            $email = $this->request->getPost("email");
            $company_name = $this->request->getPost("company_name");

            if ($this->usersModel->isEmailExists($email)) {
                return $this->response->setJSON([
                    "success" => false,
                    'message' => lang("account_already_exists_for_your_mail") . " " . anchor("signin", lang("signin"))
                ]);
            }

            $client_data = ["company_name" => $company_name];
            $client_data = clean_data($client_data);

            // check duplicate company name, if found then show an error message
            if (get_setting("disallow_duplicate_client_company_name") == "1" && $this->clientsModel->isDuplicateCompanyName($company_name)) {
                return $this->response->setJSON([
                    "success" => false,
                    'message' => lang("account_already_exists_for_your_company_name") . " " . anchor("signin", lang("signin"))
                ]);
            }

            // create a client
            $client_id = $this->partnersModel->save($client_data);
            $client_id = $this->clientsModel->save($client_data);

            $db = \Config\Database::connect();

            $query1 = $db->table('partners')->select('id')->orderBy('id', 'desc')->limit(1)->get();
            $result1 = $query1->getRow();
            $partner_id = $result1->id ?? null;

            $query2 = $db->table('clients')->select('id')->orderBy('id', 'desc')->limit(1)->get();
            $result2 = $query2->getRow();
            $client_id = $result2->id ?? null;

            $db->table('clients')->where('id', $client_id)->update(['partner_id' => $partner_id]);
            $db->table('partners')->where('id', $partner_id)->update(['client_id' => $client_id]);

            if ($client_id) {
                // client created, now create the client contact
                $user_data["user_type"] = "client";
                $user_data["email"] = $email;
                $user_data["client_id"] = $client_id;
                $user_data["is_primary_contact"] = 1;
                $user_id = $this->usersModel->save($user_data);

                $query3 = $db->table('users')->select('id')->orderBy('id', 'desc')->limit(1)->get();
                $result3 = $query3->getRow();
                $user_id = $result3->id ?? null;

                $query4 = $db->table('partners')->select('id')->orderBy('id', 'desc')->limit(1)->get();
                $result4 = $query4->getRow();
                $partner_id = $result4->id ?? null;

                $db->table('users')->where('id', $user_id)->update(['partner_id' => $partner_id]);

                log_notification("client_signup", ["client_id" => $client_id], $user_id);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => lang('error_occurred')
                ])->setStatusCode(400);
            }
            
            if ($user_id) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => lang('account_created') . " " . anchor('signin', lang('signin'))
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => lang('error_occurred')
                ])->setStatusCode(400);
            }
        }
    }
}