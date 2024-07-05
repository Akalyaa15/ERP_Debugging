<?php

namespace App\Controllers;

use App\Models\ChequeHandlerModel;
use App\Models\BankNameModel;
use App\Models\ChequeCategoriesModel;
use App\Models\ChequeStatusModel;
use App\Models\UsersModel;
use App\Models\VendorsModel;
use App\Models\ClientsModel;
use CodeIgniter\API\ResponseTrait;

class ChequeHandler extends BaseController
{
    use ResponseTrait;

    protected $chequeStatusModel;
    protected $bankNameModel;
    protected $chequeCategoriesModel;
    protected $usersModel;
    protected $vendorsModel;
    protected $clientsModel;
    protected $chequeHandlerModel;

    public function __construct()
    {
        $this->chequeStatusModel = new ChequeStatusModel();
        $this->bankNameModel = new BankNameModel();
        $this->chequeCategoriesModel = new ChequeCategoriesModel();
        $this->usersModel = new UsersModel();
        $this->vendorsModel = new VendorsModel();
        $this->clientsModel = new ClientsModel();
        $this->chequeHandlerModel = new ChequeHandlerModel();
    }

    public function index()
    {
        $this->check_module_availability("module_cheque_handler");

        $viewData = [
            'status_dropdown' => $this->_getChequeStatusDropdown(),
            'cheque_statuses' => $this->chequeStatusModel->getDetails()->getResult(),
            'members_dropdown' => $this->_getTeamMembersDropdown(),
            'rm_members_dropdown' => $this->_getRmMembersDropdown(),
            'clients_dropdown' => json_encode($this->_getClientsDropdown()),
            'others_dropdown' => $this->_getOthersDropdown(),
            'vendors_dropdown' => json_encode($this->_getVendorsDropdown()),
        ];

        if ($this->login_user->is_admin == "1" || $this->login_user->user_type == "staff" || $this->login_user->user_type == "resource") {
            if ($this->access_type != "all" && !in_array($this->login_user->id, $this->allowed_members)) {
                return redirect()->to('forbidden');
            }
        }

        echo view('cheque_handler/index', $viewData);
    }

    private function _getChequeStatusDropdown()
    {
        $statuses = $this->chequeStatusModel->getDetails()->getResult();

        $statusDropdown = [
            ['id' => '', 'text' => '- ' . lang('status') . ' -']
        ];

        foreach ($statuses as $status) {
            $statusDropdown[] = [
                'id' => $status->id,
                'text' => ($status->key_name ? lang($status->key_name) : $status->title)
            ];
        }

        return json_encode($statusDropdown);
    }

    public function modal_form()
    {
        helper(['form', 'url']);
        $id = $this->request->getVar('id');

        $validationRules = [
            'id' => 'numeric'
        ];
        if (!$this->validate($validationRules)) {
            return redirect()->to('error_page');
        }

        $viewData = [
            'bank_list_dropdown' => ['' => '-'] + $this->bankNameModel->getDropdownList(['title']),
            'cheque_category_dropdown' => ['' => '-'] + $this->chequeCategoriesModel->getDropdownList(['title'], 'id', ['status' => 'active']),
            'status_dropdown' => ['' => '-'] + $this->chequeStatusModel->getDropdownList(['title']),
            'tm_dropdown' => $this->usersModel->getDropdownList(['first_name', 'last_name'], 'id', ['user_type' => 'staff']),
            'rm_dropdown' => $this->usersModel->getDropdownList(['first_name', 'last_name'], 'id', ['user_type' => 'resource']),
            'vendors_dropdown' => $this->vendorsModel->getDropdownList(['company_name'], 'id'),
            'clients_dropdown' => $this->clientsModel->getDropdownList(['company_name'], 'id'),
            'model_info' => $this->chequeHandlerModel->getOne($this->request->getPost('id'))
        ];

        echo view('cheque_handler/modal_form', $viewData);
    }

