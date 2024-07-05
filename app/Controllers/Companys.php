<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use App\Models\Custom_fields_model;
use App\Models\Companys_model;
use App\Models\Countries_model;
use App\Models\States_model;
use App\Models\Client_groups_model;
use App\Models\Company_groups_model;
use App\Models\Buyer_types_model;
use App\Models\Gst_state_code_model;
use App\Models\Users_model;
use App\Models\Social_links_model;
use App\Models\Settings_model;
use App\Models\Kyc_info_model;
use App\Models\Email_templates_model;

class Companys extends BaseController
{
    protected $customfieldsmodel;
    protected $companysmodel;
    protected $countriesmodel;
    protected $statesmodel;
    protected $clientgroupsmoel;
    protected $companygroupsmodel;
    protected $buyertypesmodel;
    protected $gststatecodemodel;
    protected $clientsmodel;
    protected $generalfilesmodel;
    protected $usersmodel;
    protected $sociallinksmodel;
    protected $settingsmodel;
    protected $kycinfomodel;
    protected $emailtemplatesmodel;

    public function __construct()
    {
        $this->customfieldsmodel = new Custom_fields_model();
        $this->companysmodel = new Companys_model();
        $this->countriesmodel = new Countries_model();
        $this->statesmodel = new States_model();
        $this->clientgroupsmoel = new Client_groups_model();
        $this->companygroupsmodel = new Company_groups_model();
        $this->buyertypesmodel = new Buyer_types_model();
        $this->gststatecodemodel = new Gst_state_code_model();
        $this->clientsmodel = new Companys_model();
        $this->generalfilesmodel = new General_files_model();
        $this->usersmodel = new Users_model();
        $this->sociallinksmodel = new Social_links_model();
        $this->settingsmodel = new Settings_model();
        $this->kycinfomodel = new Kyc_info_model();
        $this->emailtemplatesmodel = new Email_templates_model();

        // Check permissions and initialize libraries
        $this->init_permission_checker("company");
        $this->access_only_allowed_members();
        $this->excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    }

    public function index()
    {
        $this->access_only_allowed_members();
        $this->check_module_availability("module_company");

        $view_data = [];

        // Fetch custom field headers for table
        $view_data["custom_field_headers"] = $this->customfieldsmodel->get_custom_field_headers_for_table("clients", $this->login_user->is_admin, $this->login_user->user_type);

        // Fetch groups dropdown data
        $view_data['groups_dropdown'] = json_encode($this->_get_groups_dropdown_select2_data(true));

        return view('companys/index', $view_data);
    }

    public function modal_form()
    {
        $this->access_only_allowed_members();

        $company_id = $this->request->getPost('id');
        $validation = \Config\Services::validation();

        // Validate input data
        $validation->setRules([
            'id' => 'numeric',
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return redirect()->to('forbidden'); // Replace with your redirect URL
        }

        $view_data = [
            'label_column' => "col-md-3",
            'field_column' => "col-md-9",
            'view' => $this->request->getPost('view'),
            'model_info' => $this->companysmodel->find($company_id),
            'currency_dropdown' => $this->_get_currency_dropdown_select2_data(),
            'gst_code_dropdown' => $this->_get_gst_code_dropdown_select2_data(),
            'groups_dropdown' => $this->_get_groups_dropdown_select2_data(),
            'state_dropdown' => $this->_get_state_dropdown_select2_data(),
            'buyer_types_dropdown' => $this->_get_buyer_types_dropdown_select2_data(),
            'company_setup_country_dropdown' => $this->_get_company_setup_country_dropdown(),
            'custom_fields' => $this->customfieldsmodel->get_combined_details("companys", $company_id, $this->login_user->is_admin, $this->login_user->user_type)->getResult(),
        ];

        return view('companys/modal_form', $view_data);
    }

    private function _get_state_dropdown_select2_data($show_header = false)
    {
        $states = $this->statesmodel->findAll();
        $state_dropdown = [];

        if ($show_header) {
            $state_dropdown[] = ['id' => '', 'text' => '-'];
        }

        foreach ($states as $state) {
            $state_dropdown[] = ['id' => $state['id'], 'text' => $state['title']];
        }

        return $state_dropdown;
    }

    private function _get_groups_dropdown_select2_data($show_header = false)
    {
        $client_groups = $this->companygroupsmodel->where(['deleted' => 0, 'status' => 'active'])->findAll();
        $groups_dropdown = [];

        if ($show_header) {
            $groups_dropdown[] = ['id' => '', 'text' => '- ' . lang('company_groups') . ' -'];
        }

        foreach ($client_groups as $group) {
            $groups_dropdown[] = ['id' => $group['id'], 'text' => $group['title']];
        }

        return $groups_dropdown;
    }

    private function _get_buyer_types_dropdown_select2_data($show_header = false)
    {
        $buyer_types = $this->buyertypesmodel->where(['deleted' => 0, 'status' => 'active'])->findAll();
        $buyer_types_dropdown = [];

        foreach ($buyer_types as $buyer_type) {
            $buyer_types_dropdown[] = ['id' => $buyer_type['id'], 'text' => $buyer_type['buyer_type']];
        }

        return $buyer_types_dropdown;
    }

    private function _get_currency_dropdown_select2_data()
    {
        $currency = [['id' => '', 'text' => '-']];
        foreach (get_international_currency_code_dropdown() as $value) {
            $currency[] = ['id' => $value, 'text' => $value];
        }
        return $currency;
    }

    private function _get_gst_code_dropdown_select2_data($show_header = false)
    {
        $gst_code = $this->gststatecodemodel->findAll();
        $gst_code_dropdown = [];

        foreach ($gst_code as $code) {
            $gst_code_dropdown[] = ['id' => $code['gstin_number_first_two_digits'], 'text' => $code['title']];
        }

        return $gst_code_dropdown;
    }

    /* insert or update a client */
    public function save()
    {
        $company_id = $this->request->getPost('id');

        // Validate input data
        helper('form');
        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'numeric',
            'company_name' => 'required'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->fail($validation->getErrors());
        }

        $company_name = $this->request->getPost('company_name');

        // Handle file upload for company logo
        $company_logo = $this->request->getPost('site_logo');
        $target_path = WRITEPATH . 'uploads/company/' . $company_id; // Adjust as per your directory structure
        $value = $this->moveUploadedFile('companys-logo.png', $target_path, '', $company_logo);

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
            "enable_company_logo" => $this->request->getPost('enable_company_logo'),
            "state_mandatory" => $this->request->getPost('state_mandatory'),
        ];

