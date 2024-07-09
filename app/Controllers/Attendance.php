<?php
namespace App\Controllers;
use App\Models\SettingsModel;
use App\Models\AttendanceModel;
use App\Models\TasksModel;
use App\Models\UsersModel;
use App\Models\CustomFieldsModel;
use App\Models\TaskStatusModel;
use App\Models\AttendanceTodoModel;
use App\Models\AttendanceTaskTodoModel;
class Attendance extends BaseController

{   protected $settingsModel;
    protected $attendanceModel;
    protected $tasksModel;
    protected $usersModel;
    protected $customFieldsModel;
    protected $taskStatusModel;
    protected $attendanceTodoModel;
    protected $attendanceTaskTodoModel;
    public function __construct()
    {
    parent::__construct();

        // Initialize models
        $this->settingsModel = new SettingsModel();
        $this->attendanceModel = new AttendanceModel();
        $this->tasksModel = new TasksModel();
        $this->usersModel = new UsersModel();
        $this->customFieldsModel = new CustomFieldsModel();
        $this->taskStatusModel = new TaskStatusModel();
        $this->attendanceTodoModel = new AttendanceTodoModel();
        $this->attendanceTaskTodoModel = new AttendanceTaskTodoModel();

        // This module is accessible only to team members
        $this->accessOnlyTeamMembers();

        // We can set IP restriction to access this module. Validate user access
        $this->checkAllowedIp();

        // Initialize managerial permission
        $this->initPermissionChecker("attendance");
    }
    // Check IP restriction for non-admin users
    private function checkAllowedIp()
    {
        if (!$this->login_user->is_admin) {
            if ($this->login_user->work_mode == '0') {
                $ip = get_real_ip();
                $allowed_ips = $this->settingsModel->getSetting("allowed_ip_addresses");
                if ($allowed_ips) {
                    $allowed_ip_array = array_map('trim', preg_split('/\R/', $allowed_ips));
                    if (!in_array($ip, $allowed_ip_array)) {
                        return redirect()->to(base_url("forbidden_attendance"));
                    }
                }
            }
        }
    }
    // Only admin or assigned members can access/manage other member's attendance
    protected function accessOnlyAllowedMembers($user_id = 0)
    {
        if ($this->access_type !== "all") {
            if ($user_id === $this->login_user->id || !in_array($user_id, $this->allowed_members)) {
                return redirect()->to(base_url("forbidden"));
            }
        }
    }

    // Show attendance list view
    public function index()
    {
        $this->checkModuleAvailability("module_attendance");

        $view_data['team_members_dropdown'] = json_encode($this->_getMembersDropdownListForFilter());
        $view_data['team_members_dropdowns'] = json_encode($this->_getRmMembersDropdownListForFilter());
        return view('attendance/index', $view_data);
    }

    public function forbid()
    {
        return redirect()->to(base_url("forbidden_attendance"));
    }

    // Show add/edit attendance modal
    public function modalForm()
    {
        $user_id = 0;

        $id = $this->request->getPost('id');
        $view_data['time_format_24_hours'] = get_setting("time_format") == "24_hours" ? true : false;
        $view_data['model_info'] = $this->attendanceModel->find($id);

        if ($view_data['model_info']->id) {
            $user_id = $view_data['model_info']->user_id;
            $this->accessOnlyAllowedMembers($user_id);
        }

        if ($user_id) {
            // Edit mode. Show user's info
            $view_data['team_members_info'] = $this->usersModel->find($user_id);
        } else {
            // New add mode. Show users dropdown
            // Don't show non-allowed members in dropdown
            if ($this->access_type === "all") {
                $where = array("user_type" => "staff");
            } else {
                if (!count($this->allowed_members)) {
                    return redirect()->to(base_url("forbidden"));
                }
                $where = array("user_type" => "staff", "id !=" => $this->login_user->id, "where_in" => array("id" => $this->allowed_members));
            }

            $view_data['team_members_dropdown'] = array("" => "-") + $this->usersModel->getDropdownList(array("first_name", "last_name"), "id", $where);
        }

        return view('attendance/modal_form', $view_data);
    }

    // Show attendance note modal
    public function noteModalForm()
    {
        $id = $this->request->getPost('id');
        $clock_out = $this->request->getPost('clock_out');
        $view_data["clock_out"] = $clock_out; // Trigger clockout after submit?

        $view_data['todo_id'] = $id;
        $view_data['project_id'] = 0;
        $projects = $this->tasksModel->getMyProjectsDropdownList($this->login_user->id)->findAll();
        $projects_dropdown = array(array("id" => "", "text" => "- " . lang("project") . " -"));
        foreach ($projects as $project) {
            if ($project->project_id && $project->project_title) {
                $projects_dropdown[] = array("id" => $project->project_id, "text" => $project->project_title);
            }
        }

        // Tasks team members dropdown
        $options = array(
            "id" => $id,
        );
        $clock_in_data = $this->attendanceModel->where($options)->first();

        $clock_user_id = $clock_in_data->user_id;
        $team_members_dropdown = array(array("id" => "", "text" => "- " . lang("team_member") . " -"));
        $assigned_to_list = $this->usersModel->where(array("deleted" => 0, "user_type" => "staff"))->findAll();
        foreach ($assigned_to_list as $key => $value) {
            if ($key == $clock_user_id) {
                $team_members_dropdown[] = array("id" => $key, "text" => $value, "isSelected" => true);
            } else {
                $team_members_dropdown[] = array("id" => $key, "text" => $value);
            }
        }

        // Pending tasks list
        $tasks_pending_options = array(
            "specific_user_id" => $clock_user_id,
            "status_ids" => implode(",", array('1', '2')), // Default the status value in todo, progress
        );
        $view_data['team_members_dropdown'] = json_encode($team_members_dropdown);
        $view_data["custom_field_headers"] = $this->customFieldsModel->getCustomFieldHeadersForTable("tasks", $this->login_user->is_admin, $this->login_user->user_type);

        $view_data['task_statuses'] = $this->taskStatusModel->findAll();

        $view_data['projects_dropdown'] = json_encode($projects_dropdown);

        $view_data['model_info'] = $this->attendanceModel->find($id);

        // Already exit task task
        $attendance_savetask_todo_options = array("todo_id" => $id, "user_id" => $clock_user_id);
        $attendance_savetask_todo = $this->attendanceTodoModel->where($attendance_savetask_todo_options)->findAll();
        $attendance_savetask_items_array = array();

        foreach ($attendance_savetask_todo as $attendance_savetask_item) {
            $attendance_savetask_id_items_array[] = $attendance_savetask_item->task_id;
        }

        $tasks_list = $this->tasksModel->where($tasks_pending_options)->findAll();
        $tasks_list_dropdown = array(array("id" => "", "text" => "- " . lang("task_list") . " -"));
        foreach ($tasks_list as $task_list) {
            if ($task_list->id && $task_list->project_title) {
                if (!in_array($task_list->id, $attendance_savetask_id_items_array)) {
                    $tasks_list_dropdown[] = array("id" => $task_list->id, "text" => $task_list->title . "  - " . lang("project") . ": " . $task_list->project_title);
                }
            }
        }
        $view_data['tasks_list_dropdown'] = json_encode($tasks_list_dropdown);

        return view('attendance/note_modal_form', $view_data);
    }

