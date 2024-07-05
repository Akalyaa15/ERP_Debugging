<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use DateTimeZone;

class Settings extends BaseController
{
    protected $settingsModel;
    protected $gstStateCodeModel;
    protected $countriesModel;
    protected $statesModel;
    protected $usersModel;
    protected $termsConditionsTemplatesModel;
    protected $teamModel;
    protected $notificationSettingsModel;

    public function __construct()
    {
        $this->settingsModel = new \App\Models\Settings_model();
        $this->gstStateCodeModel = new \App\Models\Gst_state_code_model();
        $this->countriesModel = new \App\Models\Countries_model();
        $this->statesModel = new \App\Models\States_model();
        $this->usersModel = new \App\Models\Users_model();
        $this->termsConditionsTemplatesModel = new \App\Models\Termsconditionstemplates_model();
        $this->teamModel = new \App\Models\Team_model();
        $this->notificationSettingsModel = new \App\Models\Notificationsettings_model();
        
        parent::__construct();
        $this->access_only_admin();
    }

    public function index()
    {
        return redirect()->to('settings/general');
    }

    public function general()
    {
        $tzlist = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
        $viewData['timezone_dropdown'] = array_combine($tzlist, $tzlist);
        $viewData['language_dropdown'] = get_language_list();
        $viewData['currency_dropdown'] = get_international_currency_code_dropdown();

        return view('settings/general', $viewData);
    }

    public function save_general_settings()
    {
        $settings = [
            "site_logo", "show_background_image_in_signin_page", "show_logo_in_signin_page", "app_title", "language",
            "timezone", "date_format", "time_format", "first_day_of_week", "default_currency", "currency_symbol",
            "currency_position", "decimal_separator", "no_of_decimals", "accepted_file_formats", "rows_per_page",
            "item_purchase_code", "scrollbar", "number_of_quantity", "company_country", "favicon"
        ];

        foreach ($settings as $setting) {
            $value = $this->request->getPost($setting);
            if ($value !== null) {
                if ($setting === "site_logo" || $setting === "favicon") {
                    $value = str_replace("~", ":", $value);
                    $value = move_temp_file("site-logo.png", get_setting("system_file_path"), "", $value);
                    if (get_setting($setting)) {
                        delete_app_files(get_setting("system_file_path"), get_system_files_setting_value($setting));
                    }
                } elseif ($setting === "item_purchase_code" && $value === "******") {
                    $value = get_setting('item_purchase_code');
                }

                $this->settingsModel->save_setting($setting, $value);
            }
        }

        $file_names = $this->request->getPost('file_names');
        if ($file_names && count($file_names)) {
            move_temp_file($file_names["0"], get_setting("system_file_path"), "", NULL, "sigin-background-image.jpg");
        }

        $site_logo_file = $this->request->getFiles('site_logo_file');
        if ($site_logo_file) {
            $site_logo_file_name = $site_logo_file->getTempName();
            if ($site_logo_file_name) {
                $site_logo = move_temp_file("site-logo.png", get_setting("system_file_path"));
                $this->settingsModel->save_setting("site_logo", $site_logo);
            }
        }

        return $this->response->setJSON(['success' => true, 'message' => lang('settings_updated')]);
    }

    public function company()
    {
        $gst_code = $this->gstStateCodeModel->findAll();
        $company_gst_state_code_dropdown = [];

        foreach ($gst_code as $code) {
            $company_gst_state_code_dropdown[] = ['id' => $code->gstin_number_first_two_digits, 'text' => $code->title];
        }

        $company_setup_country = $this->countriesModel->findAll();
        $company_setup_country_dropdown = [];

        foreach ($company_setup_country as $country) {
            $company_setup_country_dropdown[] = ['id' => $country->id, 'text' => $country->countryName];
        }

        $company_state = $this->statesModel->findAll();
        $company_state_dropdown = [];

        foreach ($company_state as $state) {
            $company_state_dropdown[] = ['id' => $state->id, 'text' => $state->title];
        }

        $viewData = [
            'company_state_dropdown' => json_encode($company_state_dropdown),
            'company_setup_country_dropdown' => json_encode($company_setup_country_dropdown),
            'company_gst_state_code_dropdown' => json_encode($company_gst_state_code_dropdown),
        ];

        return view('settings/company', $viewData);
    }

