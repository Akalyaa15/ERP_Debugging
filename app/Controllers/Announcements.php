<?php

namespace App\Controllers;

use App\Models\AnnouncementsModel;
use App\Models\ClientsModel;
use App\Models\PartnersModel;
use App\Models\VendorsModel;
use App\Models\UsersModel;

class Announcements extends BaseController
{
    protected $announcementsModel;
    protected $clientsModel;
    protected $partnersModel;
    protected $vendorsModel;
    protected $usersModel;

    public function __construct()
    {
        $this->announcementsModel = new AnnouncementsModel();
        $this->clientsModel = new ClientsModel();
        $this->partnersModel = new PartnersModel();
        $this->vendorsModel = new VendorsModel();
        $this->usersModel = new UsersModel();
        $this->init_permission_checker("announcement");
    }

    public function index()
    {
        $this->check_module_availability("module_announcement");

        $view_data['show_add_button'] = ($this->access_type === "all");
        $view_data['show_option'] = ($this->access_type === "all");

        return view('announcements/index', $view_data);
    }

    public function form($id = 0)
    {
        $this->access_only_allowed_members();

        $view_data['model_info'] = $this->announcementsModel->find($id);

        $model_info->share_with_specific = "";
        if ($model_info->share_with && !in_array($model_info->share_with, ["all_members", "all_vendors", "all_clients", "all_resource", "all_partners"])) {
            $share_with_explode = explode(":", $model_info->share_with);
            $model_info->share_with_specific = $share_with_explode[0];
        }

        $view_data['members_and_teams_dropdown'] = json_encode(get_team_members_and_teams_select2_data_list());
        $view_data['outsource_members_and_teams_dropdown'] = json_encode(get_outsource_members_and_teams_select2_data_list());

        $view_data['vendors_dropdown'] = $this->get_dropdown_options($this->vendorsModel);
        $view_data['clients_dropdown'] = $this->get_dropdown_options($this->clientsModel);
        $view_data['partners_dropdown'] = $this->get_dropdown_options($this->partnersModel);

        return view('announcements/modal_form', $view_data);
    }

    public function view($id = "")
    {
        if ($id) {
            $options = ['id' => $id];
            $options = $this->_prepare_access_options($options);
            $announcement = $this->announcementsModel->get_details($options)->getRow();

            if ($announcement) {
                $view_data['announcement'] = $announcement;
                $this->announcementsModel->mark_as_read($id, $this->login_user->id);

                return view('announcements/view', $view_data);
            }
        }

        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }

    private function _prepare_access_options($options)
    {
        if ($this->access_type !== "all") {
            $options['share_with'] = match ($this->login_user->user_type) {
                "staff" => "all_members",
                "client" => "all_clients",
                "vendor" => "all_vendors",
                default => "none",
            };
        }

        return $options;
    }

    public function mark_as_read($id)
    {
        $this->announcementsModel->mark_as_read($id, $this->login_user->id);
    }

