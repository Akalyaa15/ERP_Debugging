<?php

namespace App\Controllers;

use App\Models\UsersModel;
use App\Models\LeaveTypesModel;
use App\Models\LeaveApplicationsModel;
use App\Models\TicketsModel;
use CodeIgniter\HTTP\ResponseInterface;

class Leaves extends BaseController
{
    protected $usersmodel;
    protected $leavetypesmodel;
    protected $leaveapplicationsmodel;
    protected $ticketsmodel;

    public function __construct()
    {
        parent::__construct();
        $this->usersmodel = new UsersModel();
        $this->leavetypesmodel = new LeaveTypesModel();
        $this->leaveapplicationsmodel = new LeaveApplicationsModel();
        $this->ticketsmodel = new TicketsModel();

        $this->access_only_team_members();
        $this->init_permission_checker("leave");
    }

    // Only admin or assigned members can access/manage other member's leave
    // None admin users who have limited permission to manage other members leaves, can't manage his/her own leaves
    protected function access_only_allowed_members($user_id = 0)
    {
        if ($this->access_type !== "all") {
            if ($user_id === $this->login_user->id || !in_array($user_id, $this->allowed_members)) {
                return redirect()->to('forbidden');
            }
        }
    }

    protected function can_delete_leave_application()
    {
        return $this->login_user->is_admin || $this->login_user->permissions['can_delete_leave_application'] == "1";
    }

    public function index()
    {
        $this->check_module_availability("module_leave");
        return view("leaves/index");
    }

    // Load assign leave modal 
    public function assign_leave_modal_form($applicant_id = 0)
    {
        $view_data = [];

        if ($applicant_id) {
            $view_data['team_members_info'] = $this->usersmodel->find($applicant_id);
        } else {
            $where = ["user_type" => "staff"];
            if ($this->access_type !== "all") {
                $where['id !='] = $this->login_user->id;
                $where['where_in'] = ['id' => $this->allowed_members];
            }
            $view_data['team_members_dropdown'] = [""] + $this->usersmodel->get_dropdown_list(["first_name", "last_name"], "id", $where);
        }

        $view_data['leave_types_dropdown'] = [""] + $this->leavetypesmodel->get_dropdown_list(["title"], "id", ["status" => "active"]);
        $view_data['form_type'] = "assign_leave";
        return view('leaves/modal_form', $view_data);
    }

    // All team members can apply for leave
    public function apply_leave_modal_form()
    {
        $view_data['leave_types_dropdown'] = [""] + $this->leavetypesmodel->get_dropdown_list(["title"], "id", ["status" => "active"]);
        $view_data['form_type'] = "apply_leave";
        return view('leaves/modal_form', $view_data);
    }