    public function save_company_settings()
    {
        $settings = [
            "company_name", "company_address", "company_phone", "company_email", "company_website", "company_gst_number",
            "company_gstin_number_first_two_digits", "company_state", "company_setup_country", "company_city", "company_pincode",
            "discount_cutoff_margin"
        ];

        foreach ($settings as $setting) {
            $this->settingsModel->save_setting($setting, $this->request->getPost($setting));
        }

        return $this->response->setJSON(['success' => true, 'message' => lang('settings_updated')]);
    }

    public function email()
    {
        return view('settings/email');
    }

    public function save_email_settings()
    {
        $settings = [
            "email_sent_from_address", "email_sent_from_name", "email_protocol", "email_smtp_host", "email_smtp_port",
            "email_smtp_user", "email_smtp_pass", "email_smtp_security_type"
        ];

        foreach ($settings as $setting) {
            $value = $this->request->getPost($setting);
            if (!$value) {
                $value = "";
            }
            $this->settingsModel->save_setting($setting, $value);
        }

        $test_email_to = $this->request->getPost("send_test_mail_to");
        if ($test_email_to) {
            // Email sending logic
            // CodeIgniter 4 email library usage here
        }

        return $this->response->setJSON(['success' => true, 'message' => lang('settings_updated')]);
    }

    public function ip_restriction()
    {
        return view('settings/ip_restriction');
    }

    public function save_ip_settings()
    {
        $this->settingsModel->save_setting("allowed_ip_addresses", $this->request->getPost("allowed_ip_addresses"));

        return $this->response->setJSON(['success' => true, 'message' => lang('settings_updated')]);
    }

    public function db_backup()
    {
        return view('settings/db_backup');
    }

    public function client_permissions()
    {
        $team_members = $this->usersModel->where(['deleted' => 0, 'user_type' => 'staff'])->findAll();
        $members_dropdown = [];

        foreach ($team_members as $team_member) {
            $members_dropdown[] = ['id' => $team_member->id, 'text' => $team_member->first_name . " " . $team_member->last_name];
        }

        $hidden_menus = [
            "announcements", "events", "estimates", "invoices", "knowledge_base", "projects", "payments", "tickets"
        ];

        $hidden_menu_dropdown = [];
        foreach ($hidden_menus as $hidden_menu) {
            $hidden_menu_dropdown[] = ['id' => $hidden_menu, 'text' => lang($hidden_menu)];
        }

        $viewData = [
            'hidden_menu_dropdown' => json_encode($hidden_menu_dropdown),
            'members_dropdown' => json_encode($members_dropdown)
        ];

        return view('settings/client_permissions', $viewData);
    }

    public function save_client_settings()
    {
        $settings = [
            "disable_client_login", "disable_client_signup", "disable_partner_signup", "client_message_users",
            "hidden_client_menus", "client_can_create_projects", "client_can_create_tasks", "client_can_edit_tasks",
            "client_can_view_tasks", "client_can_comment_on_tasks", "client_can_view_project_files",
            "client_can_add_project_files", "client_can_comment_on_files", "client_can_view_milestones",
            "client_can_view_overview", "client_can_view_gantt", "disable_editing_left_menu_by_clients",
            "disable_topbar_menu_customization"
        ];

        foreach ($settings as $setting) {
            $value = $this->request->getPost($setting);
            if ($value === null) {
                $value = "";
            }
            $this->settingsModel->save_setting($setting, $value);
        }

        return $this->response->setJSON(['success' => true, 'message' => lang('settings_updated')]);
    }