        // Fetch existing company logo info
        $company_info_logo = $this->Companys_model->find($company_id);
        $company_logo_file = $company_info_logo ? $company_info_logo['company_logo'] : null;

        // Update company logo if new logo is uploaded
        if ($company_logo && !$company_logo_file) {
            $data["company_logo"] = $value;
        } else if ($company_logo && $company_logo_file) {
            // Delete old logo and update with new one
            $this->deleteFileFromDirectory(WRITEPATH . 'uploads/company/' . $company_id . '/' . $company_logo_file);
            $data["company_logo"] = $value;
        }

        // Additional fields based on user type (staff or admin)
        if ($this->login_user->user_type === "staff") {
            $data["group_ids"] = $this->request->getPost('group_ids') ?? '';
        }

        if ($this->login_user->is_admin) {
            $data["currency_symbol"] = $this->request->getPost('currency_symbol') ?? '';
            $data["currency"] = $this->request->getPost('currency') ?? '';
            $data["disable_online_payment"] = $this->request->getPost('disable_online_payment') ?? 0;
        }

        // Set created date if it's a new company
        if (!$company_id) {
            $data["created_date"] = date('Y-m-d H:i:s');
        }

        // Clean data before saving
        $data = $this->cleanData($data);

        // Check for duplicate company name
        if ($this->Companys_model->isDuplicateCompanyName($data["company_name"], $company_id)) {
            return $this->fail(lang('account_already_exists_for_your_company_name'));
        }

        // Save company data
        $save_id = $this->Companys_model->save($data, $company_id);

        // Save the new invoice no
        if (!$company_id && $save_id) {
            $cr_id = 'CR' . str_pad($save_id, 3, '0', STR_PAD_LEFT); // Example: CR001
            $this->Companys_model->save(['cr_id' => $cr_id], $save_id);
        }

        if ($save_id) {
            // Save custom fields
            save_custom_fields("companys", $save_id, $this->login_user->is_admin, $this->login_user->user_type);

            // Return success response
            return $this->respondCreated([
                "success" => true,
                "data" => $this->_rowData($save_id),
                'id' => $save_id,
                'view' => $this->request->getPost('view'),
                'message' => lang('record_saved')
            ]);
        } else {
            // Return error response
            return $this->fail(lang('error_occurred'));
        }
    }

    /* delete or undo a client */

    public function delete()
    {
        $this->access_only_allowed_members();

        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'required|numeric'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->fail($validation->getErrors());
        }

        $id = $this->request->getPost('id');
        $company_data = $this->Companys_model->find($id);
        if (!$company_data) {
            return $this->fail(lang('record_not_found'));
        }

        $cr_id = $company_data['cr_id'];

        if ($this->Companys_model->delete_company_and_sub_items($id, $cr_id)) {
            return $this->respond([
                'success' => true,
                'message' => lang('record_deleted')
            ]);
        } else {
            return $this->fail(lang('record_cannot_be_deleted'));
        }
    }
    /* list of clients, prepared for datatable  */
    public function list_data()
    {
        $this->access_only_allowed_members();
    
        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("companys", $this->login_user->is_admin, $this->login_user->user_type);
        $options = [
            "custom_fields" => $custom_fields,
            "group_id" => $this->request->getPost("group_id")
        ];
    
        $list_data = $this->Companys_model->get_details($options)->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data, $custom_fields);
        }
    
        return $this->respond([
            "data" => $result
        ]);
    }
    

    /* return a row of client list  table */

    private function _row_data($id)
{
    $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("companys", $this->login_user->is_admin, $this->login_user->user_type);
    $options = [
        "id" => $id,
        "custom_fields" => $custom_fields
    ];

    $data = $this->Companys_model->get_details($options)->getRow();
    return $this->_make_row($data, $custom_fields);
}