    // Tasks save and list
    private function _makeAttendanceSaveTaskItemRow($data = array(), $return_type = "row")
    {
        $checkbox_class = "checkbox-blank";
        $title_class = "";
        $is_checked_value = 1;

        $task_title_list_data = $this->tasksModel->find($data['title']);
        $task_title = $task_title_list_data->title;
        if ($data['is_checked'] == 0) {
            $is_checked_value = 0;
            $checkbox_class = "checkbox-checked";
        }

        $status = "<span class='$checkbox_class'></span>";
        $status = ajax_anchor(get_uri("attendance/delete_savechecklist_item/" . $data['id']), "<span class='$checkbox_class'></span>", array("class" => "delete-checklist-item", "title" => lang("delete"), "data-fade-out-on-success" => "#checklist-item-rows-" . $data['id']));

        $title = "<span class='font-13 $title_class'>" . $task_title . "</span>";

        $delete = ajax_anchor(get_uri("attendance/delete_savechecklist_item/" . $data['id']), "<i class='fa fa-times pull-right p3'></i>", array("class" => "delete-checklist-item", "title" => lang("delete"), "data-fade-out-on-success" => "#checklist-item-rows-" . $data['id']));

        if ($return_type == "data") {
            return $status . $title . $delete;
        }

        return "<div id='checklist-item-rows-" . $data['id'] . "' class='list-group-item mb5 checklist-item-rows' data-id='" . $data['id'] . "'>" . $status . $title . $delete . "</div>";
    }

    public function deleteSaveChecklistItem($id)
    {
        $task_title_list_data = $this->attendanceTaskTodoModel->find($id);
        $get_todo_id = $task_title_list_data->todo_id;

        $task_options = array("todo_id" => $get_todo_id);
        $atttask_table_list = $this->attendanceTaskTodoModel->where($task_options)->countAllResults();

        if ($atttask_table_list > 1) {
            if ($this->attendanceTaskTodoModel->delete($id)) {
                echo json_encode(array("success" => true));
            } else {
                echo json_encode(array("success" => false));
            }
        } else {
            echo json_encode(array("success" => false));
        }
    }

    private function _makeChecklistItemRow($data = array(), $todo_id = 0, $clock_user_id, $return_type = "row")
    {
        $checkbox_class = "checkbox-blank";
        $title_class = "";
        $is_checked_value = 1;

        if ($data['is_checked'] == 1) {
            $is_checked_value = 0;
            $checkbox_class = "checkbox-checked";
        }

        $status = ajax_anchor(get_uri("attendance/save_checklist_item/" . $data['id'] . "/" . $todo_id . "/" . $clock_user_id), "<span class='$checkbox_class'></span>", array("class" => "delete-checklist-item", "title" => lang("save"), "data-fade-out-on-success" => "#checklist-item-row-" . $data['id']));

        $title = "<span class='font-13 $title_class'>" . $data['title'] . "</span>";

        $delete = ajax_anchor(get_uri("attendance/save_checklist_item/" . $data['id'] . "/" . $todo_id . "/" . $clock_user_id), "<i class='fa fa-check-circle pull-right p3'></i>", array("class" => "delete-checklist-item", "title" => lang("save"), "data-fade-out-on-success" => "#checklist-item-row-" . $data['id']));

        if ($return_type == "data") {
            return $status . $title . $delete;
        }

        return "<div id='checklist-item-row-" . $data['id'] . "' class='list-group-item mb5 checklist-item-row' data-id='" . $data['id'] . "'>" . $status . $title . $delete . "</div>";
    }