    public function vendor_permissions()
    {
        $team_members = $this->usersModel->where(['deleted' => 0, 'user_type' => 'staff'])->findAll();
        $members_dropdown = [];

        foreach ($team_members as $team_member) {
            $members_dropdown[] = ['id' => $team_member->id, 'text' => $team_member->first_name . " " . $team_member->last_name];
        }

        $vendor_hidden_menus = [
            "announcements", "events", "purchase_orders", "work_orders", "knowledge_base"
        ];

        $hidden_menu_dropdown = [];
        foreach ($vendor_hidden_menus as $hidden_menu) {
            $hidden_menu_dropdown[] = ['id' => $hidden_menu, 'text' => lang($hidden_menu)];
        }

        $viewData = [
            'hidden_menu_dropdown' => json_encode($hidden_menu_dropdown),
            'members_dropdown' => json_encode($members_dropdown)
        ];

        return view('settings/vendor_permissions', $viewData);
    }
    public function save_vendor_settings()
    {
        $settings = [
            "disable_vendor_login",
            "disable_vendor_signup",
            "vendor_message_users",
            "hidden_vendor_menus",
            "disable_editing_left_menu_by_vendors",
            "disable_topbar_menu_customization_vendors"
        ];
    
        foreach ($settings as $setting) {
            $value = $this->request->getPost($setting);
            if ($value === null) {
                $value = "";
            }
    
            $this->Settings_model->save_setting($setting, $value);
        }
        return $this->response->setJSON(["success" => true, 'message' => lang('settings_updated')]);
    }
    public function invoices()
    {
        $invoice_footers = $this->Terms_conditions_templates_model->findAll();
    
        $invoice_footers_dropdown = [
            ["id" => 0, "text" => "Select a template.."]
        ];
    
        foreach ($invoice_footers as $invoice_footer) {
            $invoice_footers_dropdown[] = ["id" => $invoice_footer['id'], "text" => $invoice_footer['template_name']];
        }
    
        $view_data['invoice_footers_dropdown'] = json_encode($invoice_footers_dropdown);
    
        return view("settings/invoices", $view_data);
    }
    
    public function save_invoice_settings()
    {
        $settings = [
            "allow_partial_invoice_payment_from_clients",
            "invoice_color",
            "invoice_footer",
            "estimate_footer",
            "send_bcc_to",
            "invoice_prefix",
            "invoice_style",
            "invoice_logo",
            "send_invoice_due_pre_reminder",
            "send_invoice_due_after_reminder",
            "send_recurring_invoice_reminder_before_creation",
            "estimate_prefix"
        ];
     foreach ($settings as $setting) {
            $value = $this->request->getPost($setting);
            $saveable = true;
    
            if ($setting == "invoice_footer" || $setting == "estimate_footer") {
                $value = decode_ajax_post_data($value);
            } elseif ($setting === "invoice_logo" && $value) {
                $value = str_replace("~", ":", $value);
                $value = move_temp_file("invoice-logo.png", get_setting("system_file_path"), "", $value);
            }
    
            if ($setting === "invoice_logo" && !$value) {
                $saveable = false;
            }
    
            if ($saveable) {
                $this->Settings_model->save_setting($setting, $value);
            }
        }
        return $this->response->setJSON(["success" => true, 'message' => lang('settings_updated')]);
    }
    
    public function payslip()
    {
        $view_data['members_and_teams_dropdown'] = json_encode(get_team_members_and_teams_select2_data_list());
        
        $annual_leave_dropdown = range(1, 365);
        $view_data['annual_leave_dropdown'] = json_encode($annual_leave_dropdown);
    
        return view("settings/payslip", $view_data);
    }
    
    public function save_payslip_settings()
    {
        $settings = [
            "payslip_color",
            "payslip_footer",
            "payslip_prefix",
            "payslip_style",
            "payslip_logo",
            "maximum_no_of_casual_leave_per_month",
            "payslip_ot_status",
            "payslip_generate_date",
            "company_working_hours_for_one_day",
            "ot_permission",
            "ot_permission_specific",
            "payslip_created_status"
        ];
    
        foreach ($settings as $setting) {
            $value = $this->request->getPost($setting);
            $saveable = true;
    
            if ($setting == "payslip_footer") {
                $value = decode_ajax_post_data($value);
            } elseif ($setting === "payslip_logo" && $value) {
                $value = str_replace("~", ":", $value);
                $value = move_temp_file("payslip-logo.png", get_setting("system_file_path"), "", $value);
            }
    
            if ($setting === "payslip_logo" && !$value) {
                $saveable = false;
            }
    
            if ($saveable) {
                $this->Settings_model->save_setting($setting, $value);
            }
        }
        return $this->response->setJSON(["success" => true, 'message' => lang('settings_updated')]);
    }
    

 function student_desk_permissions() {
        
        $this->template->rander("settings/student_desk_permissions");
    }