private function _make_row($data, $custom_fields)
{
    $image_url = get_avatar($data['contact_avatar']);
    $contact = "<span class='avatar avatar-xs mr10'><img src='$image_url' alt='...'></span> {$data['primary_contact']}";
    $primary_contact = get_company_contact_profile_link($data['primary_contact_id'], $contact);

    $group_list = "";
    if ($data['groups']) {
        $groups = explode(",", $data['groups']);
        foreach ($groups as $group) {
            if ($group) {
                $group_list .= "<li>" . $group . "</li>";
            }
        }
    }

    if ($group_list) {
        $group_list = "<ul class='pl15'>" . $group_list . "</ul>";
    }

    $due = 0;
    if ($data['invoice_value']) {
        $due = ignor_minor_value($data['invoice_value'] - $data['payment_received']);
    }

    $row_data = [
        $data['id'],
        $data['cr_id'],
        anchor()->get_uri("companys/view/{$data['cr_id']}", $data['company_name']),
        $data['primary_contact'] ? $primary_contact : "",
        $group_list,
    ];

    foreach ($custom_fields as $field) {
        $cf_id = "cfv_{$field['id']}";
        $row_data[] = view('custom_fields/output_' . $field['field_type'], ["value" => $data[$cf_id]]);
    }

    $row_data[] = modal_anchor(get_uri("companys/modal_form"), "<i class='fa fa-pencil'></i>", ["class" => "edit", "title" => lang('edit_company'), "data-post-id" => $data['id']])
        . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_company'), "class" => "delete", "data-id" => $data['id'], "data-action-url" => get_uri("companys/delete"), "data-action" => "delete-confirmation"]);

    return $row_data;
}