    public function save()
    {
        helper(['form', 'url']);

        $memberType = $this->request->getPost('member_type');
        switch ($memberType) {
            case 'tm':
                $member = $this->request->getPost('tm_member');
                break;
            case 'om':
                $member = $this->request->getPost('rm_member');
                break;
            case 'clients':
                $member = $this->request->getPost('client_member');
                break;
            case 'vendors':
                $member = $this->request->getPost('vendor_member');
                break;
            case 'others':
                $member = 0;
                $data = [
            'first_name' => $this->request->getPost('first_name'),
            'last_name' => $this->request->getPost('last_name')
         ];
                break;
        }

        $id = $this->request->getPost('id');
        $targetPath = getSetting('timeline_file_path');
        $filesData = moveFilesFromTempDirToPermanentDir($targetPath, 'note');
        $newFiles = unserialize($filesData);

        $data = [
            'member_type' => $this->request->getPost('member_type'),
            'member_id' => $member,
            'cheque_number' => $this->request->getPost('cheque_no'),
            'bank_name' => $this->request->getPost('bank_name'),
            'payment_mode' => $this->request->getPost('payment_mode'),
            'account_number' => $this->request->getPost('account_number'),
            'cheque_category_id' => $this->request->getPost('cheque_category'),
            'amount' => unformat_currency($this->request->getPost('amount')),
            'issue_date' => $this->request->getPost('issue_date'),
            'drawn_on' => $this->request->getPost('drawn_on'),
            'valid_upto' => $this->request->getPost('valid_upto'),
            'description' => $this->request->getPost('description'),
            'status_id' => $this->request->getPost('status_id')
        ];

        if ($memberType == 'others') {
            $data['first_name'] = $this->request->getPost('first_name');
            $data['last_name'] = $this->request->getPost('last_name');
        }

        if ($id) {
            $noteInfo = $this->chequeHandlerModel->getOne($id);
            $timelineFilePath = getSetting('timeline_file_path');
            $newFiles = updateSavedFiles($timelineFilePath, $noteInfo->files, $newFiles);
        }

        $data['files'] = serialize($newFiles);
        $data['last_activity_user'] = $this->login_user->id;
        $data['last_activity'] = getCurrentUtcTime();

        $saveId = $this->chequeHandlerModel->save($data, $id);

        if ($saveId) {
            return $this->respond([
                'success' => true,
                'data' => $this->_rowData($saveId),
                'id' => $saveId,
                'message' => lang('record_saved')
            ]);
        } else {
            return $this->respond([
                'success' => false,
                'message' => lang('error_occurred')
            ]);
        }
    }

    public function delete()
    {
        $id = $this->request->getPost('id');

        $data = [
            'last_activity_user' => $this->login_user->id,
            'last_activity' => getCurrentUtcTime(),
        ];

        $saveId = $this->chequeHandlerModel->save($data, $id);

        if ($this->request->getPost('undo')) {
            if ($this->chequeHandlerModel->delete($id, true)) {
                return $this->respond([
                    'success' => true,
                    'data' => $this->_rowData($id),
                    'message' => lang('record_undone')
                ]);
            } else {
                return $this->respond([
                    'success' => false,
                    'message' => lang('error_occurred')
                ]);
            }
        } else {
            if ($this->chequeHandlerModel->delete($id)) {
                return $this->respond([
                    'success' => true,
                    'message' => lang('record_deleted')
                ]);
            } else {
                return $this->respond([
                    'success' => false,
                    'message' => lang('record_cannot_be_deleted')
                ]);
            }
        }
    }

    public function list_data()
    {
        $status = $this->request->getPost('status_id');
        $userId = $this->request->getPost('user_id');
        $userIds = $this->request->getPost('user_ids');
        $clientId = $this->request->getPost('client_id');
        $vendorId = $this->request->getPost('vendor_id');
        $otherId = $this->request->getPost('other_id');
        $startDate = $this->request->getPost('start_date');
        $endDate = $this->request->getPost('end_date');

        $options = [
            'status_id' => $status,
            'user_id' => $userIds ?: $userId,
            'client_id' => $clientId,
            'vendor_id' => $vendorId,
            'other_id' => $otherId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ];

        $listData = $this->chequeHandlerModel->getDetails($options)->getResult();
        $result = [];

        foreach ($listData as $data) {
            $result[] = $this->_makeRow($data);
        }

        return $this->response->setJSON(['data' => $result]);
    }

