<?php

namespace App\Controllers;

use App\Models\CustomFieldsModel;
use App\Models\ClientsModel;
use App\Models\CountriesModel;
use App\Models\StatesModel;
use App\Models\PartnerGroupsModel;
use App\Models\ClientGroupsModel;
use App\Models\BuyerTypesModel;
use App\Models\GstStateCodeModel;
use App\Models\GeneralFilesModel;
use App\Models\UsersModel;
use App\Models\SocialLinksModel;
use App\Models\SettingsModel;
use App\Models\KycInfoModel;
use App\Models\EmailTemplatesModel;

class Clients extends BaseController
{
    protected $customFieldsModel;
    protected $clientsModel;
    protected $countriesModel;
    protected $statesModel;
    protected $partnerGroupsModel;
    protected $clientGroupsModel;
    protected $buyerTypesModel;
    protected $gstStateCodeModel;
    protected $generalFilesModel;
    protected $usersModel;
    protected $socialLinksModel;
    protected $settingsModel;
    protected $kycInfoModel;
    protected $emailTemplatesModel;

    public function __construct()
    {
        $this->customFieldsModel = new CustomFieldsModel();
        $this->clientsModel = new ClientsModel();
        $this->countriesModel = new CountriesModel();
        $this->statesModel = new StatesModel();
        $this->partnerGroupsModel = new PartnerGroupsModel();
        $this->clientGroupsModel = new ClientGroupsModel();
        $this->buyerTypesModel = new BuyerTypesModel();
        $this->gstStateCodeModel = new GstStateCodeModel();
        $this->generalFilesModel = new GeneralFilesModel();
        $this->usersModel = new UsersModel();
        $this->socialLinksModel = new SocialLinksModel();
        $this->settingsModel = new SettingsModel();
        $this->kycInfoModel = new KycInfoModel();
        $this->emailTemplatesModel = new EmailTemplatesModel();
    }

    public function index()
    {
        $this->access_only_allowed_members();

        $access_info = $this->get_access_info("invoice");
        $view_data["show_invoice_info"] = (get_setting("module_invoice") && $access_info->access_type == "all") ? true : false;
        $view_data["custom_field_headers"] = $this->customFieldsModel->get_custom_field_headers_for_table("clients", $this->login_user->is_admin, $this->login_user->user_type);

        $view_data['groups_dropdown'] = json_encode($this->_get_groups_dropdown_select2_data(true));

        return view("clients/index", $view_data);
    }

    public function modal_form()
    {
        $this->access_only_allowed_members();

        $client_id = $this->request->getPost('id');
        $this->validate(['id' => 'numeric']);

        $view_data['label_column'] = "col-md-3";
        $view_data['field_column'] = "col-md-9";

        $view_data["view"] = $this->request->getPost('view');
        $view_data['model_info'] = $this->clientsModel->find($client_id);
        $view_data["currency_dropdown"] = $this->_get_currency_dropdown_select2_data();
        $view_data['gst_code_dropdown'] = $this->_get_gst_code_dropdown_select2_data();

        $view_data['groups_dropdown'] = $this->_get_groups_dropdown_select2_data();

        $country_get_code = $this->countriesModel->find($view_data['model_info']->country);
        $state_categories = $this->statesModel->get_dropdown_list(array("title"), "id", array("country_code" => $country_get_code->numberCode));
        
        $state_categories_suggestion = array(array("id" => "", "text" => "-"));
        foreach ($state_categories as $key => $value) {
            $state_categories_suggestion[] = array("id" => $key, "text" => $value);
        }

        $view_data['state_dropdown'] = $state_categories_suggestion;
        $view_data['buyer_types_dropdown'] = $this->_get_buyer_types_dropdown_select2_data();

        $view_data["custom_fields"] = $this->customFieldsModel->get_combined_details("clients", $client_id, $this->login_user->is_admin, $this->login_user->user_type)->getResult();

        return view('clients/modal_form', $view_data);
    }

    private function _get_state_dropdown_select2_data($show_header = false)
    {
        $states = $this->statesModel->findAll();
        $state_dropdown = [];

        foreach ($states as $code) {
            $state_dropdown[] = ["id" => $code->id, "text" => $code->title];
        }

        return $state_dropdown;
    }

    private function _get_groups_dropdown_select2_data($show_header = false)
    {
        $client_id = $this->request->getPost('id');
        $db = \Config\Database::connect();
        $query = $db->table('clients')
            ->select('partner_id')
            ->where('id', $client_id)
            ->where('partner_id >', 0)
            ->where('deleted', 0)
            ->get();

        $client_groups = [];
        if ($query->getNumRows() > 0) {
            $client_groups = $this->partnerGroupsModel->where(['deleted' => 0, 'status' => 'active'])->findAll();
        } else {
            $client_groups = $this->clientGroupsModel->where(['deleted' => 0, 'status' => 'active'])->findAll();
        }

        $groups_dropdown = [];
        if ($show_header) {
            $groups_dropdown[] = ["id" => "", "text" => "- " . lang("client_groups") . " -"];
        }

        foreach ($client_groups as $group) {
            $groups_dropdown[] = ["id" => $group->id, "text" => $group->title];
        }

        return $groups_dropdown;
    }

    private function _get_buyer_types_dropdown_select2_data($show_header = false)
    {
        $buyer_types = $this->buyerTypesModel->where(['deleted' => 0, 'status' => 'active'])->findAll();
        $buyer_types_dropdown = [];

        foreach ($buyer_types as $buyer_type) {
            $buyer_types_dropdown[] = ["id" => $buyer_type->id, "text" => $buyer_type->buyer_type];
        }

        return $buyer_types_dropdown;
    }

    private function _get_currency_dropdown_select2_data()
    {
        $currency = [["id" => "", "text" => "-"]];
        foreach (get_international_currency_code_dropdown() as $value) {
            $currency[] = ["id" => $value, "text" => $value];
        }
        return $currency;
    }