    function save_student_desk_permissions() {
        $settings = array(
            "disable_student_desk_registration",
            
            
        );

        foreach ($settings as $setting) {
            $value = $this->input->post($setting);
            if (is_null($value)) {
                $value = "";
            }

            $this->Settings_model->save_setting($setting, $value);
        }
        echo json_encode(array("success" => true, 'message' => lang('settings_updated')));
    }

      public  function voucher() {
                return view("settings/voucher");
       }

       public function save_voucher_settings()
       {
           $settings = [
               "voucher_color",
               "voucher_footer",
               "voucher_prefix",
               "voucher_style",
               "voucher_logo"
           ];
       
           foreach ($settings as $setting) {
               $value = $this->request->getPost($setting);
               $saveable = true;
       
               if ($setting == "voucher_footer") {
                   $value = decode_ajax_post_data($value);
               } elseif ($setting === "voucher_logo" && $value) {
                   $value = str_replace("~", ":", $value);
                   $value = move_temp_file("voucher-logo.png", get_setting("system_file_path"), "", $value);
               }
       
               if ($setting === "voucher_logo" && !$value) {
                   $saveable = false;
               }
       
               if ($saveable) {
                   $this->Settings_model->save_setting($setting, $value);
               }
           }
       
           return $this->response->setJSON(["success" => true, 'message' => lang('settings_updated')]);
       }
       


 public  function delivery() {
        return view("settings/deivery");
    }

    public function save_delivery_settings()
{
    $settings = [
        "delivery_color",
        "delivery_footer",  
        "delivery_prefix", 
        "delivery_style", 
        "delivery_logo"
    ];

    foreach ($settings as $setting) {
        $value = $this->request->getPost($setting);
        $saveable = true;

        if ($setting == "delivery_footer") {
            $value = decode_ajax_post_data($value);
        } else if ($setting === "delivery_logo" && $value) {
            $value = str_replace("~", ":", $value);
            $value = move_temp_file("delivery-logo.png", get_setting("system_file_path"), "", $value);
        }

        if ($setting === "delivery_logo" && !$value) {
            $saveable = false;
        }

        if ($saveable) {
            $this->Settings_model->save_setting($setting, $value);
        }
    }

    return $this->response->setJSON(["success" => true, 'message' => lang('settings_updated')]);
}

    public function purchase_orders() {
       return view("settings/purchase_orders");
    }

    public function save_purchase_order_settings()
    {
        $settings = [
            "purchase_order_color", 
            "purchase_order_footer",  
            "purchase_order_prefix", 
            "purchase_order_style", 
            "purchase_order_logo",
            "send_purchase_order_due_pre_reminder",
            "send_purchase_order_due_after_reminder",
            "purchase_order_due_repeat"
        ];
    
        foreach ($settings as $setting) {
            $value = $this->request->getPost($setting);
            $saveable = true;
    
            if ($setting == "purchase_order_footer") {
                $value = decode_ajax_post_data($value);
            } else if ($setting === "purchase_order_logo" && $value) {
                $value = str_replace("~", ":", $value);
                $value = move_temp_file("purchase_order-logo.png", get_setting("system_file_path"), "", $value);
            }
    
            if ($setting === "purchase_order_logo" && !$value) {
                $saveable = false;
            }
    
            if ($saveable) {
                $this->Settings_model->save_setting($setting, $value);
            }
        }
    
        return $this->response->setJSON(["success" => true, 'message' => lang('settings_updated')]);
    }
    public function work_orders() {
            return view("settings/work_orders");
         }

         public function save_work_order_settings()
         {
             $settings = [
                 "work_order_color", 
                 "work_order_footer",  
                 "work_order_prefix", 
                 "work_order_style", 
                 "work_order_logo"
             ];
         
             foreach ($settings as $setting) {
                 $value = $this->request->getPost($setting);
                 $saveable = true;
         
                 if ($setting == "work_order_footer") {
                     $value = decode_ajax_post_data($value);
                 } else if ($setting === "work_order_logo" && $value) {
                     $value = str_replace("~", ":", $value);
                     $value = move_temp_file("work_order-logo.png", get_setting("system_file_path"), "", $value);
                 }
         
                 if ($setting === "work_order_logo" && !$value) {
                     $saveable = false;
                 }
         
                 if ($saveable) {
                     $this->Settings_model->save_setting($setting, $value);
                 }
             }
         
             return $this->response->setJSON(["success" => true, 'message' => lang('settings_updated')]);
         }
         public function notifications()
         {
             $category_suggestions = [
                 ["id" => "", "text" => "- " . lang('category') . " -"],
                 ["id" => "announcement", "text" => lang("announcement")],
                 ["id" => "client", "text" => lang("client")],
                 ["id" => "event", "text" => lang("event")],
                 ["id" => "estimate", "text" => lang("estimate")],
                 ["id" => "invoice", "text" => lang("invoice")],
                 ["id" => "leave", "text" => lang("leave")],
                 ["id" => "message", "text" => lang("message")],
                 ["id" => "project", "text" => lang("project")],
                 ["id" => "ticket", "text" => lang("ticket")]
             ];
         
             $data['categories_dropdown'] = json_encode($category_suggestions);
             return view("settings/notifications/index", $data);
         }
         