    private function _makeRow($data)
    {
        $row = [
            'id' => $data->id,
            'cheque_number' => $data->cheque_number,
            // Add other fields as needed
        ];

        return $row;
    }

    private function _rowData($id)
    {
        $options = ['id' => $id];
        $data = $this->chequeHandlerModel->getDetails($options)->getRow();
        return $this->_makeRow($data);
    }

    private function _make_row($data)
    {
        $deadline_text = "-";
        if ($data->valid_upto) {
            $deadline_text = format_to_date($data->valid_upto, false);
            if (date('Y-m-d') > $data->valid_upto) {
                $deadline_text = "<span class='text-danger'>" . $deadline_text . "</span> ";
            } elseif (date('Y-m-d') == $data->valid_upto) {
                $deadline_text = "<span class='text-warning'>" . $deadline_text . "</span> ";
            }
        }

        $files_link = "";
        if ($data->files) {
            $files = unserialize($data->files);
            if (count($files)) {
                foreach ($files as $file) {
                    $file_name = get_array_value($file, "file_name");
                    $link = " fa fa-" . get_file_icon(strtolower(pathinfo($file_name, PATHINFO_EXTENSION)));
                    $files_link .= js_anchor(" ", array('title' => "", "data-toggle" => "app-modal", "data-sidebar" => "0", "class" => "pull-left font-22 mr10 $link", "title" => remove_file_prefix($file_name), "data-url" => get_uri("notes/file_preview/" . $file_name)));
                }
            }
        }

        $checkbox_class = ($data->status_key_name === "done") ? "text-success" : "text-warning";
        $check_status = js_anchor("<span class='$checkbox_class'></span>", array('title' => "", "class" => "", "data-id" => $data->id, "data-value" => $data->status_key_name === "done" ? "1" : "3", "data-act" => "update-cheque-status-checkbox")) . $data->id;
        $status = js_anchor($data->status_key_name ? lang($data->status_key_name) : $data->status_title, array('title' => "", "class" => "", "data-id" => $data->id, "data-value" => $data->status_id, "data-act" => "update-cheque-status"));

        // Handle member details based on type
        $cheque_handler_member = "";
        switch ($data->member_type) {
            case 'tm':
                if ($data->member_id) {
                    $list_data = $this->usersModel->find($data->member_id);
                    if ($list_data) {
                        $cheque_handler_member .= lang("team_member") . ": " . $list_data->first_name . " " . $list_data->last_name;
                    }
                }
                break;
            case 'om':
                if ($data->member_id) {
                    $list_data = $this->usersModel->find($data->member_id);
                    if ($list_data) {
                        $cheque_handler_member .= lang("outsource_member") . ": " . $list_data->first_name . " " . $list_data->last_name;
                    }
                }
                break;
            case 'clients':
                if ($data->member_id) {
                    $list_data = $this->clientsModel->find($data->member_id);
                    if ($list_data) {
                        $cheque_handler_member .= lang("client_company") . ": " . $list_data->company_name . "<br>";
                    }
                }
                break;
            case 'vendors':
                if ($data->member_id) {
                    $list_data = $this->vendorsModel->find($data->member_id);
                    if ($list_data) {
                        $cheque_handler_member .= lang("vendor_company") . ": " . $list_data->company_name . "<br>";
                    }
                }
                break;
            case 'others':
                if ($data->first_name) {
                    $cheque_handler_member .= lang("other_contact") . ": " . $data->first_name . " " . $data->last_name;
                }
                break;
        }

        // Last activity user details
        $last_activity_by_user_name = "-";
        if ($data->last_activity_user) {
            $last_activity_user_data = $this->usersModel->find($data->last_activity_user);
            if ($last_activity_user_data) {
                $last_activity_image_url = get_avatar($last_activity_user_data->image);
                $last_activity_user = "<span class='avatar avatar-xs mr10'><img src='$last_activity_image_url' alt='...'></span> $last_activity_user_data->first_name $last_activity_user_data->last_name";

                switch ($last_activity_user_data->user_type) {
                    case "resource":
                        $last_activity_by_user_name = get_rm_member_profile_link($data->last_activity_user, $last_activity_user);
                        break;
                    case "client":
                        $last_activity_by_user_name = get_client_contact_profile_link($data->last_activity_user, $last_activity_user);
                        break;
                    case "staff":
                        $last_activity_by_user_name = get_team_member_profile_link($data->last_activity_user, $last_activity_user);
                        break;
                    case "vendor":
                        $last_activity_by_user_name = get_vendor_contact_profile_link($data->last_activity_user, $last_activity_user);
                        break;
                }
            }
        }

        $last_activity_date = $data->last_activity ? format_to_relative_time($data->last_activity) : "-";

        return [
            $data->id,
            $data->description,
            $cheque_handler_member,
            $data->bank_name,
            $data->account_number,
            $data->cheque_number,
            $data->cheque_category,
            (get_setting("currency_symbol") . $data->amount),
            format_to_date($data->issue_date, false),
            $data->issue_date,
            $data->drawn_on,
            $deadline_text,
            $status,
            $files_link,
            $last_activity_by_user_name,
            $last_activity_date,
            modal_anchor(get_uri("Cheque_handler/modal_form"), "<i class='fa fa-pencil'></i>", array("class" => "edit", "title" => lang('edit_cheque'), "data-post-id" => $data->id))
                . js_anchor("<i class='fa fa-times fa-fw'></i>", array('title' => lang('delete_cheque'), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("Cheque_handler/delete"), "data-action" => "delete-confirmation")),
        ];
    }