    public function save()
    {
        $this->access_only_allowed_members();

        $validation = \Config\Services::validation();
        $validation->setRules([
            'title' => 'required',
            'start_date' => 'required',
            'end_date' => 'required'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return redirect()->back()->withInput()->with('errors', $validation->getErrors());
        }

        $id = $this->request->getPost('id');
        $share_with = $this->request->getPost('share_with');

        if ($share_with == "specific") {
            $share_with = $this->request->getPost('share_with_specific');
        } elseif ($share_with == "resource_specific") {
            $share_with = $this->request->getPost('share_with_resource_specific');
        } elseif ($share_with == "specific_partner_contacts") {
            $share_with = $this->request->getPost('share_with_specific_partner_contact');
        } elseif ($share_with == "specific_client_contacts") {
            $share_with = $this->request->getPost('share_with_specific_client_contact');
        } elseif ($share_with == "specific_vendor_contacts") {
            $share_with = $this->request->getPost('share_with_specific_vendor_contact');
        }

        $target_path = get_setting("timeline_file_path");
        $files_data = move_files_from_temp_dir_to_permanent_dir($target_path, "announcement");
        $new_files = unserialize($files_data);
        $partner_id = $this->request->getPost('partner_id');
        $client_id = $this->request->getPost('client_id');
        $vendor_id = $this->request->getPost('vendor_id');

        $data = [
            'title' => $this->request->getPost('title'),
            'description' => decode_ajax_post_data($this->request->getPost('description')),
            'start_date' => $this->request->getPost('start_date'),
            'end_date' => $this->request->getPost('end_date'),
            'created_by' => $this->login_user->id,
            'created_at' => gmdate("Y-m-d H:i:s"),
            'share_with' => $share_with,
            'partner_id' => $partner_id ? $partner_id : 0,
            'client_id' => $client_id ? $client_id : 0,
            'vendor_id' => $vendor_id ? $vendor_id : 0
        ];

        if ($id) {
            $expense_info = $this->announcementsModel->find($id);
            $timeline_file_path = get_setting("timeline_file_path");
            $new_files = update_saved_files($timeline_file_path, $expense_info->files, $new_files);
        }

        $data['files'] = serialize($new_files);

        if (!$id) {
            $data['read_by'] = 0;
        }

        if ($this->announcementsModel->save($data)) {
            if (!$id) {
                if ($data['share_with']) {
                    log_notification("new_announcement_created", ["announcement_id" => $this->announcementsModel->insertID()]);
                }
            }

            return redirect()->to(site_url('announcements/form/' . $this->announcementsModel->insertID()))->with('message', lang('record_saved'));
        } else {
            return redirect()->to(site_url('announcements/form'))->with('error_message', lang('error_occurred'));
        }
    }

    public function upload_file()
    {
        $this->access_only_allowed_members();
        upload_file_to_temp();
    }

    public function validate_announcement_file()
    {
        return validate_post_file($this->request->getPost('file_name'));
    }

    public function download_announcement_files($id = 0)
    {
        $options = ['id' => $id];
        $options = $this->_prepare_access_options($options);
        $info = $this->announcementsModel->get_details($options)->getRow();

        download_app_files(get_setting("timeline_file_path"), $info->files);
    }

    public function delete()
    {
        $this->access_only_allowed_members();

        $validation = \Config\Services::validation();
        $validation->setRules(['id' => 'required|numeric']);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }

        $id = $this->request->getPost('id');

        if ($this->request->getPost('undo')) {
            if ($this->announcementsModel->delete($id, true)) {
                return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_undone')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($this->announcementsModel->delete($id)) {
                return $this->response->setJSON(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }

    public function list_data()
    {
        $is_client = ($this->login_user->user_type == "client");
        $is_vendor = ($this->login_user->user_type == "vendor");
        $is_resource = ($this->login_user->user_type == "resource");
        $is_partner = ($this->login_user->partner_id);

        $options = [
            'user_id' => $this->login_user->id,
            'team_ids' => $this->login_user->team_ids,
            'client_id' => $is_client ? $this->login_user->client_id : "",
            'vendor_id' => $is_vendor ? $this->login_user->vendor_id : "",
            'partner_id' => $is_partner ? $this->login_user->partner_id : "",
            'access_type' => $this->access_type
        ];

        $list_data = $this->announcementsModel->get_details($options)->getResult();
        $result = array_map([$this, '_make_row'], $list_data);

        return $this->response->setJSON(['data' => $result]);
    }

    private function _row_data($id)
    {
        $options = ['id' => $id];
        $data = $this->announcementsModel->get_details($options)->getRow();

        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $image_url = get_avatar($data->created_by_avatar);
        $user = "<span class='avatar avatar-xs mr10'><img src='$image_url' alt=''></span> $data->created_by_user";
        $confirmed_by_array = explode(",", $data->read_by);
        $confirmed_rejected_users = $this->_get_confirmed_and_rejected_users_list($confirmed_by_array);
        $confirmed_by = $confirmed_rejected_users['confirmed_by'];
        $option = "";

        if ($this->access_type === "all") {
            $option = anchor(
                base_url("announcements/form/" . $data->id),
                "<i class='fa fa-pencil'></i>",
                ["class" => "edit", "title" => lang('edit_announcement')]
            )
            . js_anchor(
                "<i class='fa fa-times fa-fw'></i>",
                [
                    'title' => lang('delete_announcement'),
                    "class" => "delete",
                    "data-id" => $data->id,
                    "data-action-url" => base_url("announcements/delete"),
                    "data-action" => "delete-confirmation"
                ]
            );
        }

        return [
            $data->id,
            anchor(base_url("announcements/view/" . $data->id), $data->title, ["class" => "", "title" => lang('view')]),
            get_team_member_profile_link($data->created_by, $user),
            $confirmed_by,
            $data->start_date,
            format_to_date($data->start_date, false),
            $data->end_date,
            format_to_date($data->end_date, false),
            $option
        ];
    }

    private function _get_confirmed_and_rejected_users_list($confirmed_by_array)
    {
        $confirmed_by = "";

        $response_by_users = $this->announcementsModel->get_response_by_users($confirmed_by_array);
        if ($response_by_users) {
            foreach ($response_by_users as $user) {
                $image_url = get_avatar($user->image);
                $response_by_user = "<span data-toggle='tooltip' title='" . $user->member_name . "' class='avatar avatar-xs mr10'><img src='$image_url' alt='...'></span>";

                if ($user->user_type === "client") {
                    $profile_link = get_client_contact_profile_link($user->id, $response_by_user);
                } elseif ($user->user_type === "vendor") {
                    $profile_link = get_vendor_contact_profile_link($user->id, $response_by_user);
                } elseif ($user->user_type === "resource") {
                    $profile_link = get_rm_member_profile_link($user->id, $response_by_user);
                } else {
                    $profile_link = get_team_member_profile_link($user->id, $response_by_user);
                }

                if (in_array($user->id, $confirmed_by_array)) {
                    $confirmed_by .= $profile_link;
                } else {
                    $rejected_by .= $profile_link;
                }
            }
        }

        return ["confirmed_by" => $confirmed_by];
    }

    // Get all contacts of a selected client
    public function get_all_contacts_of_client($client_id)
    {
        $client_access_info = $this->get_access_info("client");
        if ($client_id && ($this->login_user->is_admin || $client_access_info->access_type == "all")) {
            $client_contacts = $this->usersModel->where([
                "user_type" => "client",
                "status" => "active",
                "client_id" => $client_id,
                "deleted" => 0
            ])->findAll();

            $client_contacts_array = [];
            if ($client_contacts) {
                foreach ($client_contacts as $contact) {
                    $client_contacts_array[] = [
                        "type" => "contact",
                        "id" => "contact:" . $contact->id,
                        "text" => $contact->first_name . " " . $contact->last_name
                    ];
                }
            }

            return $this->response->setJSON($client_contacts_array);
        }
    }

    // Get all contacts of a selected partner
    public function get_all_contacts_of_partner($partner_id)
    {
        $client_access_info = $this->get_access_info("client");
        if ($partner_id && ($this->login_user->is_admin || $client_access_info->access_type == "all")) {
            $partner_contacts = $this->usersModel->where([
                "user_type" => "client",
                "status" => "active",
                "partner_id" => $partner_id,
                "deleted" => 0
            ])->findAll();

            $partner_contacts_array = [];
            if ($partner_contacts) {
                foreach ($partner_contacts as $contact) {
                    $partner_contacts_array[] = [
                        "type" => "partner_contact",
                        "id" => "partner_contact:" . $contact->id,
                        "text" => $contact->first_name . " " . $contact->last_name
                    ];
                }
            }

            return $this->response->setJSON($partner_contacts_array);
        }
    }

    // Get all contacts of a selected vendor
    public function get_all_contacts_of_vendor($vendor_id)
    {
        $vendor_access_info = $this->get_access_info("vendor");
        if ($vendor_id && ($this->login_user->is_admin || $vendor_access_info->access_type == "all")) {
            $vendor_contacts = $this->usersModel->where([
                "user_type" => "vendor",
                "status" => "active",
                "vendor_id" => $vendor_id,
                "deleted" => 0
            ])->findAll();

            $vendor_contacts_array = [];
            if ($vendor_contacts) {
                foreach ($vendor_contacts as $contact) {
                    $vendor_contacts_array[] = [
                        "type" => "vendor_contact",
                        "id" => "vendor_contact:" . $contact->id,
                        "text" => $contact->first_name . " " . $contact->last_name
                    ];
                }
            }

            return $this->response->setJSON($vendor_contacts_array);
        }
    }
}