public function view($company_id = 0, $tab = "")
{
    $this->access_only_allowed_members();

    if (!$company_id) {
        return show_404();
    }

    $options = ["cr_id" => $company_id];
    $company_info = $this->Companys_model->get_details($options)->getRow();

    if (!$company_info) {
        return show_404();
    }

    $view_data = [
        'company_info' => $company_info,
        'show_invoice_info' => (get_setting("module_invoice") && $this->get_access_info("invoice")->access_type == "all"),
        'show_estimate_info' => (get_setting("module_estimate") && $this->get_access_info("estimate")->access_type == "all"),
        'show_estimate_request_info' => (get_setting("module_estimate_request") && $this->get_access_info("estimate_request")->access_type == "all"),
        'show_ticket_info' => (get_setting("module_ticket") && $this->get_access_info("ticket")->access_type == "all"),
        'show_note_info' => get_setting("module_note"),
        'show_event_info' => get_setting("module_event"),
        'is_starred' => strpos($company_info['starred_by'], ":" . $this->login_user->id . ":") !== false,
        'tab' => $tab,
        'hidden_menu' => [],
    ];

    return $this->template->render("companys/view", $view_data);
}

    /* add-remove start mark from client */

    public function add_remove_star($company_id, $type = "add")
    {
        if (!$company_id) {
            return;
        }
    
        $view_data = ["company_id" => $company_id];
    
        if ($type === "add") {
            $this->Companys_model->add_remove_star($company_id, $this->login_user->id, $type = "add");
            return view('companys/star/starred', $view_data);
        } else {
            $this->Companys_model->add_remove_star($company_id, $this->login_user->id, $type = "remove");
            return view('companys/star/not_starred', $view_data);
        }
    }
    
    public function show_my_starred_companys()
    {
        $view_data['companys'] = $this->Companys_model->get_starred_companys($this->login_user->id)->getResult();
        return view('companys/star/companys_list', $view_data);
    }
   /* load projects tab  */
   public function projects($company_id)
   {
       $this->access_only_allowed_members();
   
       $view_data['can_create_projects'] = $this->can_create_projects();
       $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("projects", $this->login_user->is_admin, $this->login_user->user_type);
   
       $view_data['company_id'] = $company_id;
       return view("companys/projects/index", $view_data);
   }
   
    /* load payments tab  */

    public function payments($company_id)
    {
        $this->access_only_allowed_members();
    
        if ($company_id) {
            $view_data["company_info"] = $this->Companys_model->find($company_id);
            $view_data['company_id'] = $company_id;
            return view("companys/payments/index", $view_data);
        }
    }
    

    /* load tickets tab  */

    public function tickets($company_id)
    {
        $this->access_only_allowed_members();
    
        if ($company_id) {
            $view_data['company_id'] = $company_id;
            $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("tickets", $this->login_user->is_admin, $this->login_user->user_type);
            $view_data['show_project_reference'] = get_setting('project_reference_in_tickets');
            return view("companys/tickets/index", $view_data);
        }
    }
    
    /* load invoices tab  */

    public function invoices($company_id)
    {
        $this->access_only_allowed_members();
    
        if ($company_id) {
            $view_data["company_info"] = $this->Companys_model->find($company_id);
            $view_data['company_id'] = $company_id;
            $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("invoices", $this->login_user->is_admin, $this->login_user->user_type);
            return view("companys/invoices/index", $view_data);
        }
    }    
    /* load estimates tab  */

    public function estimates($company_id)
    {
        $this->access_only_allowed_members();
    
        if ($company_id) {
            $view_data["company_info"] = $this->Companys_model->find($company_id);
            $view_data['company_id'] = $company_id;
            $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("estimates", $this->login_user->is_admin, $this->login_user->user_type);
            return view("companys/estimates/estimates", $view_data);
        }
    }
    /* load estimate requests tab  */

    public function estimate_requests($company_id)
{
    $this->access_only_allowed_members();

    if ($company_id) {
        $view_data['company_id'] = $company_id;
        return view("companys/estimates/estimate_requests", $view_data);
    }
}

    /* load notes tab  */

    public function notes($company_id)
{
    $this->access_only_allowed_members();

    if ($company_id) {
        $view_data['company_id'] = $company_id;
        return view("companys/notes/index", $view_data);
    }
}


    /* load events tab  */
    public function events($company_id)
    {
        $this->access_only_allowed_members();
    
        if ($company_id) {
            $view_data['company_id'] = $company_id;
            return view("events/index", $view_data);
        }
    }
    

    /* load files tab */

    public function files($company_id)
    {
        $this->access_only_allowed_members();
    
        $options = ["company_id" => $company_id];
        $view_data['files'] = $this->General_files_model->get_details($options)->getResult();
        $view_data['company_id'] = $company_id;
        return view("companys/files/index", $view_data);
    }
    

    /* file upload modal */

    public function file_modal_form()
{
    $id = $this->request->getPost('id');
    $view_data['model_info'] = $this->General_files_model->find($id);
    $company_id = $this->request->getPost('company_id') ?? $view_data['model_info']['company_id'];

    $this->access_only_allowed_members();

    $view_data['company_id'] = $company_id;
    return view('companys/files/modal_form', $view_data);
}


    /* save file data and move temp file to parmanent file directory */

    public function save_file()
    {
        $validation = \Config\Services::validation();
        $validation->setRules([
            "id" => "numeric",
            "company_id" => "required"
        ]);
    
        if (!$validation->withRequest($this->request)->run()) {
            return $this->fail($validation->getErrors());
        }
    
        $company_id = $this->request->getPost('company_id');
        $this->access_only_allowed_members();
    
        $files = $this->request->getPost("files");
        $success = false;
        $now = date('Y-m-d H:i:s');
    
        $target_path = WRITEPATH . "uploads/company_files/" . $company_id . "/";
    
        // Process the files uploaded by dropzone
        if ($files && is_array($files)) {
            foreach ($files as $file) {
                $file_name = $file->getName();
                $new_file_name = $file->getRandomName();
                if ($file->move($target_path, $new_file_name)) {
                    $data = [
                        "company_id" => $company_id,
                        "file_name" => $new_file_name,
                        "description" => $this->request->getPost('description_' . $file_name),
                        "file_size" => $file->getSize(),
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
            return $this->respond([
                "success" => true,
                "message" => lang('record_saved')
            ]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }
    
    public function files_list_data($company_id = 0)
    {
        $this->access_only_allowed_members();

        $options = ["company_id" => $company_id];
        $list_data = $this->General_files_model->get_details($options)->getResult();
        $result = [];
        
        foreach ($list_data as $data) {
            $result[] = $this->_make_file_row($data);
        }
        
        return $this->response->setJSON(["data" => $result]);
    }

    private function _make_file_row($data)
    {
        $file_icon = get_file_icon(strtolower(pathinfo($data->file_name, PATHINFO_EXTENSION)));

        $image_url = get_avatar($data->uploaded_by_user_image);
        $uploaded_by = "<span class='avatar avatar-xs mr10'><img src='$image_url' alt='...'></span> $data->uploaded_by_user_name";
        $uploaded_by = get_team_member_profile_link($data->uploaded_by, $uploaded_by);

        $description = "<div class='pull-left'>" . js_anchor(remove_file_prefix($data->file_name), [
            'title' => "",
            "data-toggle" => "app-modal",
            "data-sidebar" => "0",
            "data-url" => get_uri("companys/view_file/" . $data->id)
        ]);

        if ($data->description) {
            $description .= "<br /><span>" . $data->description . "</span></div>";
        } else {
            $description .= "</div>";
        }

        $options = anchor(get_uri("companys/download_file/" . $data->id), "<i class='fa fa-cloud-download'></i>", [
            "title" => lang("download")
        ]);

        $options .= js_anchor("<i class='fa fa-times fa-fw'></i>", [
            'title' => lang('delete_file'),
            "class" => "delete",
            "data-id" => $data->id,
            "data-action-url" => get_uri("companys/delete_file"),
            "data-action" => "delete-confirmation"
        ]);

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
    
            if (!$file_info->company_id) {
                return redirect()->to("forbidden");
            }
    
            $view_data['can_comment_on_files'] = false;
            $view_data["file_url"] = get_file_uri(get_general_file_path("company", $file_info->company_id) . $file_info->file_name);
            $view_data["is_image_file"] = is_image_file($file_info->file_name);
            $view_data["is_google_preview_available"] = is_google_preview_available($file_info->file_name);
            $view_data["file_info"] = $file_info;
            $view_data['file_id'] = $file_id;
            return view("companys/files/view", $view_data);
        } else {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
    }
    
    /* download a file */

    public function download_file($id)
    {
        $file_info = $this->General_files_model->find($id);
    
        if (!$file_info || !$file_info->company_id) {
            return redirect()->to("forbidden");
        }
    
        $file_data = serialize([["file_name" => $file_info->file_name]]);
        download_app_files(get_general_file_path("company", $file_info->company_id), $file_data);
    }
    
    public function upload_file()
    {
        upload_file_to_temp();
    }
    
    public function validate_file()
    {
        return validate_post_file($this->request->getPost("file_name"));
    }
    
    public function delete_file()
    {
        $id = $this->request->getPost('id');
        $info = $this->General_files_model->find($id);
    
        if (!$info || !$info->company_id) {
            return redirect()->to("forbidden");
        }
    
        if ($this->General_files_model->delete($id)) {
            delete_file_from_directory(get_general_file_path("company", $info->company_id) . $info->file_name);
            return $this->response->setJSON(["success" => true, 'message' => lang('record_deleted')]);
        } else {
            return $this->response->setJSON(["success" => false, 'message' => lang('record_cannot_be_deleted')]);
        }
    }
    
    public function contact_profile($contact_id = null, $tab = "") {
        $this->access_only_allowed_members_or_contact_personally($contact_id);
    
        $viewData['user_info'] = $this->Users_model->find($contact_id);
        $viewData['company_info'] = $this->Companys_model->where('cr_id', $viewData['user_info']->company_id)->first();
        $viewData['tab'] = $tab;
    
        if ($viewData['user_info'] && $viewData['user_info']->user_type === "company") {
            $viewData['show_contact_info'] = true;
            $viewData['show_social_links'] = true;
            $viewData['social_link'] = $this->Social_links_model->find($contact_id);
            return view('companys/contacts/view', $viewData);
        } else {
            throw PageNotFoundException::forPageNotFound();
        }
    }
    

    public function account_settings($contact_id) {
        $this->access_only_allowed_members_or_contact_personally($contact_id);
    
        $viewData['user_info'] = $this->Users_model->find($contact_id);
        return view('users/account_settings', $viewData);
    }    
    //show my preference settings of a team member
    public function my_preferences() {
        $viewData['user_info'] = $this->Users_model->find($this->login_user->id);
        $viewData['language_dropdown'] = [];
    
        if (!get_setting("disable_language_selector_for_clients")) {
            $viewData['language_dropdown'] = get_language_list();
        }
    
        return view('companys/contacts/my_preferences', $viewData);
    }
    
    public function save_bank_info($company_id = null)
    {
        $this->access_only_allowed_members_or_client_contact($company_id);
    
        $postData = $this->request->getPost();
    
        $user_data = [
            "cin" => $postData['cin'],
            "tan" => $postData['tan'],
            "uam" => $postData['uam'],
            "panno" => $postData['panno'],
            "iec" => $postData['iec'],
            "name" => $postData['name'],
            "accountnumber" => $postData['accountnumber'],
            "swift_code" => $postData['swift_code'],
            "bankname" => $postData['bankname'],
            "branch" => $postData['branch'],
            "ifsc" => $postData['ifsc'],
            "micr" => $postData['micr']
        ];
    
        $user_data = array_map('clean_data', $user_data);
    
        $user_info_updated = $this->Companys_model->save($user_data, $company_id);
    
        if ($user_info_updated) {
            return $this->response->setJSON(["success" => true, 'message' => lang('record_updated')]);
        } else {
            return $this->response->setJSON(["success" => false, 'message' => lang('error_occurred')]);
        }
    }
    

    /* load contact's social links tab view */

    public function contact_social_links_tab($contact_id = null)
{
    $this->access_only_allowed_members_or_contact_personally($contact_id);

    $viewData['user_id'] = $contact_id;
    $viewData['user_type'] = "company";
    $viewData['model_info'] = $this->Social_links_model->find($contact_id);

    return view('users/social_links', $viewData);
}
public function contact_kyc_info_tab($contact_id = null)
{
    $this->access_only_allowed_members_or_contact_personally($contact_id);

    $viewData['user_id'] = $contact_id;
    $viewData['user_type'] = "company";
    $viewData['model_info'] = $this->Kyc_info_model->find($contact_id);

    return view('users/kyc_info', $viewData);
}

    /* insert/upadate a contact */

    public function save_contact()
{
    $contact_id = $this->request->getPost('contact_id');
    $company_id = $this->request->getPost('company_id');

    $this->access_only_allowed_members_or_contact_personally($contact_id);

    $user_data = [
        "first_name" => $this->request->getPost('first_name'),
        "last_name" => $this->request->getPost('last_name'),
        "phone" => $this->request->getPost('phone'),
        "alternative_phone" => $this->request->getPost('alternative_phone'),
        "skype" => $this->request->getPost('skype'),
        "job_title" => $this->request->getPost('job_title'),
        "gender" => $this->request->getPost('gender'),
        "note" => $this->request->getPost('note'),
        "user_type" => "company",
    ];

    $validationRules = [
        "first_name" => "required",
        "last_name" => "required",
        "company_id" => "required"
    ];

    if (!$contact_id) {
        // Inserting new contact. Client_id is required.
        $validationRules["email"] = "required|valid_email";

        $user_data["company_id"] = $company_id;
        $user_data["email"] = trim($this->request->getPost('email'));
        $user_data["password"] = md5($this->request->getPost('login_password'));
        $user_data["created_at"] = date('Y-m-d H:i:s'); // Example date format.

        // Validate duplicate email address
        if ($this->Users_model->is_email_exists($user_data["email"])) {
            return $this->response->setJSON(["success" => false, 'message' => lang('duplicate_email')]);
        }
    }

    // By default, the first contact of a client is the primary contact.
    // Check existing primary contact. If not found, then set the first contact = primary contact.
    $primary_contact = $this->Companys_model->get_primary_contact($company_id);
    if (!$primary_contact) {
        $user_data['is_primary_contact'] = 1;
    }

    // Only admin can change existing primary contact.
    $is_primary_contact = $this->request->getPost('is_primary_contact');
    if ($is_primary_contact && $this->login_user->is_admin) {
        $user_data['is_primary_contact'] = 1;
    }

    $user_data = array_map('clean_data', $user_data);

    $save_id = $this->Users_model->save($user_data, $contact_id);
    if ($save_id) {
        save_custom_fields("contacts", $save_id, $this->login_user->is_admin, $this->login_user->user_type);

        // Has changed the existing primary contact? Update previous primary contact and set is_primary_contact=0.
        if ($is_primary_contact) {
            $this->Users_model->save(["is_primary_contact" => 0], $primary_contact);
        }

        // Send login details to user only for the first time when creating a new contact.
        if (!$contact_id && $this->request->getPost('email_login_details')) {
            // Implement email sending logic here.
        }

        return $this->response->setJSON(["success" => true, "data" => $this->_contact_row_data($save_id), 'id' => $contact_id, 'message' => lang('record_saved')]);
    } else {
        return $this->response->setJSON(["success" => false, 'message' => lang('error_occurred')]);
    }
}

    //save social links of a contact
    public function save_contact_social_links($contact_id = null)
    {
        $this->access_only_allowed_members_or_contact_personally($contact_id);
    
        $postData = $this->request->getPost();
    
        $social_link_data = [
            "facebook" => $postData['facebook'],
            "twitter" => $postData['twitter'],
            "linkedin" => $postData['linkedin'],
            "googleplus" => $postData['googleplus'],
            "digg" => $postData['digg'],
            "youtube" => $postData['youtube'],
            "pinterest" => $postData['pinterest'],
            "instagram" => $postData['instagram'],
            "github" => $postData['github'],
            "tumblr" => $postData['tumblr'],
            "vine" => $postData['vine'],
            "user_id" => $contact_id,
        ];
    
        $social_link_data = array_map('clean_data', $social_link_data);
    
        $this->Social_links_model->save($social_link_data, $contact_id);
        return $this->response->setJSON(["success" => true, 'message' => lang('record_updated')]);
    }
    
    public function save_kyc_info($contact_id = null)
    {
        $this->access_only_allowed_members_or_contact_personally($contact_id);
    
        $postData = $this->request->getPost();
    
        $kyc_info_data = [
            "aadhar_no" => $postData['aadhar_no'],
            "passportno" => $postData['passportno'],
            "drivinglicenseno" => $postData['drivinglicenseno'],
            "panno" => $postData['panno'],
            "voterid" => $postData['voterid'],
            "name" => $postData['name'],
            "accountnumber" => $postData['accountnumber'],
            "bankname" => $postData['bankname'],
            "branch" => $postData['branch'],
            "ifsc" => $postData['ifsc'],
            "micr" => $postData['micr'],
            "epf_no" => $postData['epf_no'],
            "uan_no" => $postData['uan_no'],
            "swift_code" => $postData['swift_code'],
            "iban_code" => $postData['iban_code'],
            "user_id" => $contact_id,
        ];
    
        $kyc_info_data = array_map('clean_data', $kyc_info_data);
    
        $this->Kyc_info_model->save($kyc_info_data, $contact_id);
        return $this->response->setJSON(["success" => true, 'message' => lang('record_updated')]);
    }
    
    public function save_account_settings($user_id = null)
    {
        $this->access_only_allowed_members_or_contact_personally($user_id);
    
        $postData = $this->request->getPost();
    
        $account_data = [
            "email" => $postData['email']
        ];
    
        // Don't reset password if the user doesn't enter any password.
        if ($postData['password']) {
            $account_data['password'] = md5($postData['password']);
        }
    
        // Only admin can disable other users' login permission.
        if ($this->login_user->is_admin) {
            $account_data['disable_login'] = $postData['disable_login'];
        }
    
        if ($this->Users_model->save($account_data, $user_id)) {
            return $this->response->setJSON(["success" => true, 'message' => lang('record_updated')]);
        } else {
            return $this->response->setJSON(["success" => false, 'message' => lang('error_occurred')]);
        }
    }
    
    //save profile image of a contact
    public function save_profile_image($user_id = null)
    {
        $this->access_only_allowed_members_or_contact_personally($user_id);
    
        // Process the file uploaded by dropzone.
        $profile_image = $this->request->getPost("profile_image");
    
        if ($profile_image) {
            $profile_image = move_uploaded_file($profile_image, WRITEPATH . 'uploads/profile_images/avatar.png'); // Adjust target path as needed
            $image_data = ["image" => $profile_image];
            $this->Users_model->save($image_data, $user_id);
            return $this->response->setJSON(["success" => true, 'message' => lang('profile_image_changed')]);
        }
    
        // Process the file uploaded using manual file submit.
        $profile_image_file = $this->request->getFile('profile_image_file');
    
        if ($profile_image_file && $profile_image_file->isValid()) {
            $profile_image = $profile_image_file->move(WRITEPATH . 'uploads/profile_images', 'avatar.png'); // Adjust target path as needed
            $image_data = ["image" => $profile_image];
            $this->Users_model->save($image_data, $user_id);
            return $this->response->setJSON(["success" => true, 'message' => lang('profile_image_changed')]);
        }
    
        return $this->response->setJSON(["success" => false, 'message' => lang('error_occurred')]);
    }
    
    /* delete or undo a contact */
    public function delete_contact()
    {
        $this->validate([
            "id" => "required|numeric"
        ]);
    
        $this->access_only_allowed_members();
    
        $id = $this->request->getPost('id');
    
        if ($this->request->getPost('undo')) {
            if ($this->Users_model->delete($id, true)) {
                return $this->response->setJSON(["success" => true, "data" => $this->_contact_row_data($id), "message" => lang('record_undone')]);
            } else {
                return $this->response->setJSON(["success" => false, "message" => lang('error_occurred')]);
            }
        } else {
            if ($this->Users_model->delete($id)) {
                return $this->response->setJSON(["success" => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->response->setJSON(["success" => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }
     /* return a row of contact list table */

     public function contacts_list_data($company_id = 0)
     {
         // $this->access_only_allowed_members_or_client_contact($company_id);
     
         $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("contacts", $this->login_user->is_admin, $this->login_user->user_type);
     
         $options = [
             "user_type" => "company",
             "company_id" => $company_id,
             "custom_fields" => $custom_fields
         ];
     
         $list_data = $this->Users_model->get_details($options)->getResult();
         $result = [];
     
         foreach ($list_data as $data) {
             $result[] = $this->_make_contact_row($data, $custom_fields);
         }
     
         return $this->response->setJSON(["data" => $result]);
     }
     
     private function _contact_row_data($id)
     {
         $customFields = $this->customFieldsModel->getAvailableFieldsForTable('contacts', $this->loginUser->is_admin, $this->loginUser->user_type);
         $options = [
             'id' => $id,
             'user_type' => 'company',
             'custom_fields' => $customFields,
         ];
         $data = $this->usersModel->getDetails($options)->getRow();
         return $this->_make_contact_row($data, $customFields);
     }
 
     private function _make_contact_row($data, $customFields)
     {
         $imageUrl = get_avatar($data->image);
         $userAvatar = "<span class='avatar avatar-xs'><img src='$imageUrl' alt='...'></span>";
         $fullName = $data->first_name . ' ' . $data->last_name . ' ';
         $primaryContact = '';
         if ($data->is_primary_contact == '1') {
             $primaryContact = "<span class='label-info label'>" . lang('primary_contact') . '</span>';
         }
 
         $contactLink = anchor(get_uri('companys/contact_profile/' . $data->id), $fullName . $primaryContact);
         if ($this->loginUser->user_type === 'company') {
             $contactLink = $fullName; // don't show clickable link to client
         }
 
         $rowData = [
             $userAvatar,
             $contactLink,
             $data->job_title,
             $data->email,
             $data->phone ?: '-',
             $data->alternative_phone ?: '-',
             $data->skype ?: '-',
         ];
 
         foreach ($customFields as $field) {
             $cfId = 'cfv_' . $field->id;
             $rowData[] = view('custom_fields/output_' . $field->field_type, ['value' => $data->$cfId]);
         }
 
         $rowData[] = js_anchor("<i class='fa fa-times fa-fw'></i>", [
             'title' => lang('delete_contact'),
             'class' => 'delete',
             'data-id' => "$data->id",
             'data-action-url' => get_uri('companys/delete_contact'),
             'data-action' => 'delete-confirmation',
         ]);
 
         return $rowData;
     }

     public function invitation_modal()
     {
         $this->validate([
             'company_id' => 'required',
         ]);
 
         $companyId = $this->request->getPost('company_id');
 
         $this->access_only_allowed_members_or_client_contact($companyId);
 
         $viewData['company_info'] = $this->companiesModel->getDetails(['cr_id' => $companyId])->getRow();
         return view('companys/contacts/invitation_modal', $viewData);
     } 

    //send a team member invitation to an email address
    public function send_invitation()
    {
        $this->validate([
            'company_id' => 'required',
            'email' => 'required|valid_email|trim',
        ]);

        $companyId = $this->request->getPost('company_id');
        $email = trim($this->request->getPost('email'));

        // $this->access_only_allowed_members_or_client_contact($companyId);

        $emailTemplate = $this->emailTemplatesModel->getFinalTemplate('company_contact_invitation');

        $parserData = [
            'INVITATION_SENT_BY' => $this->loginUser->first_name . ' ' . $this->loginUser->last_name,
            'SIGNATURE' => $emailTemplate->signature,
            'SITE_URL' => get_uri(),
            'LOGO_URL' => get_logo_url(),
        ];

        $key = encode_id($this->encryption->encrypt('company|' . $email . '|' . (time() + (24 * 60 * 60)) . '|' . $companyId), 'signup');
        $parserData['INVITATION_URL'] = get_uri('signup/accept_invitation/' . $key);

        $message = \Config\Services::parser()->setData($parserData)->renderString($emailTemplate->message);
        if (send_app_mail($email, $emailTemplate->subject, $message)) {
            return $this->respond(['success' => true, 'message' => lang('invitation_sent')]);
        } else {
            return $this->respond(['success' => false, 'message' => lang('error_occurred')]);
        }
    }
    /* only visible to client  */

    public function users()
    {
        if ($this->loginUser->user_type === 'company') {
            $viewData['company_id'] = $this->loginUser->company_id;
            return view('companys/contacts/users', $viewData);
        }
    }
    public function get_country_item_suggestion()
    {
        $key = $this->request->getGet('q');
        $suggestion = [];

        $items = $this->countriesModel->getCountrySuggestion($key);

        foreach ($items as $item) {
            $suggestion[] = ['id' => $item->id, 'text' => $item->countryName];
        }

        return $this->respond($suggestion);
    }

    public function get_country_item_info_suggestion()
    {
        $item = $this->companiesModel->getCountryInfoSuggestion($this->request->getPost('item_name'));

        if ($item) {
            return $this->respond(['success' => true, 'item_info' => $item]);
        } else {
            return $this->respond(['success' => false]);
        }
    }

    public function get_country_code_suggestion()
    {
        $item = $this->countriesModel->getCountryCodeSuggestion($this->request->getPost('item_name'));

        if ($item) {
            return $this->respond(['success' => true, 'item_info' => $item]);
        } else {
            return $this->respond(['success' => false]);
        }
    }
    public function get_state_suggestion()
{
    $key = $this->request->getGet('q');
    $ss = $this->request->getGet('ss');
    $items = $this->companiesModel->getItemSuggestionsCountryName($key, $ss);

    $suggestions = [];
    foreach ($items as $item) {
        $suggestions[] = ['id' => $item->id, 'text' => $item->title];
    }

    return $this->respond($suggestions);
}

   /* function get_gst_state_suggestion() {

        $gst_number =  $this->input->post("gst");
        $gstin_number_first_two_digits =substr($gst_number,0,2);
        $itemss =  $this->Gst_state_code_model->get_item_suggestions_gst_state($gstin_number_first_two_digits);
        //$itemss =  $this->Countries_model->get_item_suggestions_country_name('india');
        $suggestions = array();
      foreach ($itemss as $items) {
           $suggestions[] = array("id" => $items->gstin_number_first_two_digits, "text" => $items->title);
       }
        echo json_encode($suggestions);
    } */




//Import excel ,csv modal form  for vendors 
public function companys_excel_form()
    {
        return view('companys/companys_excel_form');
    }

   //import excel file for vendors 
   public function import()
    {
        $file = $this->request->getFile('file');

        if ($file->isValid() && !$file->hasMoved()) {
            $path = $file->getTempName();
            $object = PHPExcel_IOFactory::load($path);

            $data = [];
            foreach ($object->getWorksheetIterator() as $worksheet) {
                $highestRow = $worksheet->getHighestRow();
                for ($row = 2; $row <= $highestRow; $row++) {
                    $company_name = $worksheet->getCellByColumnAndRow(0, $row)->getValue();
                    $address = $worksheet->getCellByColumnAndRow(1, $row)->getValue();
                    $city = $worksheet->getCellByColumnAndRow(2, $row)->getValue();
                    $state = $worksheet->getCellByColumnAndRow(3, $row)->getValue();
                    $country = $worksheet->getCellByColumnAndRow(4, $row)->getValue();
                    $zip = $worksheet->getCellByColumnAndRow(5, $row)->getValue();
                    $phone = $worksheet->getCellByColumnAndRow(6, $row)->getValue();
                    $website = $worksheet->getCellByColumnAndRow(7, $row)->getValue();
                    $gst_number = $worksheet->getCellByColumnAndRow(8, $row)->getValue();
                    $gstin_number_first_two_digits = $worksheet->getCellByColumnAndRow(9, $row)->getValue();
                    $currency = $worksheet->getCellByColumnAndRow(10, $row)->getValue();
                    $currency_symbol = $worksheet->getCellByColumnAndRow(11, $row)->getValue();

                    // Get country name convert to country id
                    $country_id_list = $this->countriesModel->getCountryIdByName($country);
                    $country_id = $country_id_list->id;

                    // State name convert to state id
                    $state_id_list = $this->statesModel->getStateIdByName($state);
                    $state_id = $state_id_list->id;

                    $options = [
                        "company_name" => $company_name,
                        "address" => $address,
                        "city" => $city,
                        "state" => $state_id,
                        "zip" => $zip,
                        "country" => $country_id,
                        "phone" => $phone,
                        "website" => $website,
                        "gst_number" => $gst_number,
                        "gstin_number_first_two_digits" => $gstin_number_first_two_digits,
                        "currency_symbol" => $currency_symbol,
                        "currency" => $currency,
                    ];

                    $list_datas = $this->companysModel->getImportDetails($options);

                    if (!$list_datas) {
                        $data[] = array_merge($options, [
                            "buyer_type" => 0,
                            "group_ids" => 0,
                            "deleted" => 0,
                            "created_date" => date("Y-m-d"),
                        ]);
                    }
                }
            }
            if (!empty($data)) {
                $this->companysModel->insertBatch($data);
                return $this->response->setJSON(['success' => true, 'message' => 'Data Imported successfully']);
            }
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Error occurred during file upload.']);
    }
// Import CSV file
public function upload_file_csv()
{
    $csvMimes = ['application/vnd.ms-excel', 'text/plain', 'text/csv', 'text/tsv'];
    $file = $this->request->getFile('file');

    if ($file->isValid() && in_array($file->getMimeType(), $csvMimes) && !$file->hasMoved()) {
        $csvFile = fopen($file->getTempName(), 'r');

        // Skip first line
        fgetcsv($csvFile);

        $data = [];
        while (($line = fgetcsv($csvFile)) !== false) {
            // Get country name convert to country id
            $country_id_list = $this->countriesModel->getCountryIdByName($line[4]);
            $country_id = $country_id_list->id;

            // State name convert to state id
            $state_id_list = $this->statesModel->getStateIdByName($line[3]);
            $state_id = $state_id_list->id;

            $options = [
                "company_name" => $line[0],
                "address" => $line[1],
                "city" => $line[2],
                "state" => $state_id,
                "country" => $country_id,
                "zip" => $line[5],
                "phone" => $line[6],
                "website" => $line[7],
                "gst_number" => $line[8],
                "gstin_number_first_two_digits" => $line[9],
                "currency" => $line[10],
                "currency_symbol" => $line[11],
            ];

            $list_datas = $this->companysModel->getImportDetails($options);

            if (!$list_datas) {
                $data[] = array_merge($options, [
                    "buyer_type" => 0,
                    "group_ids" => 0,
                    "deleted" => 0,
                    "created_date" => date("Y-m-d"),
                ]);
            }
        }

        if (!empty($data)) {
            $this->companysModel->insertBatch($data);
            return $this->response->setJSON(['success' => true, 'message' => 'Data Imported successfully']);
        }

        fclose($csvFile);
    }

    return $this->response->setJSON(['success' => false, 'message' => 'Error occurred during file upload.']);
}

// Clients PO list
public function companys_po_list($company_id)
{
    if ($this->access_only_allowed_members() && $company_id) {
        $company_info = $this->companysModel->find($company_id);
        return view('companys/company_po_list', ['company_info' => $company_info, 'company_id' => $company_id]);
    }

    return redirect()->back();
}

// Additional method to check member access
protected function access_only_allowed_members()
{
    // Implement your access check logic here
    return true;
}
}

/* End of file clients.php */
/* Location: ./application/controllers/clients.php */