   public function save_checklist_item($id, $todo_id, $clock_user_id)
{
    $todo_model_info = $this->Attendance_model->get_one($todo_id);
    $start_date = date("Y-m-d", strtotime($todo_model_info->in_time));
    $check_options = [
        "title" => $id,
        "start_date" => $start_date,
        "todo_id" => $todo_id,
        "user_id" => $clock_user_id
    ];

    $check_exits_todo = $this->Attendance_task_todo_model->get_details($check_options)->getResult();
    if (!$check_exits_todo) {
        $data = [
            "title" => $id,
            "description" => $this->request->getPost('description') ?? "",
            "start_date" => $start_date,
            "todo_id" => $todo_id,
            "user_id" => $clock_user_id
        ];

        $save_id = $this->Attendance_task_todo_model->save($data);
    }

    if (isset($save_id)) {
        return json_encode(["success" => true, "message" => lang('record_saved')]);
    } else {
        return json_encode(["success" => false]);
    }
}
public function log_sor_time()
{
    $this->validation->setRules([
        "id" => "numeric",
    ]);

    if (!$this->validation->withRequest($this->request)->run()) {
        return json_encode(["success" => false, 'message' => lang('error_occurred')]);
    }

    $id = $this->request->getPost('id');
    $now = date("Y-m-d");
    $data = [
        "note" => $this->request->getPost('sor_note'),
        "in_time" => $now,
        "status" => "incomplete",
        "user_id" => $this->login_user->id,
        "clockin_location" => $this->request->getPost('result')
    ];

    $save_id = $this->Attendance_model->save($data, $id);
    if ($save_id) {
        return json_encode([
            "success" => true,
            "data" => $this->_row_data($save_id),
            'id' => $save_id,
            'message' => lang('record_saved'),
            "clock_widget" => clock_widget(true)
        ]);
    } else {
        return json_encode(["success" => false, 'message' => lang('error_occurred')]);
    }
}
public function start_day_report_modal_form()
{
    $view_data["clock_in"] = $this->request->getPost("clock_in");
    $view_data['model_info'] = $this->Attendance_model->get_one($this->request->getPost('id'));
    echo view('attendance/add_todo/start_day_report_modal', $view_data);
}

public function todo_view()
{
    $todo_id = $this->request->getPost('id');
    $model_info = $this->Attendance_model->get_details(["id" => $todo_id])->getRow();

    $view_data['model_info'] = $model_info;
    $view_data['todo_id'] = $todo_id;
    $view_data['project_id'] = 0;

    $projects = $this->Tasks_model->get_my_projects_dropdown_list($this->login_user->id)->getResult();
    $projects_dropdown = [['id' => '', 'text' => '- ' . lang("project") . ' -']];
    foreach ($projects as $project) {
        if ($project->project_id && $project->project_title) {
            $projects_dropdown[] = ['id' => $project->project_id, 'text' => $project->project_title];
        }
    }
    $view_data['projects_dropdown'] = json_encode($projects_dropdown);

    $team_members_dropdown = [['id' => '', 'text' => '- ' . lang("team_member") . ' -']];
    $assigned_to_list = $this->Users_model->get_dropdown_list(['first_name', 'last_name'], 'id', ['deleted' => 0, 'user_type' => 'staff']);
    foreach ($assigned_to_list as $key => $value) {
        if ($key == $this->login_user->id) {
            $team_members_dropdown[] = ['id' => $key, 'text' => $value, 'isSelected' => true];
        } else {
            $team_members_dropdown[] = ['id' => $key, 'text' => $value];
        }
    }
    $view_data['team_members_dropdown'] = json_encode($team_members_dropdown);

    echo view('attendance/add_todo/add_todo', $view_data);
}


public function todo_save()
{
    $this->validation->setRules([
        "id" => "numeric",
        "title" => "required"
    ]);

    if (!$this->validation->withRequest($this->request)->run()) {
        return json_encode(["success" => false]);
    }

    $id = $this->request->getPost('id');
    $todo_model_info = $this->Attendance_model->get_one($id);
    $start_date = date("Y-m-d", strtotime($todo_model_info->in_time));

    $usertime_model_info = $this->Users_model->get_one($todo_model_info->user_id);
    $user_timezone_date_crate = new DateTime('now', new DateTimeZone($usertime_model_info->user_timezone));
    $now = $user_timezone_date_crate->format("Y-m-d H:i:s");

    $check_options = [
        "task_id" => $this->request->getPost('task_user_id'),
        "start_date" => $start_date,
        "todo_id" => $id,
        "user_id" => $todo_model_info->user_id,
    ];

    $check_exits_todo = $this->Attendance_todo_model->get_details($check_options)->getResult();
    if (!$check_exits_todo) {
        $data = [
            "title" => $this->request->getPost('title'),
            "start_date" => $now,
            "todo_id" => $id,
            "user_id" => $this->login_user->id,
            "task_id" => $this->request->getPost('task_user_id'),
        ];

        $save_id = $this->Attendance_todo_model->save($data);
    } else {
        return json_encode(["success" => false, 'message' => 'This Task is already exists']);
    }

    $options = ["id" => $id];
    $attendance_user_info = $this->Attendance_model->get_details($options)->getRow();
    $attendance_user_infos = $attendance_user_info->in_time;
    $timestamp = $attendance_user_infos;
    $splitTimeStamp = explode(" ", $timestamp);
    $date = $splitTimeStamp[0];
    $time = $splitTimeStamp[1];
    if ($time == '00:00:00') {
        $DB4 = \Config\Database::connect('default');
        $DB4->table('attendance')->where('id', $id)->update(["in_time" => $now]);
    }

    if (isset($save_id)) {
        $item_info = $this->Attendance_todo_model->get_one($save_id);
        return json_encode(["success" => true, "data" => $this->_todo_make_row($item_info), 'id' => $save_id, "clock_widget" => clock_widget(true), 'message' => lang('record_saved')]);
    } else {
        return json_encode(["success" => false]);
    }
}


public function add_todo_modal_save()
{
    $this->validation->setRules([
        "id" => "numeric",
        "todo_id" => "required"
    ]);

    if (!$this->validation->withRequest($this->request)->run()) {
        return json_encode(["success" => false]);
    }

    $id = $this->request->getPost('id');
    $todo_id = $this->request->getPost('todo_id');
    $now = get_current_utc_time();
    $data = [
        "title" => $this->request->getPost('title'),
        "description" => $this->request->getPost('description') ?? "",
        "start_date" => $this->request->getPost('start_date') ?? $now,
        "todo_id" => $todo_id,
        "user_id" => $this->login_user->id
    ];

    $save_id = $this->Attendance_todo_model->save($data, $id);

    if ($save_id) {
        $options = ["id" => $todo_id];
        $item_info = $this->Attendance_todo_model->get_details($options)->getRow();
        return json_encode(["success" => true, "todo_id" => $item_info->todo_id, "data" => $this->_todo_make_row($item_info), 'id' => $save_id, 'message' => lang('record_saved')]);
    } else {
        return json_encode(["success" => false]);
    }
}


public function todo_save_status() {
        // Validate input
        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'required|numeric',
            'status' => 'required'
        ]);
    
        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $validation->getErrors()
            ]);
        }
    
        $id = $this->request->getPost('id');
        $data = [
            "status" => $this->request->getPost('status')
        ];
    
        // Load model via service
        $attendanceTodoModel = new Attendance_todo_model();
    
        // Save data
        $save_id = $attendanceTodoModel->save($data, $id);
    
        if ($save_id) {
            return $this->response->setJSON([
                'success' => true,
                'data' => $this->_todo_row_data($save_id),
                'id' => $save_id,
                'message' => lang('record_saved')
            ]);
        } else {
            return $this->response->setJSON([
                'success' => false,
                'message' => lang('error_occurred')
            ]);
        }
    }
    public function todo_title_view()
    {
        helper(['form']);

        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'required|numeric'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            // Handle validation errors if needed
            return redirect()->back()->withInput()->with('error', 'Validation failed');
        }

        $model_info = $this->Attendance_todo_model->getOne($this->request->getPost('id'));

        // Implement your access validation if needed
        // $this->validate_access($model_info);

        $view_data['model_info'] = $model_info;

        return view('attendance/add_todo/todo_title_view', $view_data);
    }
    public function add_todo_modal_form()
    {
        helper(['form']);

        // Get post data
        $id = $this->request->getPost('id');
        
        // Get model info based on post ID
        $view_data['model_info'] = $this->Attendance_todo_model->getOne($id);

        // Check if todo_id exists in model_info, otherwise initialize
        $todo_id = isset($view_data['model_info']->todo_id) ? $view_data['model_info']->todo_id : null;
        $view_data['todo_id'] = $todo_id;
        return view('attendance/add_todo/add_todo_modal_form', $view_data);
    }
    public function add_todo_modal_form()
    {
        helper(['form']);
        
        // Get post data
        $id = $this->request->getPost('id');
        
        // Get model info based on post ID
        $view_data['model_info'] = $this->Attendance_todo_model->getOne($id);

        // Check if todo_id exists in model_info, otherwise initialize
        $todo_id = isset($view_data['model_info']->todo_id) ? $view_data['model_info']->todo_id : null;
        $view_data['todo_id'] = $todo_id;

        // Load view with data
        return view('attendance/add_todo/add_todo_modal_form', $view_data);
    }
    public function todo_delete()
    {
        $rules = [
            'id' => 'required|numeric'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $id = $this->request->getPost('id');

        // Example: Access validation (if needed)
        /*
        $todo_info = $this->Attendance_todo_model->getOne($id);
        $this->validate_access($todo_info);
        */

        if ($this->request->getPost('undo')) {
            if ($this->Attendance_todo_model->delete($id, true)) {
                return $this->respond(['success' => true, 'data' => $this->_todo_row_data($id), 'message' => lang('record_undone')]);
            } else {
                return $this->fail(lang('error_occurred'));
            }
        } else {
            if ($this->Attendance_todo_model->delete($id)) {
                return $this->respond(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->fail(lang('record_cannot_be_deleted'));
            }
        }
    }
    private function _todo_make_row($data)
    {
        helper(['url', 'form', 'date', 'array']);

        $title = modal_anchor(route_to('attendance/todo_title_view', $data->id), $data->title, ['class' => 'edit', 'title' => lang('todo'), 'data-post-id' => $data->id]);

        $status_class = "";
        $checkbox_class = "checkbox-blank";

        if ($data->status === "to_do") {
            $status_class = "b-warning";
        } else {
            $checkbox_class = "checkbox-checked";
            $status_class = "b-success";
        }

        $check_status = js_anchor("<span class='$checkbox_class'></span>", [
            'title' => "",
            'class' => "",
            'data-id' => $data->id,
            'data-value' => $data->status === "done" ? "to_do" : "done",
            'data-act' => "update-todo-status-checkbox"
        ]);

        $start_date_text = "";
        if (is_date_exists($data->start_date)) {
            $start_date_text = attendance_format_to_date($data->start_date, false);
            if (get_my_local_time("Y-m-d") > $data->start_date && $data->status != "done") {
                $start_date_text = "<span class='text-danger'>" . $start_date_text . "</span> ";
            } else if (get_my_local_time("Y-m-d") == $data->start_date && $data->status != "done") {
                $start_date_text = "<span class='text-warning'>" . $start_date_text . "</span> ";
            }
        }

        $edit_button = "";
        if ($data->status != "done") {
            $edit_button = modal_anchor(route_to('attendance/add_todo_modal_form'), "<i class='fa fa-pencil'></i>", ['class' => 'edit', 'title' => lang('edit'), 'data-post-id' => $data->id])
                . js_anchor("<i class='fa fa-times fa-fw'></i>", [
                    'title' => lang('delete'),
                    'class' => 'delete',
                    'data-id' => $data->id,
                    'data-action-url' => route_to('attendance/todo_delete'),
                    'data-action' => 'delete-confirmation'
                ]);
        }

        $task_id_details = new Tasks_model();
        $task_id_client = new Clients_model();
        $task_id_project = new Projects_model();
        $task_id_status = new Task_status_model();

        $task_id_details = $task_id_details->find($data->task_id);
        $task_id_client = $task_id_client->find($task_id_details->client_id);
        $task_id_project = $task_id_project->find($task_id_details->project_id);
        $task_id_status = $task_id_status->find($task_id_details->status_id);

        $task_title = "-";
        if ($task_id_details->title) {
            $task_title = modal_anchor(route_to('projects/task_view'), $task_id_details->title, ['title' => lang('task_info') . " #$data->id", 'data-post-id' => $task_id_details->id]);
        }

        $task_client = "-";
        if ($task_id_client->company_name) {
            $task_client = anchor(route_to('clients/view', $task_id_client->id), $task_id_client->company_name);
        }

        $task_project_title = "-";
        if ($task_id_project->title) {
            $task_project_title = anchor(route_to('projects/view', $task_id_project->project_id), $task_id_project->title);
        }

        return [
            $status_class,
            "<i class='hide'>" . $data->id . "</i>" . $check_status . $title . ($data->title),
            $data->start_date,
            $start_date_text,
            $task_title,
            $task_id_details->start_date,
            $task_id_details->deadline,
            $task_client,
            $task_project_title,
            $task_id_status->title,
            $edit_button
        ];
    }
public function save() {
    $id = $this->request->getPost('id');

    $validation = \Config\Services::validation();
    $validation->setRules([
        'id' => 'numeric',
        'in_date' => 'required',
        'out_date' => 'required',
        'in_time' => 'required',
        'out_time' => 'required'
    ]);

    if (!$validation->withRequest($this->request)->run()) {
        return $this->failValidationErrors($validation->getErrors());
    }

    $inTime = $this->request->getPost('in_time');
    $outTime = $this->request->getPost('out_time');

    // Convert time to 24-hour format if needed
    if (get_setting('time_format') != '24_hours') {
        $inTime = convert_time_to_24hours_format($inTime);
        $outTime = convert_time_to_24hours_format($outTime);
    }

    // Prepare data array
    $data = [
        'in_time' => $this->request->getPost('in_date') . ' ' . $inTime,
        'out_time' => $this->request->getPost('out_date') . ' ' . $outTime,
        'status' => 'pending',
        'note' => $this->request->getPost('note')
    ];

    // Save or update data
    if ($id) {
        $info = $this->Attendance_model->find($id);
        $userId = $info->user_id;
    } else {
        $userId = $this->request->getPost('user_id');
        $data['user_id'] = $userId;
    }

    // Ensure user access
    $this->access_only_allowed_members($userId);

    // Save data using the model
    if ($this->Attendance_model->save($data, $id)) {
        return $this->respond([
            'success' => true,
            'data' => $this->_row_data($id),
            'id' => $id,
            'isUpdate' => (bool)$id,
            'message' => lang('record_saved')
        ]);
    } else {
        return $this->fail(lang('error_occurred'));
    }
}

public function save_note() {
    $id = $this->request->getPost('id');

    $validation = \Config\Services::validation();
    $validation->setRules([
        'id' => 'required|numeric'
    ]);

    if (!$validation->withRequest($this->request)->run()) {
        return $this->failValidationErrors($validation->getErrors());
    }

    $data = [
        'note' => $this->request->getPost('note')
    ];

    // Save note using the model
    if ($this->Attendance_model->save($data, $id)) {
        return $this->respond([
            'success' => true,
            'data' => $this->_row_data($id),
            'id' => $id,
            'isUpdate' => true,
            'message' => lang('record_saved')
        ]);
    } else {
        return $this->fail(lang('error_occurred'));
    }
}
public function log_time()
{
    $note = $this->request->getPost('note');
    $result = $this->request->getPost('result');

    $this->Attendance_model->log_time($this->login_user->id, $note, $result);

    if ($this->request->getPost("clock_out")) {
        return $this->response->setJSON(["success" => true, "clock_widget" => $this->clock_widget(true)]);
    } else {
        return $this->clock_widget();
    }
}
public function delete()
{
    $id = $this->request->getPost('id');

    if ($this->access_type !== "all") {
        $info = $this->Attendance_model->getOne($id);
        $this->access_only_allowed_members($info->user_id);
    }

    if ($this->request->getPost('undo')) {
        if ($this->Attendance_model->delete($id, true)) {
            return $this->response->setJSON(["success" => true, "data" => $this->_row_data($id), "message" => lang('record_undone')]);
        } else {
            return $this->response->setJSON(["success" => false, "message" => lang('error_occurred')]);
        }
    } else {
        if ($this->Attendance_model->delete($id)) {
            return $this->response->setJSON(["success" => true, 'message' => lang('record_deleted')]);
        } else {
            return $this->response->setJSON(["success" => false, 'message' => lang('record_cannot_be_deleted')]);
        }
    }
}
public function list_data()
{
    $start_date = $this->request->getPost('start_date');
    $end_date = $this->request->getPost('end_date');
    $user_id = $this->request->getPost('user_id');
    $user_ids = $this->request->getPost('user_ids');

    $options = [
        "start_date" => $start_date,
        "end_date" => $end_date,
        "login_user_id" => $this->login_user->id,
        "user_id" => $user_id ?: $user_ids,
        "access_type" => $this->access_type,
        "allowed_members" => $this->allowed_members
    ];

    $list_data = $this->Attendance_model->getDetails($options)->getResult();

    $result = [];
    foreach ($list_data as $data) {
        $result[] = $this->_make_row($data);
    }

    return $this->response->setJSON(["data" => $result]);
}
public function attendance_info()
{
    $this->check_module_availability("module_attendance");

    $view_data['user_id'] = $this->login_user->id;

    if ($this->request->isAJAX()) {
        return view("team_members/attendance_info", $view_data);
    } else {
        $view_data['page_type'] = "full";
        return view("team_members/attendance_info", $view_data);
    }
}
private function _row_data($id)
{
    $options = ["id" => $id];
    $data = $this->Attendance_model->getDetails($options)->getRow();
    return $this->_make_row($data);
}
private function _make_row($data)
    {
        $image_url = get_avatar($data->created_by_avatar);
        $user = "<span class='avatar avatar-xs mr10'><img src='$image_url' alt=''></span> $data->created_by_user";
        $out_time = $data->out_time;
        if (!is_date_exists($out_time)) {
            $out_time = "";
        }

        $to_time = strtotime($data->out_time);
        if (!$out_time) {
            $to_time = strtotime($data->in_time);
        }
        $from_time = strtotime($data->in_time);

        $option_links = modal_anchor(route_to("attendance/modal_form"), "<i class='fa fa-pencil'></i>", [
            "class" => "edit",
            "title" => lang('edit_attendance'),
            "data-post-id" => $data->id
        ]) . js_anchor("<i class='fa fa-times fa-fw'></i>", [
            'title' => lang('delete_attendance'),
            "class" => "delete",
            "data-id" => $data->id,
            "data-action-url" => route_to("attendance/delete"),
            "data-action" => "delete-confirmation"
        ]);

        if ($this->access_type != "all") {
            // Don't show options links for non-admin user's own records
            if ($data->user_id === $this->login_user->id) {
                $option_links = "";
            }
        }

        $note_link = modal_anchor(route_to("attendance/note_modal_form"), "<i class='fa fa-comment-o p10'></i>", [
            "class" => "edit text-muted",
            "title" => lang("note"),
            "data-post-id" => $data->id
        ]);

        if ($data->note) {
            $note_link = modal_anchor(route_to("attendance/note_modal_form"), "<i class='fa fa-comment p10'></i>", [
                "class" => "edit text-muted",
                "title" => $data->note,
                "data-modal-title" => lang("note"),
                "data-post-id" => $data->id
            ]);
        }

        if ($data->user_user_type == "staff") {
            return [
                get_team_member_profile_link($data->user_id, $user),
                attendance_format_to_date($data->in_time),
                attendance_format_to_time($data->in_time),
                $out_time ? attendance_format_to_date($out_time) : "-",
                $out_time ? attendance_format_to_time($out_time) : "-",
                $data->clockin_location,
                $data->clockout_location,
                convert_seconds_to_time_format(abs($to_time - $from_time)),
                $note_link,
                $option_links
            ];
        } elseif ($data->user_user_type == "resource") {
            return [
                get_rm_member_profile_link($data->user_id, $user),
                attendance_format_to_date($data->in_time),
                attendance_format_to_time($data->in_time),
                $out_time ? attendance_format_to_date($out_time) : "-",
                $out_time ? attendance_format_to_time($out_time) : "-",
                $data->clockin_location,
                $data->clockout_location,
                convert_seconds_to_time_format(abs($to_time - $from_time)),
                $note_link,
                $option_links
            ];
        }
    }
    public function custom()
    {
        $view_data['team_members_dropdown'] = json_encode($this->_get_members_dropdown_list_for_filter());
        $view_data['team_members_dropdowns'] = json_encode($this->_get_rm_members_dropdown_list_for_filter());
        return view("attendance/custom_list", $view_data);
    }

    // Members clocked in method
    public function members_clocked_in()
    {
        return view("attendance/members_clocked_in");
    }

    // Summary method
    public function summary()
    {
        $view_data['team_members_dropdown'] = json_encode($this->_get_members_dropdown_list_for_filter());
        $view_data['team_members_dropdowns'] = json_encode($this->_get_rm_members_dropdown_list_for_filter());
        return view("attendance/summary_list", $view_data);
    }

    // Summary list data method
    public function summary_list_data()
    {
        $start_date = $this->request->getPost('start_date');
        $end_date = $this->request->getPost('end_date');
        $user_id = $this->request->getPost('user_id');

        $options = [
            "start_date" => $start_date,
            "end_date" => $end_date,
            "login_user_id" => $this->login_user->id,
            "user_id" => $user_id,
            "access_type" => $this->access_type,
            "allowed_members" => $this->allowed_members
        ];

        $list_data = $this->Attendance_model->get_summary_details($options)->getResult();

        $result = [];
        foreach ($list_data as $data) {
            $image_url = get_avatar($data->created_by_avatar);
            $user = "<span class='avatar avatar-xs mr10'><img src='$image_url' alt=''></span> $data->created_by_user";

            $duration = convert_seconds_to_time_format(abs($data->total_duration));

            $result[] = [
                get_team_member_profile_link($data->user_id, $user),
                $duration,
                to_decimal_format(convert_time_string_to_decimal($duration))
            ];
        }

        return $this->response->setJSON(["data" => $result]);
    }

    // Summary details method
    public function summary_details()
    {
        $view_data['team_members_dropdown'] = json_encode($this->_get_members_dropdown_list_for_filter());
        $view_data['team_members_dropdowns'] = json_encode($this->_get_rm_members_dropdown_list_for_filter());
        return view("attendance/summary_details_list", $view_data);
    }

    // Private method to get members dropdown list for filter
    private function _get_members_dropdown_list_for_filter()
    {
        if ($this->access_type === "all") {
            $where = ["user_type" => "staff"];
        } else {
            if (!count($this->allowed_members)) {
                $where = ["user_type" => "nothing"]; // Don't show any users in dropdown
            } else {
                // Add login user in dropdown list
                $allowed_members = $this->allowed_members;
                $allowed_members[] = $this->login_user->id;

                $where = [
                    "user_type" => "staff",
                    "where_in" => ["id" => $allowed_members]
                ];
            }
        }

        $members = $this->Users_model->getDropdownList(["first_name", "last_name"], "id", $where);

        $members_dropdown = [["id" => "", "text" => "- " . lang("member") . " -"]];
        foreach ($members as $id => $name) {
            $members_dropdown[] = ["id" => $id, "text" => $name];
        }
        return $members_dropdown;
    }

    // Private method to get RM members dropdown list for filter
    private function _get_rm_members_dropdown_list_for_filter()
    {
        if ($this->access_type === "all") {
            $where = ["user_type" => "resource"];
        } else {
            if (!count($this->allowed_members)) {
                $where = ["user_type" => "nothing"]; // Don't show any users in dropdown
            } else {
                // Add login user in dropdown list
                $allowed_members = $this->allowed_members;
                $allowed_members[] = $this->login_user->id;

                $where = [
                    "user_type" => "resource",
                    "where_in" => ["id" => $allowed_members]
                ];
            }
        }

        $members = $this->Users_model->getDropdownList(["first_name", "last_name"], "id", $where);

        $members_dropdowns = [["id" => "", "text" => "- " . lang("outsource_member") . " -"]];
        foreach ($members as $id => $name) {
            $members_dropdowns[] = ["id" => $id, "text" => $name];
        }
        return $members_dropdowns;
    }


public function get_status_suggestion()
{
    $itemName = $this->request->getPost("item_name");
    $item = $this->Attendance_todo_model->get_status_info_suggestion($itemName);

    if ($item) {
        $result = [];
        foreach ($item as $i) {
            $result[] = $i->status;
        }
        return $this->response->setJSON(["success" => true, "item_info" => $result]);
    } else {
        return $this->response->setJSON(["success" => false]);
    }
}
public function ot_handler()
{
    $this->check_module_availability("module_attendance");

    $view_data['team_members_dropdown'] = json_encode($this->_get_members_dropdown_list_for_filter());
    $view_data['team_members_dropdowns'] = json_encode($this->_get_rm_members_dropdown_list_for_filter());

    return view("ot_handler/index", $view_data);
}

public function summary_ot_handler_details()
{
    $view_data['team_members_dropdown'] = json_encode($this->_get_members_dropdown_list_for_filter());
    $view_data['team_members_dropdowns'] = json_encode($this->_get_rm_members_dropdown_list_for_filter());

    return view("ot_handler/summary_ot_handler", $view_data);
}

public function monthly_ot_handler()
{
    $view_data['team_members_dropdown'] = json_encode($this->_get_members_dropdown_list_for_filter());
    $view_data['team_members_dropdowns'] = json_encode($this->_get_rm_members_dropdown_list_for_filter());

    return view("ot_handler/monthly_ot_handler", $view_data);
}

public function yearly_ot_handler()
{
    $view_data['team_members_dropdown'] = json_encode($this->_get_members_dropdown_list_for_filter());
    $view_data['team_members_dropdowns'] = json_encode($this->_get_rm_members_dropdown_list_for_filter());

    return view("ot_handler/yearly_ot_handler", $view_data);
}

public function weekly_ot_handler()
{
    $view_data['team_members_dropdown'] = json_encode($this->_get_members_dropdown_list_for_filter());
    $view_data['team_members_dropdowns'] = json_encode($this->_get_rm_members_dropdown_list_for_filter());

    return view("ot_handler/weekly_ot_handler", $view_data);
}

    /* get data the ot handler summary details tab */
    public function summary_details_list_ot_handler_data()
    {
        $request = service('request');
        $startDate = $request->getPost('start_date');
        $endDate = $request->getPost('end_date');
        $userId = $request->getPost('user_id');
        $userIds = $request->getPost('userr_id');

        // Prepare options based on user_id or userr_id
        if ($userId) {
            $options = [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'login_user_id' => $this->login_user->id,
                'user_id' => $userId,
                'access_type' => $this->access_type,
                'allowed_members' => $this->allowed_members,
                'summary_details' => true
            ];
        } else if ($userIds) {
            $options = [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'login_user_id' => $this->login_user->id,
                'user_id' => $userIds,
                'access_type' => $this->access_type,
                'allowed_members' => $this->allowed_members,
                'summary_details' => true
            ];
        } else {
            $options = [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'login_user_id' => $this->login_user->id,
                'user_id' => $userId,
                'access_type' => $this->access_type,
                'allowed_members' => $this->allowed_members,
                'summary_details' => true
            ];
        }

        // Fetch data from model
        $attendanceModel = new AttendanceModel();
        $listData = $attendanceModel->getSummaryDetails($options);

        // Process data and group by users
        $result = [];
        $lastUser = '';
        $lastTotalDuration = 0;
        $lastCreatedBy = '';
        $hasData = false;

        foreach ($listData as $data) {
            $oneDayWorkingHours = get_setting('company_working_hours_for_one_day');
            $oneDayWorkingSeconds = $oneDayWorkingHours * 60 * 60;
            $otHandler = $data->total_duration - $oneDayWorkingSeconds;

            if ($otHandler >= 0) {
                $otHandlerDuration = $otHandler;
                $imageURL = get_avatar($data->created_by_avatar);
                $user = "<span class='avatar avatar-xs mr10'><img src='$imageURL'></span> $data->created_by_user";

                if ($lastUser != $data->user_id) {
                    $lastUser = $data->user_id;

                    $result[] = [
                        $data->created_by_user,
                        get_team_member_profile_link($data->user_id, $user),
                        '',
                        '',
                        ''
                    ];

                    $lastKey = count($result) - 1;
                    $result[$lastKey][0] = $lastCreatedBy;
                    $result[$lastKey][3] = '<b>' . convert_seconds_to_time_format($lastTotalDuration) . '</b>';
                    $result[$lastKey][4] = '<b>' . to_decimal_format(convert_time_string_to_decimal(convert_seconds_to_time_format($lastTotalDuration))) . '</b>';
                    $result[$lastKey][5] = '<b>-</b>';
                    $result[$lastKey][6] = '<b>-</b>';
                    $result[$lastKey][7] = '<b>-</b>';

                    $lastTotalDuration = 0;
                }

                $lastTotalDuration += abs($otHandlerDuration);
                $lastCreatedBy = $data->created_by_user;
                $hasData = true;

                $duration = convert_seconds_to_time_format(abs($otHandlerDuration));
                $options = [
                    'start_date' => date('Y-m-d', strtotime($data->start_date)),
                    'user_id' => $data->user_id
                ];

                $todoModel = new AttendanceTodoModel();
                $listDataTodo = $todoModel->getDetails($options);
                $groupList = '';
                $i = 0;

                if ($listDataTodo) {
                    foreach ($listDataTodo as $group) {
                        if ($group->start_date) {
                            $i++;
                            $taskDetails = $this->TasksModel->getOne($group->task_id);
                            $taskTitle = $taskDetails->title ?? lang('not_specified');
                            $groupTodoTitle = $group->title ?? lang('not_specified');
                            $taskTitleLink = modal_anchor(get_uri('projects/task_view'), $taskTitle, ['title' => lang('task_info') . " #$data->id", 'data-post-id' => $taskDetails->id]);
                            $groupList .= "<ul style='text-align:left'>$i) <span style='font-weight:bold'>Todo do :</span> $groupTodoTitle $taskTitleLink &nbsp&nbsp&nbsp</ul><br>";
                        }
                    }
                }

                if ($groupList) {
                    $groupList = "<ol class='pl15'>$groupList</ol>";
                }

                $result[] = [
                    $data->created_by_user,
                    '',
                    attendance_format_to_date($data->start_date, false),
                    $duration,
                    to_decimal_format(convert_time_string_to_decimal($duration)),
                    $data->clock_in,
                    $data->clock_out,
                    $groupList
                ];
            }
        }

        if ($hasData) {
            $lastKey = count($result) - 1;
            $result[$lastKey][0] = $data->created_by_user;
            $result[$lastKey][3] = '<b>' . convert_seconds_to_time_format($lastTotalDuration) . '</b>';
            $result[$lastKey][4] = '<b>' . to_decimal_format(convert_time_string_to_decimal(convert_seconds_to_time_format($lastTotalDuration))) . '</b>';
            $result[$lastKey][5] = '<b>-</b>';
            $result[$lastKey][6] = '<b>-</b>';
            $result[$lastKey][7] = '<b>-</b>';
        }

        return $this->response->setJSON(['data' => $result]);
    }
// Get data for the OT handler daily summary details tab
public function daily_details_list_ot_handler_data()
{
    $request = service('request');
    $startDate = $request->getPost('start_date');
    $endDate = $request->getPost('end_date');
    $userIds = $request->getPost('userr_id');

    // Prepare options based on user_id or userr_id
    if ($userId) {
        $options = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'login_user_id' => $this->login_user->id,
            'user_id' => $userId,
            'access_type' => $this->access_type,
            'allowed_members' => $this->allowed_members,
            'summary_details' => true
        ];
    } else if ($userIds) {
        $options = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'login_user_id' => $this->login_user->id,
            'user_id' => $userIds,
            'access_type' => $this->access_type,
            'allowed_members' => $this->allowed_members,
            'summary_details' => true
        ];
    } else {
        $options = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'login_user_id' => $this->login_user->id,
            'user_id' => $userId,
            'access_type' => $this->access_type,
            'allowed_members' => $this->allowed_members,
            'summary_details' => true
        ];
    }

    // Fetch data from model
    $attendanceModel = new AttendanceModel();
    $listData = $attendanceModel->getSummaryDetails($options);

    // Process data and group by users
    $result = [];

    foreach ($listData as $data) {
        $oneDayWorkingHours = get_setting('company_working_hours_for_one_day');
        $oneDayWorkingSeconds = $oneDayWorkingHours * 60 * 60;
        $otHandler = $data->total_duration - $oneDayWorkingSeconds;

        if ($otHandler >= 0) {
            $otHandlerDuration = $otHandler;
            $imageURL = get_avatar($data->created_by_avatar);
            $user = "<span class='avatar avatar-xs mr10'><img src='$imageURL'></span> $data->created_by_user";

            $duration = convert_seconds_to_time_format(abs($otHandlerDuration));

            $options = [
                'start_date' => date('Y-m-d', strtotime($data->start_date)),
                'user_id' => $data->user_id
            ];

            $todoModel = new AttendanceTodoModel();
            $listDataTodo = $todoModel->getDetails($options);
            $groupList = '';
            $i = 0;

            if ($listDataTodo) {
                foreach ($listDataTodo as $group) {
                    if ($group->start_date) {
                        $i++;
                        $taskDetails = $this->TasksModel->getOne($group->task_id);
                        $taskTitle = $taskDetails->title ?? lang('not_specified');
                        $groupTodoTitle = $group->title ?? lang('not_specified');
                        $taskTitleLink = modal_anchor(get_uri('projects/task_view'), $taskTitle, ['title' => lang('task_info') . " #$data->id", 'data-post-id' => $taskDetails->id]);
                        $groupList .= "<ul style='text-align:left'>$i) <span style='font-weight:bold'>Todo do :</span> $groupTodoTitle $taskTitleLink &nbsp&nbsp&nbsp</ul><br>";
                    }
                }
            }

            if ($groupList) {
                $groupList = "<ol class='pl15'>$groupList</ol>";
            }

            $result[] = [
                get_team_member_profile_link($data->user_id, $user),
                attendance_format_to_date($data->start_date, false),
                $duration,
                to_decimal_format(convert_time_string_to_decimal($duration)),
                $data->clock_in,
                $data->clock_out,
                $groupList
            ];
        }
    }
   return $this->response->setJSON(['data' => $result]);
}
public function update_user_timezone()
{
    // Validate incoming data
    $rules = [
        'login_user_id' => 'required',
        'login_user_timezone' => 'required'
    ];

    if (!$this->validate($rules)) {
        return $this->failValidationErrors($this->validator->getErrors());
    }

    // Retrieve data from request
    $loginUserId = $this->request->getPost('login_user_id');
    $loginUserTimezone = $this->request->getPost('login_user_timezone');

    // Prepare data to update
    $data = [
        'user_timezone' => $loginUserTimezone
    ];

    // Save data to UsersModel
    $usersModel = new UsersModel();
    $saveId = $usersModel->update($loginUserId, $data);

    if ($saveId) {
        return $this->respond([
            'success' => true,
            'message' => lang('settings_updated')
        ]);
    } else {
        return $this->fail([
            'success' => false,
            'message' => 'Record cannot be updated'
        ]);
    }
}
}