         public function notification_modal_form()
         {
             $id = $this->request->getPost("id");
             if ($id) {
                 helper(['notifications']);
         
                 $model_info = $this->Notification_settings_model->where('id', $id)->first();
                 $notify_to = get_notification_config($model_info['event'], "notify_to");
         
                 if (!$notify_to) {
                     $notify_to = [];
                 }
         
                 $members_dropdown = [];
                 $team_dropdown = [];
         
                 if (in_array("team_members", $notify_to)) {
                     $team_members = $this->Users_model->where(["deleted" => 0, "user_type" => "staff"])->findAll();
         
                     foreach ($team_members as $team_member) {
                         $members_dropdown[] = ["id" => $team_member['id'], "text" => $team_member['first_name'] . " " . $team_member['last_name']];
                     }
                 }
         
                 if (in_array("team", $notify_to)) {
                     $teams = $this->Team_model->where(["deleted" => 0])->findAll();
                     foreach ($teams as $team) {
                         $team_dropdown[] = ["id" => $team['id'], "text" => $team['title']];
                     }
                 }
         
                 if ($model_info['notify_to_terms']) {
                     $model_info['notify_to_terms'] = explode(",", $model_info['notify_to_terms']);
                 } else {
                     $model_info['notify_to_terms'] = [];
                 }
         
                 $data['members_dropdown'] = json_encode($members_dropdown);
                 $data['team_dropdown'] = json_encode($team_dropdown);
                 $data["notify_to"] = $notify_to;
                 $data["model_info"] = $model_info;
         
                 return view("settings/notifications/modal_form", $data);
             }
         }
         
         public function notification_settings_list_data()
         {
             $options = ["category" => $this->request->getPost("category")];
             $list_data = $this->Notification_settings_model->where($options)->findAll();
             $result = [];
             foreach ($list_data as $data) {
                 $result[] = $this->_make_notification_settings_row($data);
             }
             return $this->response->setJSON(["data" => $result]);
         }
         
         private function _make_notification_settings_row($data)
         {
             $yes = "<i class='fa fa-check-circle'></i>";
             $no = "<i class='fa fa-check-circle' style='opacity:0.2'></i>";
             $notify_to = "";
         
             if ($data['notify_to_terms']) {
                 $terms = explode(",", $data['notify_to_terms']);
                 foreach ($terms as $term) {
                     if ($term) {
                         $notify_to .= "<li>" . lang($term) . "</li>";
                     }
                 }
             }
         
             if ($data['notify_to_team_members']) {
                 $notify_to .= "<li>" . lang("team_members") . ": " . $data['team_members_list'] . "</li>";
             }
         
             if ($data['notify_to_team']) {
                 $notify_to .= "<li>" . lang("team") . ": " . $data['team_list'] . "</li>";
             }
         
             if ($notify_to) {
                 $notify_to = "<ul class='pl15'>" . $notify_to . "</ul>";
             }
         
             return [
                 $data['sort'],
                 lang($data['event']),
                 $notify_to,
                 lang($data['category']),
                 $data['enable_email'] ? $yes : $no,
                 $data['enable_web'] ? $yes : $no,
                 modal_anchor(get_uri("settings/notification_modal_form"), "<i class='fa fa-pencil'></i>", ["class" => "edit", "title" => lang('notification'), "data-post-id" => $data['id']])
             ];
         }
         