    public function save_task_status($id = 0)
    {
        $this->access_only_team_members();
        
        $data = [
            "status_id" => $this->request->getPost('value')
        ];

        if ($this->chequeHandlerModel->save($data, $id)) {
            return $this->respond([
                "success" => true,
                "data" => $this->_row_data($id),
                'id' => $id,
                "message" => lang('record_saved')
            ]);
        } else {
            return $this->respond([
                "success" => false,
                "message" => lang('error_occurred')
            ]);
        }
    }
    private function _get_clients_dropdown()
    {
        $clients_dropdown = [['id' => '', 'text' => '- ' . lang('client') . ' -']];
        $clients = $this->clientsModel->getDropdownList(['company_name']);
        foreach ($clients as $key => $value) {
            $clients_dropdown[] = ['id' => $key, 'text' => $value];
        }
        return json_encode($clients_dropdown);
    }

    private function _get_others_dropdown()
    {
        $others_members = $this->chequeHandlerModel->where(['deleted' => 0, 'member_type' => 'others'])->findAll();

        $others_members_dropdown = [['id' => '', 'text' => '- ' . lang('others') . ' -']];
        foreach ($others_members as $others_member) {
            $others_members_dropdown[] = ['id' => $others_member->id, 'text' => $others_member->first_name . ' ' . $others_member->last_name];
        }
        return json_encode($others_members_dropdown);
    }


    private function _get_vendors_dropdown()
    {
        $vendors_dropdown = [['id' => '', 'text' => '- ' . lang('vendor') . ' -']];
        $vendors = $this->vendorsModel->getDropdownList(['company_name']);
        foreach ($vendors as $key => $value) {
            $vendors_dropdown[] = ['id' => $key, 'text' => $value];
        }
        return json_encode($vendors_dropdown);
    }

    private function _get_team_members_dropdown()
    {
        $team_members = $this->usersModel->where(['deleted' => 0, 'user_type' => 'staff'])->findAll();

        $members_dropdown = [['id' => '', 'text' => '- ' . lang('member') . ' -']];
        foreach ($team_members as $team_member) {
            $members_dropdown[] = ['id' => $team_member->id, 'text' => $team_member->first_name . ' ' . $team_member->last_name];
        }
        return json_encode($members_dropdown);
    }
    private function _get_rm_members_dropdown()
    {
        $rm_members = $this->usersModel->where(['deleted' => 0, 'user_type' => 'resource'])->findAll();

        $rm_members_dropdown = [['id' => '', 'text' => '- ' . lang('outsource_member') . ' -']];
        foreach ($rm_members as $rm_member) {
            $rm_members_dropdown[] = ['id' => $rm_member->id, 'text' => $rm_member->first_name . ' ' . $rm_member->last_name];
        }
        return json_encode($rm_members_dropdown);
    }
}