    // Save: assign leave 
    public function assign_leave()
    {
        $leave_data = $this->_prepare_leave_form_data();
        $applicant_id = $this->request->getPost('applicant_id');
        $leave_data['applicant_id'] = $applicant_id;
        $leave_data['created_by'] = $this->login_user->id;
        $leave_data['checked_by'] = $this->login_user->id;
        $leave_data['checked_at'] = $leave_data['created_at'];
        $leave_data['status'] = "approved";

        // Hasn't full access? Allow to update only specific member's record, excluding logged in user's own record
        $this->access_only_allowed_members($leave_data['applicant_id']);

        $save_id = $this->leaveapplicationsmodel->save($leave_data);
        if ($save_id) {
            log_notification("leave_assigned", ["leave_id" => $save_id, "to_user_id" => $applicant_id]);
            return $this->response->setJSON(["success" => true, "data" => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(["success" => false, 'message' => lang('error_occurred')]);
        }
    }

    /* save: apply leave */

    <?php

    namespace App\Controllers;
    
    use App\Models\UsersModel;
    use App\Models\LeaveTypesModel;
    use App\Models\LeaveApplicationsModel;
    use App\Models\TicketsModel;
    
    class Leaves extends BaseController
    {
        protected $usersmodel;
        protected $leavetypesmodel;
        protected $leaveapplicationsmodel;
        protected $ticketsmodel;
    
        public function __construct()
        {
            parent::__construct();
            $this->usersmodel = new UsersModel();
            $this->leavetypesmodel = new LeaveTypesModel();
            $this->leaveapplicationsmodel = new LeaveApplicationsModel();
            $this->ticketsmodel = new TicketsModel();
    
            $this->access_only_team_members();
            $this->init_permission_checker("leave");
        }
    
        public function apply_leave()
        {
            $leave_data = $this->_prepare_leave_form_data();
            $leave_data['applicant_id'] = $this->login_user->id;
            $leave_data['created_by'] = 0;
            $leave_data['checked_at'] = "0000:00:00";
            $leave_data['status'] = "pending";
            $leave_data['line_manager'] = $this->login_user->line_manager;
    
            if (!$this->login_user->line_manager) {
                return $this->response->setJSON(['success' => false, 'message' => lang('lien_manager_not_assign')]);
            }
    
            $leave_data = clean_data($leave_data);
    
            $save_id = $this->leaveapplicationsmodel->save($leave_data);
            if ($save_id) {
                log_notification("leave_application_submitted", ["leave_id" => $save_id]);
                return $this->response->setJSON(["success" => true, "data" => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
            } else {
                return $this->response->setJSON(["success" => false, 'message' => lang('error_occurred')]);
            }
        }
    
        public function remarks($leave_id = 0, $status, $applicant_id)
        {
            $where = ["user_type" => "staff", "status" => "active"];
            $view_data['team_members_dropdown'] = ["" => "-"] + $this->usersmodel->get_dropdown_list(["first_name", "last_name"], "id", $where);
    
            $view_data['applicant_id'] = $applicant_id;
            $view_data['leave_id'] = $leave_id;
            $view_data['status'] = $status;
    
            return view('leaves/remarks', $view_data);
        }
    
        public function save_remarks()
        {
            $id = $this->request->getPost('id');
            $status = $this->request->getPost('status');
            $description = $this->request->getPost('description');
            $now = get_current_utc_time();
    
            $leave_data = [
                "checked_by" => $this->login_user->id,
                "status" => $status,
            ];
    
            if ($status === "approve_by_manager") {
                $leave_data['manager_remarks'] = $description;
                $leave_data['alternate_id'] = $this->request->getPost('alternate_id');
                $leave_data['line_manager'] = $this->login_user->id;
            }
            if ($status === "rejected") {
                $leave_data['manager_remarks'] = $description;
                $leave_data['line_manager'] = $this->login_user->id;
            }
            if ($status === "reject_admin") {
                $leave_data["status"] = "rejected";
                $leave_data["hr_remarks"] = $description;
            }
            if ($status === "approve_admin") {
                $leave_data["status"] = "approved";
                $leave_data["hr_remarks"] = $description;
            }
    
            $voucher_id = $this->leaveapplicationsmodel->save($leave_data, $id);
            if ($voucher_id) {
                $notification_options = ["leave_id" => $id, "to_user_id" => $this->request->getPost('applicant_id')];
    
                if ($status == "rejected" || $status == "reject_admin") {
                    log_notification("leave_rejected", $notification_options);
                } else if ($status == "approve_admin") {
                    log_notification("leave_approved", $notification_options);
                } else if ($status == "approve_by_manager") {
                    log_notification("leave_approved_by_manager", $notification_options);
                    $notification_options = ["leave_id" => $id, "to_user_id" => $this->request->getPost('alternate_id')];
                    log_notification("leave_alternate", $notification_options);
                }
    
                return $this->response->setJSON(['success' => true, 'message' => lang('record_saved')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
            }
        }
    
        /* Prepare common data for a leave application both for apply a leave or assign a leave */
        private function _prepare_leave_form_data()
        {
            $this->validate([
                'leave_type_id' => 'required|numeric',
                'reason' => 'required'
            ]);
    
            $duration = $this->request->getPost('duration');
            $hours_per_day = get_setting('company_working_hours_for_one_day');
            $hours = 0;
            $days = 0;
    
            if ($duration === "multiple_days") {
                $this->validate([
                    'start_date' => 'required',
                    'end_date' => 'required'
                ]);
    
                $start_date = $this->request->getPost('start_date');
                $end_date = $this->request->getPost('end_date');
    
                // Calculate total days
                $d_start = new \DateTime($start_date);
                $d_end = new \DateTime($end_date);
                $d_diff = $d_start->diff($d_end);
    
                $days = $d_diff->days + 1;
                $hours = $days * $hours_per_day;
            } else if ($duration === "hours") {
                $this->validate([
                    'hour_date' => 'required'
                ]);
    
                $start_date = $this->request->getPost('hour_date');
                $end_date = $start_date;
                $hours = $this->request->getPost('hours');
                $days = $hours / $hours_per_day;
            } else {
                $this->validate([
                    'single_date' => 'required'
                ]);
    
                $start_date = $this->request->getPost('single_date');
                $end_date = $start_date;
                $hours = $hours_per_day;
                $days = 1;
            }
    
            $now = get_current_utc_time();
            $leave_data = [
                "leave_type_id" => $this->request->getPost('leave_type_id'),
                "start_date" => $start_date,
                "end_date" => $end_date,
                "reason" => $this->request->getPost('reason'),
                "created_by" => $this->login_user->id,
                "created_at" => $now,
                "total_hours" => $hours,
                "total_days" => $days
            ];
    
            return $leave_data;
        }
    
        // Load pending approval tab
        public function pending_approval()
        {
            $view_data['team_members_dropdown'] = json_encode($this->_get_members_dropdown_list_for_filter());
            $view_data['line_manager_dropdown'] = json_encode($this->_get_line_manager_dropdown_list_for_filter());
            $view_data['leave_types_dropdown'] = json_encode($this->_get_leave_types_dropdown_list_for_filter());
            return view("leaves/pending_approval", $view_data);
        }
    
        // Load all applications tab 
        public function all_applications()
        {
            $view_data['team_members_dropdown'] = json_encode($this->_get_members_dropdown_list_for_filter());
            $view_data['leave_types_dropdown'] = json_encode($this->_get_leave_types_dropdown_list_for_filter());
            return view("leaves/all_applications", $view_data);
        }
    
        // Load leave summary tab
        public function summary()
        {
            $view_data['team_members_dropdown'] = json_encode($this->_get_members_dropdown_list_for_filter());
            $view_data['leave_types_dropdown'] = json_encode($this->_get_leave_types_dropdown_list_for_filter());
            return view("leaves/summary", $view_data);
        }
    
        // List of pending leave application. Prepared for datatable
        public function pending_approval_list_data()
        {
            $applicant_id = $this->request->getPost('applicant_id');
            $line_managers = $this->request->getPost('line_managers');
            $leave_type_id = $this->request->getPost('leave_type_id');
            $options = [
                "statuss" => "pending_approve_by_manager",
                "access_type" => $this->access_type,
                "allowed_members" => $this->allowed_members,
                "applicant_id" => $applicant_id,
                "leave_type_id" => $leave_type_id,
                "line_managers" => $line_managers
            ];
            $list_data = $this->leaveapplicationsmodel->get_list($options);
    
            $result = [];
            foreach ($list_data as $data) {
                $result[] = $this->_make_row($data);
            }
            return $this->response->setJSON(['data' => $result]);
        }
    
        // List of all leave application. Prepared for datatable 
        public function all_application_list_data()
        {
            $start_date = $this->request->getPost('start_date');
            $end_date = $this->request->getPost('end_date');
            $applicant_id = $this->request->getPost('applicant_id');
            $options = [
                "start_date" => $start_date,
                "end_date" => $end_date,
                "applicant_id" => $applicant_id,
                "login_user_id" => $this->login_user->id,
                "access_type" => $this->access_type,
                "allowed_members" => $this->allowed_members
            ];
    
            if ($applicant_id == 'line_manager') {
                $options = [
                    "start_date" => $start_date,
                    "end_date" => $end_date,
                    "line_manager" => $this->login_user->id,
                    "login_user_id" => $this->login_user->id,
                    "access_type" => 'all',
                    "allowed_members" => $this->allowed_members
                ];
            }
    
            $list_data = $this->leaveapplicationsmodel->get_list($options);
            $result = [];
            foreach ($list_data as $data) {
                $result[] = $this->_make_row($data);
            }
            return $this->response->setJSON(['data' => $result]);
        }
    
    // list of leave summary. prepared for datatable
 
    public function summary_list_data()
    {
        $start_date = $this->request->getPost('start_date');
        $end_date = $this->request->getPost('end_date');
        $applicant_id = $this->request->getPost('applicant_id');
        $leave_type_id = $this->request->getPost('leave_type_id');

        $options = [
            "start_date" => $start_date,
            "end_date" => $end_date,
            "access_type" => $this->access_type,
            "allowed_members" => $this->allowed_members,
            "applicant_id" => $applicant_id,
            "leave_type_id" => $leave_type_id
        ];

        $list_data = $this->leaveApplicationsModel->get_summary($options)->getResult();

        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row_for_summary($data);
        }
        return $this->response->setJSON(['data' => $result]);
    }

    private function _row_data($id)
    {
        $options = ["id" => $id];
        $data = $this->leaveApplicationsModel->get_list($options)->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $meta_info = $this->_prepare_leave_info($data);
        $option_icon = "fa-info";
        if ($data->status === "pending") {
            $option_icon = "fa-bolt";
        }

        $actions = modal_anchor(
            base_url("leaves/application_details"),
            "<i class='fa $option_icon'></i>",
            ["class" => "edit", "title" => lang('application_details'), "data-post-id" => $data->id]
        );

        $can_manage_application = false;
        if ($this->access_type === "all") {
            $can_manage_application = true;
        } else if (in_array($data->applicant_id, $this->allowed_members) && $data->applicant_id !== $this->login_user->id) {
            $can_manage_application = true;
        }

        if ($this->can_delete_leave_application() && $can_manage_application) {
            $actions .= js_anchor(
                "<i class='fa fa-times fa-fw'></i>",
                ['title' => lang('delete'), "class" => "delete", "data-id" => $data->id, "data-action-url" => base_url("leaves/delete"), "data-action" => "delete-confirmation"]
            );
        }

        if ($data->applicant_user_type == "staff") {
            return [
                get_team_member_profile_link($data->applicant_id, $meta_info->applicant_meta),
                $meta_info->leave_type_meta,
                $meta_info->date_meta,
                $meta_info->duration_meta,
                $meta_info->status_meta,
                $meta_info->applied_date,
                $actions
            ];
        } elseif ($data->applicant_user_type == "resource") {
            return [
                get_rm_member_profile_link($data->applicant_id, $meta_info->applicant_meta),
                $meta_info->leave_type_meta,
                $meta_info->date_meta,
                $meta_info->duration_meta,
                $meta_info->status_meta,
                $meta_info->applied_date,
                $actions
            ];
        }
    }

    private function _make_row_for_summary($data)
    {
        $meta_info = $this->_prepare_leave_info($data);

        return [
            get_team_member_profile_link($data->applicant_id, $meta_info->applicant_meta),
            $meta_info->leave_type_meta,
            $meta_info->duration_meta
        ];
    }

    private function _prepare_leave_info($data)
    {
        $image_url = get_avatar($data->applicant_avatar);
        $data->applicant_meta = "<span class='avatar avatar-xs mr10'><img src='$image_url' alt=''></span>" . $data->applicant_name;

        if (isset($data->status)) {
            if ($data->status === "pending") {
                $status_class = "label-warning";
            } elseif ($data->status === "approved" || $data->status === "approve_by_manager") {
                $status_class = "label-success";
            } elseif ($data->status === "rejected") {
                $status_class = "label-danger";
            } else {
                $status_class = "label-default";
            }
            $data->status_meta = "<span class='label $status_class'>" . lang($data->status) . "</span>";
        }

        if (isset($data->start_date)) {
            $date = format_to_date($data->start_date, FALSE);
            if ($data->start_date != $data->end_date) {
                $date = sprintf(lang('start_date_to_end_date_format'), format_to_date($data->start_date, FALSE), format_to_date($data->end_date, FALSE));
            }
            $data->date_meta = $date;
        }

        $duration = $data->total_days > 1 ? $data->total_days . " " . lang("days") : $data->total_days . " " . lang("day");

        if ($data->total_hours > 1) {
            $duration .= " (" . $data->total_hours . " " . lang("hours") . ")";
        } else {
            $duration .= " (" . $data->total_hours . " " . lang("hour") . ")";
        }

        $data->applied_date = $data->created_at;
        $data->duration_meta = $duration;
        $data->leave_type_meta = "<span style='background-color:" . $data->leave_type_color . "' class='color-tag pull-left'></span>" . $data->leave_type_title;
        return $data;
    }

    public function application_details()
    {
        $this->validate([
            'id' => 'required|numeric'
        ]);

        $application_id = $this->request->getPost('id');
        $info = $this->leaveApplicationsModel->get_details_info($application_id);
        if (!$info) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $can_manage_application = false;
        if ($this->access_type === "all") {
            $can_manage_application = true;
        } elseif (in_array($info->applicant_id, $this->allowed_members) && $info->applicant_id !== $this->login_user->id) {
            $can_manage_application = true;
        }
        $view_data['show_approve_reject'] = $can_manage_application;

        if (!$can_manage_application && $info->applicant_id !== $this->login_user->id) {
            return redirect()->to('forbidden');
        }

        $view_data['leave_info'] = $this->_prepare_leave_info($info);
        return view("leaves/application_details", $view_data);
    }

    public function update_status()
    {
        $this->validate([
            'id' => 'required|numeric',
            'status' => 'required'
        ]);

        $application_id = $this->request->getPost('id');
        $status = $this->request->getPost('status');
        $now = gmdate('Y-m-d H:i:s');

        $leave_data = [
            'checked_by' => $this->login_user->id,
            'checked_at' => $now,
            'status' => $status
        ];

        $application_info = $this->leaveApplicationsModel->find($application_id);

        if ($status === "approved" || $status === "rejected") {
            // $this->access_only_allowed_members($application_info->applicant_id);
        } elseif ($status === "canceled" && $application_info->applicant_id != $this->login_user->id) {
            return redirect()->to('forbidden');
        }

        // user can update only the applications where status = pending
        if ($application_info->status != "pending" || !in_array($status, ["approved", "approve_by_manager", "rejected", "canceled"])) {
            return redirect()->to('forbidden');
        }

        if ($this->leaveApplicationsModel->update($application_id, $leave_data)) {
            $notification_options = [
                'leave_id' => $application_id,
                'to_user_id' => $application_info->applicant_id
            ];

            if ($status == "approved") {
                log_notification("leave_approved", $notification_options);
            } elseif ($status == "rejected") {
                log_notification("leave_rejected", $notification_options);
            } elseif ($status == "canceled") {
                log_notification("leave_canceled", $notification_options);
            } elseif ($status == "approve_by_manager") {
                log_notification("leave_approved_by_manager", $notification_options);
            }

            return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($application_id), 'id' => $application_id, 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function delete()
    {
        $id = $this->request->getPost('id');

        $this->validate([
            'id' => 'required|numeric'
        ]);

        if (!$this->can_delete_leave_application()) {
            return redirect()->to('forbidden');
        }

        $application_info = $this->leaveApplicationsModel->find($id);
        $this->access_only_allowed_members($application_info->applicant_id);

        if ($this->leaveApplicationsModel->delete($id)) {
            return $this->response->setJSON(['success' => true, 'message' => lang('record_deleted')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
        }
    }

    public function leave_info()
    {
        // Assuming $this->login_user is initialized properly
        $view_data['applicant_id'] = $this->login_user->id;

        if ($this->request->isAJAX()) {
            return view("team_members/leave_info", $view_data);
        } else {
            $view_data['page_type'] = "full";
            return view("team_members/leave_info", $view_data);
        }
    }

    private function _get_members_dropdown_list_for_filter()
    {
        if ($this->access_type === "all") {
            $where = ["user_type" => "staff"];
        } else {
            if (!count($this->allowed_members)) {
                $where = ["user_type" => "nothing"];
            } else {
                $allowed_members = $this->allowed_members;
                $allowed_members[] = $this->login_user->id;
                $where = ["user_type" => "staff", "where_in" => ["id" => $allowed_members]];
            }
        }

        $members = $this->usersModel->get_dropdown_list(["first_name", "last_name"], "id", $where);

        $members_dropdown = [["id" => "", "text" => "- " . lang("team_member") . " -"]];
        foreach ($members as $id => $name) {
            $members_dropdown[] = ["id" => $id, "text" => $name];
        }
        return $members_dropdown;
    }

    private function _get_line_manager_dropdown_list_for_filter()
    {
        if ($this->access_type === "all") {
            $where = ["user_type" => "staff"];
        } else {
            if (!count($this->allowed_members)) {
                $where = ["user_type" => "nothing"];
            } else {
                $allowed_members = $this->allowed_members;
                $allowed_members[] = $this->login_user->id;
                $where = ["user_type" => "staff", "where_in" => ["id" => $allowed_members]];
            }
        }

        $members = $this->usersModel->get_dropdown_list(["first_name", "last_name"], "id", $where);

        $members_dropdown = [["id" => "", "text" => "- " . lang("line_manager") . " -"]];
        foreach ($members as $id => $name) {
            $members_dropdown[] = ["id" => $id, "text" => $name];
        }
        return $members_dropdown;
    }

    //summary dropdown list of leave type 

    private function _get_leave_types_dropdown_list_for_filter()
    {
        $leave_type = $this->leaveTypesModel->where('status', 'active')->findAll();

        $leave_type_dropdown = [["id" => "", "text" => "- " . lang("leave_type") . " -"]];
        foreach ($leave_type as $type) {
            $leave_type_dropdown[] = ["id" => $type['id'], "text" => $type['title']];
        }
        return $leave_type_dropdown;
    }

    // Summary details list
    // Load leave summary tab
    public function summary_details()
    {
        $view_data['team_members_dropdown'] = json_encode($this->_get_members_dropdown_list_for_filter());
        $view_data['leave_types_dropdown'] = json_encode($this->_get_leave_types_dropdown_list_for_filter());

        return view("leaves/summary_details", $view_data);
    }

    // List of leave summary, prepared for datatable
    public function summary_details_list_data()
    {
        $start_date = $this->request->getPost('start_date');
        $end_date = $this->request->getPost('end_date');
        $applicant_id = $this->request->getPost('applicant_id');
        $leave_type_id = $this->request->getPost('leave_type_id');

        $options = [
            "start_date" => $start_date,
            "end_date" => $end_date,
            "access_type" => $this->access_type,
            "allowed_members" => $this->allowed_members,
            "applicant_id" => $applicant_id,
            "leave_type_id" => $leave_type_id
        ];

        $list_data = $this->leaveApplicationsModel->get_summary($options)->getResult();

        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row_for_summary_details($data);
        }
        return $this->response->setJSON(['data' => $result]);
    }

    // Prepare a row of leave application list table
    private function _make_row_for_summary_details($data)
    {
        $meta_info = $this->_prepare_leave_info($data);

        return [
            get_team_member_profile_link($data->applicant_id, $meta_info->applicant_meta),
            $meta_info->leave_type_meta,
            $data->total_days
        ];
    }
}