         public function save_notification_settings()
         {
             $id = $this->request->getPost("id");
         
             $data = [
                 "enable_web" => $this->request->getPost("enable_web"),
                 "enable_email" => $this->request->getPost("enable_email"),
                 "notify_to_team" => "",
                 "notify_to_team_members" => "",
                 "notify_to_terms" => "",
             ];
         
             $notify_to_terms_list = $this->Notification_settings_model->notify_to_terms();
             $notify_to_terms = "";
         
             foreach ($notify_to_terms_list as $key => $term) {
                 if ($term == "team") {
                     $data["notify_to_team"] = $this->request->getPost("team");
                 } else if ($term == "team_members") {
                     $data["notify_to_team_members"] = $this->request->getPost("team_members");
                 } else {
                     $other_term = $this->request->getPost($term);
         
                     if ($other_term) {
                         if ($notify_to_terms) {
                             $notify_to_terms .= ",";
                         }
         
                         $notify_to_terms .= $term;
                     }
                 }
             }
         
             $data["notify_to_terms"] = $notify_to_terms;
         
             $save_id = $this->Notification_settings_model->save($data, $id);
         
             if ($save_id) {
                 return $this->response->setJSON(["success" => true, "data" => $this->_notification_list_data($save_id), 'id' => $save_id, 'message' => lang('settings_updated')]);
             } else {
                 return $this->response->setJSON(["success" => false, 'message' => lang('error_occurred')]);
             }
         }
         public function modules()
         {
             return view("settings/modules");
         }
         
    public function save_module_settings() {

        $settings = ["module_timeline", "module_event", "module_todo", "module_note", "module_message", "module_chat", "module_invoice", "module_expense", "module_attendance", "module_leave", "module_estimate", "module_estimate_request", "module_ticket", "module_announcement", "module_project_timesheet", "module_help", "module_knowledge_base","module_outsource_members","module_payslip","module_delivery","module_purchase_order","module_work_order","module_voucher","module_master_data","module_production_data","module_assets_data","module_cheque_handler","module_company_bank_statement","module_student_desk","module_income","module_loan","module_state","module_country","module_company","module_branch","module_department","module_designation"];

        foreach ($settings as $setting) {
            $value = $this->request->getpost($setting);
            if (is_null($value)) {
                $value = "";
            }

            $this->Settings_model->save_setting($setting, $value);
        }
        return $this->response->setJSON(["success"=>true,'message'=>lang('settings_updated')]);
    }

    /* upload a file */

    public function upload_file() {
        upload_file_to_temp();
    }

    /* check valid file */

    public function validate_file()
    {
        return validate_post_file($this->request->getPost("file_name"));
    }
    public function cron_job()
    {
        return view("settings/cron_job");
    }
    public function integration()
    {
        return view("settings/integration/index");
    }
    public function re_captcha()
    {
        return view("settings/integration/re_captcha");
    }
    public function save_re_captcha_settings()
    {
        $settings = ["re_captcha_site_key", "re_captcha_secret_key"];
    
        foreach ($settings as $setting) {
            $value = $this->request->getPost($setting);
            if (is_null($value)) {
                $value = "";
            }
    
            $this->Settings_model->save_setting($setting, $value);
        }
    
        return $this->response->setJSON(["success" => true, 'message' => lang('settings_updated')]);
    }
    public function tickets()
    {
        return view("settings/tickets");
    }
    public function save_ticket_settings()
    {
        $settings = [
            "show_recent_ticket_comments_at_the_top", 
            "ticket_prefix", 
            "project_reference_in_tickets"
        ];
    
        foreach ($settings as $setting) {
            $value = $this->request->getPost($setting);
            if (is_null($value)) {
                $value = "";
            }
    
            $this->Settings_model->save_setting($setting, $value);
        }
    
        return $this->response->setJSON(["success" => true, 'message' => lang('settings_updated')]);
    }
    public function company_permissions()
    {
        return view("settings/company_permissions");
    }
    public function save_companypermission_settings()
    {
        $settings = [
            "disable_company_signup"
        ];
    
        foreach ($settings as $setting) {
            $value = $this->request->getPost($setting);
            if (is_null($value)) {
                $value = "";
            }
    
            $this->Settings_model->save_setting($setting, $value);
        }
    
        return $this->response->setJSON(["success" => true, 'message' => lang('settings_updated')]);
    }
}