    private function _get_gst_code_dropdown_select2_data($show_header = false)
    {
        $gst_code = $this->gstStateCodeModel->findAll();
        $gst_code_dropdown = [];

        foreach ($gst_code as $code) {
            $gst_code_dropdown[] = ["id" => $code->gstin_number_first_two_digits, "text" => $code->title];
        }

        return $gst_code_dropdown;
    }

    public function save()
    {
        $client_id = $this->request->getPost('id');
        $this->access_only_allowed_members_or_client_contact($client_id);

        $this->validate([
            "id" => "numeric",
            "company_name" => "required"
        ]);

        $company_name = $this->request->getPost('company_name');
        $client_logo = $this->request->getPost('site_logo');
        $target_path = FCPATH . "/" . get_general_file_path("client", $client_id);
        $value = move_temp_file("clients-logo.png", $target_path, "", $client_logo);

        $data = [
            "company_name" => $company_name,
            "address" => $this->request->getPost('address'),
            "city" => $this->request->getPost('city'),
            "state" => $this->request->getPost('state'),
            "zip" => $this->request->getPost('zip'),
            "country" => $this->request->getPost('country'),
            "phone" => $this->request->getPost('phone'),
            "website" => $this->request->getPost('website'),
            "gst_number" => $this->request->getPost('gst_number'),
            "gstin_number_first_two_digits" => $this->request->getPost('gstin_number_first_two_digits'),
            "currency_symbol" => $this->request->getPost('currency_symbol'),
            "currency" => $this->request->getPost('currency'),
            "buyer_type" => $this->request->getPost('buyer_type'),
            "enable_client_logo" => $this->request->getPost('enable_client_logo'),
            "state_mandatory" => $this->request->getPost('state_mandatory'),
        ];

        $client_info_logo = $this->clientsModel->find($client_id);
        $client_logo_file = $client_info_logo->client_logo;

        if ($client_logo && !$client_logo_file) {
            $data["client_logo"] = $value;
        } else if ($client_logo && $client_logo_file) {
            delete_file_from_directory(get_general_file_path("client", $client_id) . $client_logo_file);
            $data["client_logo"] = $value;
        }

        if ($this->login_user->user_type === "staff") {
            $data["group_ids"] = $this->request->getPost('group_ids') ? $this->request->getPost('group_ids') : "";
        }

        if (!$client_id) {
            $data["created_date"] = get_current_utc_time();
        }

        if ($this->login_user->is_admin) {
            $data["currency_symbol"] = $this->request->getPost('currency_symbol') ? $this->request->getPost('currency_symbol') : "";
            $data["currency"] = $this->request->getPost('currency') ? $this->request->getPost('currency') : "";
            $data["disable_online_payment"] = $this->request->getPost('disable_online_payment') ? $this->request->getPost('disable_online_payment') : 0;
        }

        $data = clean_data($data);

        if ($this->clientsModel->is_duplicate_company_name($data["company_name"], $client_id)) {
            return $this->response->setJSON(["success" => false, 'message' => lang("account_already_exists_for_your_company_name")]);
        }

        $save_id = $this->clientsModel->save($data, $client_id);

        if ($save_id) {
            $db = \Config\Database::connect();
            $db->table('partners')
                ->where('client_id', $client_id)
                ->update([
                    "company_name" => $company_name,
                    "address" => $this->request->getPost('address'),
                    "city" => $this->request->getPost('city'),
                    "state" => $this->request->getPost('state'),
                    "zip" => $this->request->getPost('zip'),
                    "country" => $this->request->getPost('country'),
                    "phone" => $this->request->getPost('phone'),
                    "website" => $this->request->getPost('website'),
                    "gst_number" => $this->request->getPost('gst_number'),
                    "gstin_number_first_two_digits" => $this->request->getPost('gstin_number_first_two_digits'),
                    "currency_symbol" => $this->request->getPost('currency_symbol') ? $this->request->getPost('currency_symbol') : "",
                    "currency" => $this->request->getPost('currency') ? $this->request->getPost('currency') : "",
                    "group_ids" => $this->request->getPost('group_ids') ? $this->request->getPost('group_ids') : "",
                    "state_mandatory" => $this->request->getPost('state_mandatory') ? $this->request->getPost('state_mandatory') : "",
                    "disable_online_payment" => $this->request->getPost('disable_online_payment') ? $this->request->getPost('disable_online_payment') : 0
                ]);
        }

        if ($save_id) {
            save_custom_fields("clients", $save_id, $this->login_user->is_admin, $this->login_user->user_type);

            return $this->response->setJSON(["success" => true, "data" => $this->_row_data($save_id), 'id' => $save_id, 'view' => $this->request->getPost('view'), 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(["success" => false, 'message' => lang('error_occurred')]);
        }
    }
    public function delete()
    {
        $this->access_only_allowed_members();

        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'required|numeric'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON(['success' => false, 'message' => $validation->getErrors()]);
        }

        $id = $this->request->getPost('id');

        if ($this->clientsModel->delete_client_and_sub_items($id)) {
            return $this->response->setJSON(['success' => true, 'message' => lang('record_deleted')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
        }
    }

    /* list of clients, prepared for datatable  */

    public function list_data()
    {
        $this->access_only_allowed_members();
        $custom_fields = $this->customFieldsModel->get_available_fields_for_table("clients", $this->session->get('is_admin'), $this->session->get('user_type'));
        $options = [
            "custom_fields" => $custom_fields,
            "group_id" => $this->request->getPost("group_id")
        ];
        $list_data = $this->clientsModel->get_details($options)->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data, $custom_fields);
        }
        return $this->response->setJSON(['data' => $result]);
    }
    /* return a row of client list  table */

    private function _row_data($id)
    {
        $custom_fields = $this->customFieldsModel->get_available_fields_for_table("clients", $this->session->get('is_admin'), $this->session->get('user_type'));
        $options = [
            "id" => $id,
            "custom_fields" => $custom_fields
        ];
        $data = $this->clientsModel->get_details($options)->getRow();
        return $this->_make_row($data, $custom_fields);
    }

    /* prepare a row of client list table */
    private function _make_row($data, $custom_fields)
    {
        $image_url = get_avatar($data->contact_avatar);
        $contact = "<span class='avatar avatar-xs mr10'><img src='$image_url' alt='...'></span> $data->primary_contact";
        $primary_contact = get_client_contact_profile_link($data->primary_contact_id, $contact);

        $group_list = "";
        if ($data->groups) {
            $groups = explode(",", $data->groups);
            foreach ($groups as $group) {
                if ($group) {
                    $group_list .= "<li>" . $group . '&nbsp&nbsp&nbsp' . "</li>";
                }
            }
        }

        if ($group_list) {
            $group_list = "<ul class='pl15'>" . $group_list . "</ul>";
        }

        $due = 0;
        if ($data->invoice_value) {
            $due = ignor_minor_value($data->invoice_value - $data->payment_received);
        }

        $row_data = [
            $data->id,
            anchor(get_uri("clients/view/" . $data->id), $data->company_name),
            $data->primary_contact ? $primary_contact : "",
            $group_list,
            to_decimal_format($data->total_projects),
            to_currency($data->invoice_value, $data->currency_symbol),
            to_currency($data->payment_received, $data->currency_symbol),
            to_currency($due, $data->currency_symbol)
        ];

        foreach ($custom_fields as $field) {
            $cf_id = "cfv_" . $field->id;
            $row_data[] = view("custom_fields/output_" . $field->field_type, ["value" => $data->$cf_id]);
        }

        $row_data[] = modal_anchor(get_uri("clients/modal_form"), "<i class='fa fa-pencil'></i>", ["class" => "edit", "title" => lang('edit_client'), "data-post-id" => $data->id])
            . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_client'), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("clients/delete"), "data-action" => "delete-confirmation"]);

        return $row_data;
    }
    public function view($client_id = 0, $tab = "")
    {
        $this->access_only_allowed_members();

        if ($client_id) {
            $options = ["id" => $client_id];
            $client_info = $this->clientsModel->get_details($options)->getRow();
            if ($client_info) {

                $access_info = $this->get_access_info("invoice");
                $view_data["show_invoice_info"] = (get_setting("module_invoice") && $access_info->access_type == "all") ? true : false;

                $access_info = $this->get_access_info("estimate");
                $view_data["show_estimate_info"] = (get_setting("module_estimate") && $access_info->access_type == "all") ? true : false;

                $access_info = $this->get_access_info("estimate_request");
                $view_data["show_estimate_request_info"] = (get_setting("module_estimate_request") && $access_info->access_type == "all") ? true : false;

                $access_info = $this->get_access_info("ticket");
                $view_data["show_ticket_info"] = (get_setting("module_ticket") && $access_info->access_type == "all") ? true : false;

                $view_data["show_note_info"] = (get_setting("module_note")) ? true : false;
                $view_data["show_event_info"] = (get_setting("module_event")) ? true : false;

                $view_data['client_info'] = $client_info;

                $view_data["is_starred"] = strpos($client_info->starred_by, ":" . $this->session->get('id') . ":") ? true : false;

                $view_data["tab"] = $tab;

                // even it's hidden, admin can view all information of client
                $view_data['hidden_menu'] = [""];

                return view("clients/view", $view_data);
            } else {
                throw new \CodeIgniter\Exceptions\PageNotFoundException('Client Not Found');
            }
        } else {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Client Not Found');
        }
    }

    /* add-remove start mark from client */

    public function add_remove_star($client_id, $type = "add")
    {
        if ($client_id) {
            $view_data["client_id"] = $client_id;

            if ($type === "add") {
                $this->clientsModel->add_remove_star($client_id, $this->session->get('id'), "add");
                return view('clients/star/starred', $view_data);
            } else {
                $this->clientsModel->add_remove_star($client_id, $this->session->get('id'), "remove");
                return view('clients/star/not_starred', $view_data);
            }
        }
    }

    
    public function show_my_starred_clients()
    {
        $view_data["clients"] = $this->clientsModel->get_starred_clients($this->session->get('id'))->getResult();
        return view('clients/star/clients_list', $view_data);
    }

    /* load projects tab  */

      public function projects($client_id)
    {
        $this->access_only_allowed_members();

        $view_data['can_create_projects'] = $this->can_create_projects();
        $view_data["custom_field_headers"] = $this->customFieldsModel->get_custom_field_headers_for_table("projects", $this->session->get('is_admin'), $this->session->get('user_type'));

        $view_data['client_id'] = $client_id;
        return view("clients/projects/index", $view_data);
    }
    /* load payments tab  */
    public function payments($client_id)
    {
        $this->access_only_allowed_members();

        if ($client_id) {
            $view_data["client_info"] = $this->clientsModel->get_one($client_id);
            $view_data['client_id'] = $client_id;
            return view("clients/payments/index", $view_data);
        }
    }

    /* load tickets tab  */

    public function tickets($client_id)
{
    $this->access_only_allowed_members();

    if ($client_id) {
        $data['client_id'] = $client_id;
        $data['custom_field_headers'] = $this->Custom_fields_model->get_custom_field_headers_for_table("tickets", $this->login_user->is_admin, $this->login_user->user_type);
        $data['show_project_reference'] = get_setting('project_reference_in_tickets');

        return view("clients/tickets/index", $data);
    }
}

    /* load invoices tab  */

    public function invoices($client_id)
    {
        $this->access_only_allowed_members();
    
        if ($client_id) {
            $data['client_info'] = $this->Clients_model->get_one($client_id);
            $data['client_id'] = $client_id;
            $data['custom_field_headers'] = $this->Custom_fields_model->get_custom_field_headers_for_table("invoices", $this->login_user->is_admin, $this->login_user->user_type);
    
            return view("clients/invoices/index", $data);
        }
    }
    

    /* load estimates tab  */
    public function estimates($client_id)
    {
        $this->access_only_allowed_members();
    
        if ($client_id) {
            $data['client_info'] = $this->Clients_model->get_one($client_id);
            $data['client_id'] = $client_id;
            $data['custom_field_headers'] = $this->Custom_fields_model->get_custom_field_headers_for_table("estimates", $this->login_user->is_admin, $this->login_user->user_type);
    
            return view("clients/estimates/estimates", $data);
        }
    }
    
    /* load estimate requests tab  */

    public function estimate_requests($client_id)
    {
        $this->access_only_allowed_members();
    
        if ($client_id) {
            $data['client_id'] = $client_id;
    
            return view("clients/estimates/estimate_requests", $data);
        }
    }
    

    /* load notes tab  */
    public function notes($client_id)
    {
        $this->access_only_allowed_members();
    
        if ($client_id) {
            $data['client_id'] = $client_id;
    
            return view("clients/notes/index", $data);
        }
    }
    

    /* load events tab  */

    public function events($client_id)
    {
        $this->access_only_allowed_members();
    
        if ($client_id) {
            $data['client_id'] = $client_id;
    
            return view("events/index", $data);
        }
    }
    

    /* load files tab */

    public function files($client_id)
{
    $this->access_only_allowed_members();

    $options = ["client_id" => $client_id];
    $data['files'] = $this->General_files_model->get_details($options)->getResult();
    $data['client_id'] = $client_id;

    return view("clients/files/index", $data);
}


    /* file upload modal */

    public function file_modal_form()
    {
        $data['model_info'] = $this->General_files_model->get_one($this->request->getPost('id'));
        $client_id = $this->request->getPost('client_id') ?? $data['model_info']->client_id;
    
        $this->access_only_allowed_members();
    
        $data['client_id'] = $client_id;
    
        return view('clients/files/modal_form', $data);
    }
    

    /* save file data and move temp file to parmanent file directory */

    public function save_file()
{
    helper(['form', 'url']);
    $rules = [
        'client_id' => 'required|numeric'
    ];

    if (!$this->validate($rules)) {
        return json_encode(['success' => false, 'message' => lang('error_occurred')]);
    }

    $client_id = $this->request->getPost('client_id');
    $this->access_only_allowed_members();

    $files = $this->request->getPost("files");
    $success = false;
    $now = date('Y-m-d H:i:s');

    $target_path = WRITEPATH . "uploads/";

    // Process uploaded files
    if ($files && count($files) > 0) {
        foreach ($files as $file) {
            $file_name = $this->request->getPost('file_name_' . $file);
            $new_file_name = move_temp_file($file_name, $target_path);

            if ($new_file_name) {
                $data = [
                    "client_id" => $client_id,
                    "file_name" => $new_file_name,
                    "description" => $this->request->getPost('description_' . $file),
                    "file_size" => $this->request->getPost('file_size_' . $file),
                    "created_at" => $now,
                    "uploaded_by" => $this->login_user->id
                ];

                $success = $this->General_files_model->save($data);
            } else {
                $success = false;
            }
        }
    }

    if ($success) {
        return json_encode(['success' => true, 'message' => lang('record_saved')]);
    } else {
        return json_encode(['success' => false, 'message' => lang('error_occurred')]);
    }
}


public function files_list_data($client_id = 0)
{
    $this->access_only_allowed_members();

    $options = ["client_id" => $client_id];
    $list_data = $this->General_files_model->get_details($options)->getResult();
    $result = [];

    foreach ($list_data as $data) {
        $result[] = $this->_make_file_row($data);
    }

    return json_encode(['data' => $result]);
}

private function _make_file_row($data)
{
    $file_icon = get_file_icon(strtolower(pathinfo($data->file_name, PATHINFO_EXTENSION)));

    $image_url = get_avatar($data->uploaded_by_user_image);
    $uploaded_by = "<span class='avatar avatar-xs mr10'><img src='$image_url' alt='...'></span> $data->uploaded_by_user_name";
    $uploaded_by = get_team_member_profile_link($data->uploaded_by, $uploaded_by);

    $description = "<div class='pull-left'>" . js_anchor(remove_file_prefix($data->file_name), ['title' => "", 'data-toggle' => "app-modal", 'data-sidebar' => "0", 'data-url' => site_url("clients/view_file/{$data->id}")]);

    if ($data->description) {
        $description .= "<br /><span>{$data->description}</span></div>";
    } else {
        $description .= "</div>";
    }

    $options = anchor(site_url("clients/download_file/{$data->id}"), "<i class='fa fa fa-cloud-download'></i>", ['title' => lang('download')]);
    $options .= js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_file'), 'class' => "delete", 'data-id' => $data->id, 'data-action-url' => site_url("clients/delete_file"), 'data-action' => "delete-confirmation"]);

    return [
        $data->id,
        "<div class='fa fa-$file_icon font-22 mr10 pull-left'></div>" . $description,
        convert_file_size($data->file_size),
        $uploaded_by,
        format_to_datetime($data->created_at),
        $options
    ];
}


private function _make_file_row($data)
{
    $file_icon = get_file_icon(strtolower(pathinfo($data->file_name, PATHINFO_EXTENSION)));

    $image_url = get_avatar($data->uploaded_by_user_image);
    $uploaded_by = "<span class='avatar avatar-xs mr10'><img src='$image_url' alt='...'></span> $data->uploaded_by_user_name";
    $uploaded_by = get_team_member_profile_link($data->uploaded_by, $uploaded_by);

    $description = "<div class='pull-left'>" . js_anchor(remove_file_prefix($data->file_name), ['title' => "", 'data-toggle' => "app-modal", 'data-sidebar' => "0", 'data-url' => site_url("clients/view_file/{$data->id}")]);

    if ($data->description) {
        $description .= "<br /><span>{$data->description}</span></div>";
    } else {
        $description .= "</div>";
    }

    $options = anchor(site_url("clients/download_file/{$data->id}"), "<i class='fa fa fa-cloud-download'></i>", ['title' => lang('download')]);
    $options .= js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_file'), 'class' => "delete", 'data-id' => $data->id, 'data-action-url' => site_url("clients/delete_file"), 'data-action' => "delete-confirmation"]);

    return [
        $data->id,
        "<div class='fa fa-$file_icon font-22 mr10 pull-left'></div>" . $description,
        convert_file_size($data->file_size),
        $uploaded_by,
        format_to_datetime($data->created_at),
        $options
    ];
}
public function view_file($file_id = 0)
{
    $file_info = $this->General_files_model->get_details(["id" => $file_id])->getRow();

    if ($file_info) {
        $this->access_only_allowed_members();

        if (!$file_info->client_id) {
            return redirect()->to("forbidden");
        }

        $data['can_comment_on_files'] = false;
        $data['file_url'] = base_url(get_general_file_path("client", $file_info->client_id) . $file_info->file_name);
        $data['is_image_file'] = is_image_file($file_info->file_name);
        $data['is_google_preview_available'] = is_google_preview_available($file_info->file_name);
        $data['file_info'] = $file_info;
        $data['file_id'] = $file_id;

        return view("clients/files/view", $data);
    } else {
        return show_404();
    }
} 
/* download a file */
public function download_file($id)
{
    $file_info = $this->General_files_model->get_one($id);

    if (!$file_info->client_id) {
        return redirect()->to("forbidden");
    }

    $file_data = serialize([['file_name' => $file_info->file_name]]);
    download_app_files(get_general_file_path("client", $file_info->client_id), $file_data);
}

 /* upload a post file */
 public function upload_file()
 {
     upload_file_to_temp();
 }
 

    /* check valid file for client */
    public function validate_file()
    {
        return validate_post_file($this->request->getPost("file_name"));
    }
    

    /* delete a file */
    public function delete_file()
    {
        $id = $this->request->getPost('id');
        $info = $this->General_files_model->get_one($id);
    
        if (!$info->client_id) {
            return redirect()->to("forbidden");
        }
    
        if ($this->General_files_model->delete($id)) {
            delete_file_from_directory(get_general_file_path("client", $info->client_id) . $info->file_name);
    
            return json_encode(['success' => true, 'message' => lang('record_deleted')]);
        } else {
            return json_encode(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
        }
    }
    

    public function contact_profile($contact_id = 0, $tab = "")
{
    $this->access_only_allowed_members_or_contact_personally($contact_id);

    $data['user_info'] = $this->Users_model->get_one($contact_id);
    $data['client_info'] = $this->Clients_model->get_one($data['user_info']->client_id);
    $data['tab'] = $tab;

    if ($data['user_info']->user_type === "client") {
        $data['show_contact_info'] = true;
        $data['show_social_links'] = true;
        $data['social_link'] = $this->Social_links_model->get_one($contact_id);

        return view("clients/contacts/view", $data);
    } else {
        return show_404();
    }
}
    //show account settings of a user
    public function account_settings($contact_id)
    {
        $this->access_only_allowed_members_or_contact_personally($contact_id);
    
        $data['user_info'] = $this->Users_model->get_one($contact_id);
    
        return view("users/account_settings", $data);
    }
    
    //show my preference settings of a team member
    public function my_preferences()
    {
        $data["user_info"] = $this->Users_model->get_one($this->login_user->id);
        $data['language_dropdown'] = [];
    
        if (!get_setting("disable_language_selector_for_clients")) {
            $data['language_dropdown'] = get_language_list();
        }
    
        $data["hidden_topbar_menus_dropdown"] = $this->get_hidden_topbar_menus_dropdown();
    
        return view("clients/contacts/my_preferences", $data);
    }
    
    public function save_my_preferences()
    {
        $settings = ["notification_sound_volume", "disable_keyboard_shortcuts"];
    
        if (!get_setting("disable_language_selector_for_clients")) {
            $settings[] = "personal_language";
        }
    
        if (!get_setting("disable_topbar_menu_customization")) {
            $settings[] = "hidden_topbar_menus";
        }
    
        foreach ($settings as $setting) {
            $value = $this->request->getPost($setting);
    
            if (is_null($value)) {
                $value = "";
            }
    
            $this->Settings_model->save_setting("user_" . $this->login_user->id . "_" . $setting, $value, "user");
        }
    
        $user_data = [
            "enable_web_notification" => $this->request->getPost("enable_web_notification"),
            "enable_email_notification" => $this->request->getPost("enable_email_notification"),
        ];
    
        $user_data = clean_data($user_data);
        $this->Users_model->save($user_data, $this->login_user->id);
    
        return json_encode(['success' => true, 'message' => lang('settings_updated')]);
    }
    
    public function save_bank_info($client_id)
    {
        $this->access_only_allowed_members_or_client_contact($client_id);
    
        $data = [
            "cin" => $this->request->getPost('cin'),
            "tan" => $this->request->getPost('tan'),
            "uam" => $this->request->getPost('uam'),
            "panno" => $this->request->getPost('panno'),
            "iec" => $this->request->getPost('iec'),
            "name" => $this->request->getPost('name'),
            "accountnumber" => $this->request->getPost('accountnumber'),
            "swift_code" => $this->request->getPost('swift_code'),
            "bankname" => $this->request->getPost('bankname'),
            "branch" => $this->request->getPost('branch'),
            "ifsc" => $this->request->getPost('ifsc'),
            "micr" => $this->request->getPost('micr')
        ];
    
        $data = clean_data($data);
    
        if ($this->Clients_model->save($data, $client_id)) {
            return $this->response->setJSON(['success' => true, 'message' => lang('record_updated')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }    

    /* load contact's social links tab view */

    public function contact_social_links_tab($contact_id = 0)
    {
        if ($contact_id) {
            $this->access_only_allowed_members_or_contact_personally($contact_id);
    
            $data['user_id'] = $contact_id;
            $data['user_type'] = "client";
            $data['model_info'] = $this->Social_links_model->getOne($contact_id); // Assuming method name change to getOne
            return view('users/social_links', $data);
        }
    }
    
    public function contact_kyc_info_tab($contact_id = 0)
    {
        if ($contact_id) {
            $this->access_only_allowed_members_or_contact_personally($contact_id);
    
            $data['user_id'] = $contact_id;
            $data['user_type'] = "client";
            $data['model_info'] = $this->Kyc_info_model->getOne($contact_id); // Assuming method name change to getOne
            return view('users/kyc_info', $data);
        }
    }
    
    /* insert/upadate a contact */

    public function save_contact()
    {
        $contact_id = $this->request->getPost('contact_id');
        $client_id = $this->request->getPost('client_id');
    
        $this->access_only_allowed_members_or_contact_personally($contact_id);
    
        $data = [
            "first_name" => $this->request->getPost('first_name'),
            "last_name" => $this->request->getPost('last_name'),
            "phone" => $this->request->getPost('phone'),
            "alternative_phone" => $this->request->getPost('alternative_phone'),
            "skype" => $this->request->getPost('skype'),
            "job_title" => $this->request->getPost('job_title'),
            "gender" => $this->request->getPost('gender'),
            "note" => $this->request->getPost('note')
        ];
    
        $validationRules = [
            "first_name" => "required",
            "last_name" => "required",
            "client_id" => "required|numeric"
        ];
    
        if (!$contact_id) {
            // Additional validation rules for new contact
            $validationRules["email"] = "required|valid_email";
    
            // Set fields for new contact
            $data["client_id"] = $client_id;
            $data["email"] = trim($this->request->getPost('email'));
            $data["password"] = md5($this->request->getPost('login_password'));
            $data["created_at"] = date('Y-m-d H:i:s'); // Use appropriate datetime function
        }
    
        validate($data, $validationRules);
    
        // Check for duplicate email
        if (!$contact_id && $this->Users_model->isEmailExists($data["email"])) { // Assuming method name change to isEmailExists
            return $this->response->setJSON(['success' => false, 'message' => lang('duplicate_email')]);
        }
    
        // Handle primary contact logic
        $primary_contact = $this->Clients_model->getPrimaryContact($client_id);
        if (!$primary_contact) {
            $data['is_primary_contact'] = 1;
        }
    
        $is_primary_contact = (bool) $this->request->getPost('is_primary_contact');
        if ($is_primary_contact && $this->auth->isAdmin()) {
            $data['is_primary_contact'] = 1;
        }
    
        $data = clean_data($data);
    
        $saveId = $this->Users_model->save($data, $contact_id);
        if ($saveId) {
            // Save custom fields and handle primary contact change if needed
    
            // Send login details if creating a new contact
            if (!$contact_id && $this->request->getPost('email_login_details')) {
                // Send email logic
            }
    
            return $this->response->setJSON(['success' => true, 'data' => $this->_contact_row_data($saveId), 'id' => $contact_id, 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }
    
    //save social links of a contact
    public function save_contact_social_links($contact_id = 0)
{
    $this->access_only_allowed_members_or_contact_personally($contact_id);

    $social_link_data = [
        "facebook" => $this->request->getPost('facebook'),
        "twitter" => $this->request->getPost('twitter'),
        "linkedin" => $this->request->getPost('linkedin'),
        "googleplus" => $this->request->getPost('googleplus'),
        "digg" => $this->request->getPost('digg'),
        "youtube" => $this->request->getPost('youtube'),
        "pinterest" => $this->request->getPost('pinterest'),
        "instagram" => $this->request->getPost('instagram'),
        "github" => $this->request->getPost('github'),
        "tumblr" => $this->request->getPost('tumblr'),
        "vine" => $this->request->getPost('vine'),
        "user_id" => $contact_id
    ];

    $social_link_data = clean_data($social_link_data);

    $this->Social_links_model->save($social_link_data, $contact_id); // Assuming method name change to save
    return $this->response->setJSON(['success' => true, 'message' => lang('record_updated')]);
}

public function save_kyc_info($contact_id = 0)
{
    $this->access_only_allowed_members_or_contact_personally($contact_id);

    $kyc_info_data = [
        "aadhar_no" => $this->request->getPost('aadhar_no'),
        "passportno" => $this->request->getPost('passportno'),
        "drivinglicenseno" => $this->request->getPost('drivinglicenseno'),
        "panno" => $this->request->getPost('panno'),
        "voterid" => $this->request->getPost('voterid'),
        "name" => $this->request->getPost('name'),
        "accountnumber" => $this->request->getPost('accountnumber'),
        "bankname" => $this->request->getPost('bankname'),
        "branch" => $this->request->getPost('branch'),
        "ifsc" => $this->request->getPost('ifsc'),
        "micr" => $this->request->getPost('micr'),
        "epf_no" => $this->request->getPost('epf_no'),
        "uan_no" => $this->request->getPost('uan_no'),
        "swift_code" => $this->request->getPost('swift_code'),
        "iban_code" => $this->request->getPost('iban_code'),
        "user_id" => $contact_id
    ];

    $kyc_info_data = clean_data($kyc_info_data);

    $this->Kyc_info_model->save($kyc_info_data, $contact_id); // Assuming method name change to save
    return $this->response->setJSON(['success' => true, 'message' => lang('record_updated')]);
}

public function save_account_settings($user_id)
{
    $this->access_only_allowed_members_or_contact_personally($user_id);

    $data = [
        "email" => $this->request->getPost('email')
    ];

    // Handle password only if provided
    $password = $this->request->getPost('password');
    if ($password) {
        $data['password'] = md5($password);
    }

    // Only admin can disable login
    if ($this->auth->isAdmin()) {
        $data['disable_login'] = $this->request->getPost('disable_login');
    }

    // Validate email
    validate($data, [
        "email" => "required|valid_email"
    ]);

    // Check for duplicate email
    if ($this->Users_model->isEmailExists($data['email'], $user_id)) { // Assuming method name change to isEmailExists
        return $this->response->setJSON(['success' => false, 'message' => lang('duplicate_email')]);
    }

    if ($this->Users_model->save($data, $user_id)) { // Assuming method name change to save
        return $this->response->setJSON(['success' => true, 'message' => lang('record_updated')]);
    } else {
        return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
    }
}

public function save_profile_image($user_id = 0)
{
    $this->access_only_allowed_members_or_contact_personally($user_id);

    // Process the file uploaded by Dropzone
    $profile_image = str_replace("~", ":", $this->request->getPost("profile_image"));

    if ($profile_image) {
        $profile_image = move_temp_file("avatar.png", get_setting("profile_image_path"), "", $profile_image);
        $image_data = ["image" => $profile_image];
        $this->Users_model->save($image_data, $user_id);
        return $this->response->setJSON(["success" => true, "message" => lang('profile_image_changed')]);
    }

    // Process the file uploaded using manual file submit
    if ($_FILES) {
        $profile_image_file = $this->request->getFiles("profile_image_file");
        $image_file_name = $profile_image_file["tmp_name"];
        if ($image_file_name) {
            $profile_image = move_temp_file("avatar.png", get_setting("profile_image_path"), "", $image_file_name);
            $image_data = ["image" => $profile_image];
            $this->Users_model->save($image_data, $user_id);
            return $this->response->setJSON(["success" => true, "message" => lang('profile_image_changed')]);
        }
    }
}


    /* delete or undo a contact */
    public function delete_contact()
    {
        $validation = \Config\Services::validation();
    
        $validation->setRules([
            'id' => 'required|numeric'
        ]);
    
        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON(['success' => false, 'message' => $validation->getErrors()]);
        }
    
        $this->access_only_allowed_members();
    
        $id = $this->request->getPost('id');
    
        if ($this->request->getPost('undo')) {
            if ($this->Users_model->delete($id, true)) {
                return $this->response->setJSON(['success' => true, 'data' => $this->_contact_row_data($id), 'message' => lang('record_undone')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($this->Users_model->delete($id)) {
                return $this->response->setJSON(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }
    
    /* list of contacts, prepared for datatable  */

    public function contacts_list_data($client_id = 0)
    {
        $this->access_only_allowed_members_or_client_contact($client_id);
    
        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("contacts", $this->login_user->is_admin, $this->login_user->user_type);
    
        $options = [
            "user_type" => "client",
            "client_id" => $client_id,
            "custom_fields" => $custom_fields
        ];
    
        $list_data = $this->Users_model->get_details($options)->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_contact_row($data, $custom_fields);
        }
    
        return $this->response->setJSON(["data" => $result]);
    }
    

    /* return a row of contact list table */

    private function _contact_row_data($id)
{
    $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("contacts", $this->login_user->is_admin, $this->login_user->user_type);

    $options = [
        "id" => $id,
        "user_type" => "client",
        "custom_fields" => $custom_fields
    ];

    $data = $this->Users_model->get_details($options)->getRow();
    return $this->_make_contact_row($data, $custom_fields);
}

    /* prepare a row of contact list table */

    private function _make_contact_row($data, $custom_fields)
    {
        $image_url = get_avatar($data->image);
        $user_avatar = "<span class='avatar avatar-xs'><img src='$image_url' alt='...'></span>";
        $full_name = $data->first_name . " " . $data->last_name . " ";
        $primary_contact = ($data->is_primary_contact == "1") ? "<span class='label-info label'>" . lang('primary_contact') . "</span>" : "";
    
        $contact_link = anchor()->getUri("clients/contact_profile/" . $data->id, $full_name . $primary_contact);
    
        if ($this->login_user->user_type === "client") {
            $contact_link = $full_name; // Don't show clickable link to client
        }
    
        $row_data = [
            $user_avatar,
            $contact_link,
            $data->job_title,
            $data->email,
            $data->phone ? $data->phone : "-",
            $data->alternative_phone ? $data->alternative_phone : "-",
            $data->skype ? $data->skype : "-"
        ];
    
        foreach ($custom_fields as $field) {
            $cf_id = "cfv_" . $field->id;
            $row_data[] = view("custom_fields/output_" . $field->field_type, ["value" => $data->$cf_id]);
        }
    
        $row_data[] = js()->anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_contact'), "class" => "delete", "data-id" => "$data->id", "data-action-url" => getUri("clients/delete_contact"), "data-action" => "delete-confirmation"]);
    
        return $row_data;
    }
    

    /* open invitation modal */

    public function invitation_modal()
{
    $validation = \Config\Services::validation();

    $validation->setRules([
        'client_id' => 'required|numeric'
    ]);

    if (!$validation->withRequest($this->request)->run()) {
        return $this->response->setJSON(['success' => false, 'message' => $validation->getErrors()]);
    }

    $client_id = $this->request->getPost('client_id');

    $this->access_only_allowed_members_or_client_contact($client_id);

    $view_data["client_info"] = $this->Clients_model->get_one($client_id);
    return view('clients/contacts/invitation_modal', $view_data);
}

    //send a team member invitation to an email address
    public function send_invitation()
    {
        $client_id = $this->request->getPost('client_id');
        $email = trim($this->request->getPost('email'));
    
        $validation = \Config\Services::validation();
    
        $validation->setRules([
            'client_id' => 'required|numeric',
            'email' => 'required|valid_email|trim'
        ]);
    
        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON(['success' => false, 'message' => $validation->getErrors()]);
        }
    
        $this->access_only_allowed_members_or_client_contact($client_id);
    
        $email_template = $this->Email_templates_model->get_final_template("client_contact_invitation");
    
        $parser_data = [
            "INVITATION_SENT_BY" => $this->login_user->first_name . " " . $this->login_user->last_name,
            "SIGNATURE" => $email_template->signature,
            "SITE_URL" => site_url(),
            "LOGO_URL" => get_logo_url()
        ];
    
        // Make the invitation URL with 24hrs validity
        $key = encode_id($this->encryption->encrypt('client|' . $email . '|' . (time() + (24 * 60 * 60)) . '|' . $client_id), "signup");
        $parser_data['INVITATION_URL'] = site_url("signup/accept_invitation/" . $key);
    
        // Send invitation email
        $message = $this->parser->setData($parser_data)->renderString($email_template->message);
        if (send_app_mail($email, $email_template->subject, $message)) {
            return $this->response->setJSON(['success' => true, 'message' => lang("invitation_sent")]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }
    

    /* only visible to client  */

    public function users()
{
    if ($this->login_user->user_type === "client") {
        $view_data['client_id'] = $this->login_user->client_id;
        return view("clients/contacts/users", $view_data);
    }
}
public function get_country_item_suggestion()
{
    $key = $this->request->getVar("q");
    $suggestion = [];

    $items = $this->Countries_model->get_country_suggestion($key);

    foreach ($items as $item) {
        $suggestion[] = ["id" => $item->id, "text" => $item->countryName];
    }

    echo json_encode($suggestion);
}

public function get_country_item_info_suggestion()
{
    $itemName = $this->request->getPost("item_name");

    $item = $this->Countries_model->get_country_info_suggestion($itemName);

    if ($item) {
        echo json_encode(["success" => true, "item_info" => $item]);
    } else {
        echo json_encode(["success" => false]);
    }
}



public function get_country_code_suggestion()
{
    $itemName = $this->request->getPost("item_name");

    $item = $this->Countries_model->get_country_code_suggestion($itemName);

    if ($item) {
        echo json_encode(["success" => true, "item_info" => $item]);
    } else {
        echo json_encode(["success" => false]);
    }
}


public function get_state_suggestion()
{
    $key = $this->request->getVar("q");
    $ss = $this->request->getVar("ss");

    $itemss = $this->Countries_model->get_item_suggestions_country_name($key, $ss);

    $suggestions = [];
    foreach ($itemss as $items) {
        $suggestions[] = ["id" => $items->id, "text" => $items->title];
    }

    echo json_encode($suggestions);
}

public function get_state_suggestion()
{
    $key = $this->request->getVar("q");
    $ss = $this->request->getVar("ss");

    $itemss = $this->Countries_model->get_item_suggestions_country_name($key, $ss);

    $suggestions = [];
    foreach ($itemss as $items) {
        $suggestions[] = ["id" => $items->id, "text" => $items->title];
    }

    echo json_encode($suggestions);
}




//Import excel ,csv modal form  for vendors 
public function clients_excel_form()
{
    return view('clients/clients_excel_form');
}



public function import()
{
    if ($this->request->getFile("file")) {
        $file = $this->request->getFile("file");
        $path = $file->getTempName();

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $worksheet = $spreadsheet->getActiveSheet();

        foreach ($worksheet->getRowIterator(2) as $row) {
            $rowData = $row->getValues();

            // Map column values to variables
            $company_name = $rowData[0];
            $address = $rowData[1];
            $city = $rowData[2];
            $state = $rowData[3];
            $country = $rowData[4];
            $zip = $rowData[5];
            $phone = $rowData[6];
            $website = $rowData[7];
            $gst_number = $rowData[8];contact_social_links_tab
            $gstin_number_first_two_digits = $rowData[9];
            $currency = $rowData[10];
            $currency_symbol = $rowData[11];

            // Get country ID from name
            $country_id = $this->Countries_model->get_country_id_excel(["countryName" => $country])->id;

            // Get state ID from name
            $state_id = $this->States_model->get_state_id_excel(["title" => $state])->id;

            // Prepare data for insertion
            $data = [
                "company_name" => $company_name,
                "address" => $address,
                "city" => $city,
                "state" => $state_id,
                "country" => $country_id,
                "zip" => $zip,
                "phone" => $phone,
                "website" => $website,
                "gst_number" => $gst_number,
                "gstin_number_first_two_digits" => $gstin_number_first_two_digits,
                "currency" => $currency,
                "currency_symbol" => $currency_symbol,
                "buyer_type" => 0,
                "group_ids" => 0,
                "deleted" => 0,
                "created_date" => date("Y-m-d")
            ];

            // Check if data already exists before insertion
            $existingData = $this->Clients_model->get_import_detailss($data)->getRow();
            if (!$existingData) {
                $this->Clients_model->insert($data);
            }
        }

        echo 'Data Imported successfully';
    }
}



public function upload_file_csv()
{
    $csvMimes = ['application/vnd.ms-excel', 'text/plain', 'text/csv', 'text/tsv'];

    if ($this->request->getFile('file') && in_array($this->request->getFile('file')->getMimeType(), $csvMimes)) {
        $file = $this->request->getFile('file');
        $csvFile = fopen($file->getTempName(), 'r');

        // Skip first line if CSV has headers
        fgetcsv($csvFile);

        while (($line = fgetcsv($csvFile)) !== false) {
            // Map column values to variables
            $company_name = $line[0];
            $address = $line[1];
            $city = $line[2];
            $state = $line[3];
            $country = $line[4];
            $zip = $line[5];
            $phone = $line[6];
            $website = $line[7];
            $gst_number = $line[8];
            $gstin_number_first_two_digits = $line[9];
            $currency = $line[10];
            $currency_symbol = $line[11];

            // Get country ID from name
            $country_id = $this->Countries_model->get_country_id_excel(["countryName" => $country])->id;

            // Get state ID from name
            $state_id = $this->States_model->get_state_id_excel(["title" => $state])->id;

            // Prepare data for insertion
            $data = [
                "company_name" => $company_name,
                "address" => $address,
                "city" => $city,
                "state" => $state_id,
                "country" => $country_id,
                "zip" => $zip,
                "phone" => $phone,
                "website" => $website,
                "gst_number" => $gst_number,
                "gstin_number_first_two_digits" => $gstin_number_first_two_digits,
                "currency" => $currency,
                "currency_symbol" => $currency_symbol,
                "buyer_type" => 0,
                "group_ids" => 0,
                "deleted" => 0,
                "created_date" => date("Y-m-d")
            ];

            // Check if data already exists before insertion
            $existingData = $this->Clients_model->get_import_detailss($data)->getRow();
            if (!$existingData) {
                $this->Clients_model->insert($data);
            }
        }

        fclose($csvFile);
    }
}

public function clients_po_list($client_id)
{
    $this->access_only_allowed_members();

    if ($client_id) {
        $view_data["client_info"] = $this->Clients_model->get_one($client_id);
        $view_data['client_id'] = $client_id;
        return view("clients/client_po_list", $view_data);
    }
}


public function clients_wo_list($client_id)
{
    $this->access_only_allowed_members();

    if ($client_id) {
        $view_data["client_info"] = $this->Clients_model->get_one($client_id);
        $view_data['client_id'] = $client_id;
        return view("clients/client_wo_list", $view_data);
    }
}
public function keyboard_shortcut_modal_form()
{
    return view('team_members/keyboard_shortcut_modal_form');
}
}

/* End of file clients.php */
/* Location: ./application/controllers/clients.php */