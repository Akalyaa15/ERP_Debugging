<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use App\Models\AttendanceModel;
use App\Models\UsersModel;
use App\Models\SettingsModel;
use App\Models\TasksModel;
use App\Models\AttendanceTaskTodoModel;
use App\Models\AttendanceTodoModel;
use App\Models\CustomFieldsModel;
use App\Models\TaskStatusModel;

class Attendance extends BaseController
{
    protected $attendanceModel;
    protected $usersModel;
    protected $settingsModel;
    protected $tasksModel;
    protected $attendanceTaskTodoModel;
    protected $attendanceTodoModel;
    protected $customFieldsModel;
    protected $taskStatusModel;

    public function __construct()
    {
        $this->attendanceModel = new AttendanceModel();
        $this->usersModel = new UsersModel();
        $this->settingsModel = new SettingsModel();
        $this->tasksModel = new TasksModel();
        $this->attendanceTaskTodoModel = new AttendanceTaskTodoModel();
        $this->attendanceTodoModel = new AttendanceTodoModel();
        $this->customFieldsModel = new CustomFieldsModel();
        $this->taskStatusModel = new TaskStatusModel();

        // This module is accessible only to team members
        $this->accessOnlyTeamMembers();

        // We can set IP restriction to access this module. Validate user access
        $this->checkAllowedIp();

        // Initialize managerial permission
        $this->initPermissionChecker("attendance");
    }
   private function check_allowed_ip()
    {
        $session = session();
        if (!$session->has('is_admin')) {
            if ($session->get('work_mode') == '0') {
                $ip = Services::request()->getIPAddress();
                $allowed_ips = $this->SettingsModel->getSetting('allowed_ip_addresses');
                if ($allowed_ips) {
                    $allowed_ip_array = array_map('trim', preg_split('/\R/', $allowed_ips));
                    if (!in_array($ip, $allowed_ip_array)) {
                        return redirect()->to('forbidden');
                    }
                }
            }
        }
    }

    // Only admin or assigned members can access/manage other member's attendance
    protected function access_only_allowed_members($user_id = 0)
    {
        $session = session();
        if ($session->get('access_type') !== 'all') {
            if ($user_id !== (int)$session->get('id') || !in_array($user_id, $this->allowed_members)) {
                return redirect()->to('forbidden');
            }
        }
    }

    // Show attendance list view
    public function index()
    {
        $this->check_module_availability("module_attendance");

        $data['team_members_dropdown'] = json_encode($this->_get_members_dropdown_list_for_filter());
        $data['team_members_dropdowns'] = json_encode($this->_get_rm_members_dropdown_list_for_filter());

        echo view('attendance/index', $data);
    }

    // Show add/edit attendance modal
    public function modal_form()
    {
        helper(['form', 'url']);

        $id = $this->request->getPost('id');

        $data['time_format_24_hours'] = setting('time_format') == '24_hours';
        $data['model_info'] = $this->AttendanceModel->getOne($id);
        if ($data['model_info']->id) {
            $user_id = $data['model_info']->user_id;
            $this->access_only_allowed_members($user_id);
        }

        if ($user_id) {
            // Edit mode. Show user's info
            $data['team_members_info'] = $this->UsersModel->getOne($user_id);
        } else {
            // New add mode. Show users dropdown
            if (session()->get('access_type') === 'all') {
                $where = ['user_type' => 'staff'];
            } else {
                if (empty($this->allowed_members)) {
                    return redirect()->to('forbidden');
                }
                $where = ['user_type' => 'staff', 'id !=' => session()->get('id'), 'where_in' => ['id' => $this->allowed_members]];
            }

            $data['team_members_dropdown'] = array_merge(['' => '-'], $this->UsersModel->getDropdownList(['first_name', 'last_name'], 'id', $where));
        }

        echo view('attendance/modal_form', $data);
    }

    // Show attendance note modal
    public function note_modal_form()
    {
        helper(['form', 'url']);

        $id = $this->request->getPost('id');

        $data['clock_out'] = $this->request->getPost('clock_out');
        $data['todo_id'] = $id;
        $data['project_id'] = 0;

        $projects = $this->TasksModel->getMyProjectsDropdownList(session()->get('id'))->getResult();
        $projects_dropdown = [['id' => '', 'text' => '- ' . lang('project') . ' -']];
        foreach ($projects as $project) {
            if ($project->project_id && $project->project_title) {
                $projects_dropdown[] = ['id' => $project->project_id, 'text' => $project->project_title];
            }
        }

        $data['team_members_dropdown'] = json_encode($this->_get_team_members_dropdown($id));

        $data['custom_field_headers'] = $this->CustomFieldsModel->getCustomFieldHeadersForTable('tasks', session()->get('is_admin'), session()->get('user_type'));
        $data['task_statuses'] = $this->TaskStatusModel->getDetails()->getResult();
        $data['projects_dropdown'] = json_encode($projects_dropdown);
        $data['model_info'] = $this->AttendanceModel->getOne($id);

        echo view('attendance/note_modal_form', $data);
    }

    // Helper to get team members dropdown
    private function _get_team_members_dropdown($id)
    {
        // Implement your logic here to fetch team members dropdown
        return [];
    }

    // Make attendance save task item row
    private function _make_attendance_save_task_item_row($data = [], $return_type = 'row')
    {
        // Implement your logic here for attendance save task item row
        return '';
    }

    // Delete save checklist item
    public function delete_savechecklist_item($id)
    {
        $task_title_list_data = $this->AttendanceTaskTodoModel->getOne($id);
        $get_todo_id = $task_title_list_data->todo_id;

        $task_options = ['todo_id' => $get_todo_id];
        $atttask_table_list = $this->AttendanceTaskTodoModel->getDetails($task_options)->getNumRows();

        if ($atttask_table_list > 1) {
            if ($this->AttendanceTaskTodoModel->delete($id)) {
                return $this->respondDeleted(['success' => true]);
            } else {
                return $this->fail(['success' => false]);
            }
        } else {
            return $this->fail(['success' => false]);
        }
    }

    private function _make_checklist_item_row($data = [], $todo_id = 0, $clock_user_id, $return_type = "row")
    {
        $checkbox_class = "checkbox-blank";
        $title_class = "";
        $is_checked_value = 1;

        if ($data['is_checked'] == 1) {
            $is_checked_value = 0;
            $checkbox_class = "checkbox-checked";
            // $title_class = "text-line-through text-off";
        }

        $status = anchor("attendance/save_checklist_item/{$data['id']}/{$todo_id}/{$clock_user_id}", "<span class='$checkbox_class'></span>", ['class' => 'delete-checklist-item', 'title' => lang('save'), 'data-fade-out-on-success' => "#checklist-item-row-{$data['id']}"]);

        $title = "<span class='font-13 $title_class'>" . $data['title'] . "</span>";

        $delete = anchor("attendance/save_checklist_item/{$data['id']}/{$todo_id}/{$clock_user_id}", "<i class='fa fa-check-circle pull-right p3'></i>", ['class' => 'delete-checklist-item', 'title' => lang('save'), 'data-fade-out-on-success' => "#checklist-item-row-{$data['id']}"]);

        if ($return_type == "data") {
            return $status . $title . $delete;
        }

        return "<div id='checklist-item-row-{$data['id']}' class='list-group-item mb5 checklist-item-row' data-id='{$data['id']}'>" . $status . $title . $delete . "</div>";
    }

    // Save checklist item
    public function save_checklist_item($id, $todo_id, $clock_user_id)
    {
        $todo_model_info = $this->attendanceModel->find($todo_id);

        $now = date("Y-m-d");
        $start_date = date("Y-m-d", strtotime($todo_model_info['in_time']));
        $check_options = [
            "title" => $id,
            "start_date" => $start_date,
            "todo_id" => $todo_id,
            "user_id" => $clock_user_id
        ];

        $check_exists_todo = $this->attendanceTaskTodoModel->where($check_options)->findAll();
        if (empty($check_exists_todo)) {
            $data = [
                "title" => $id,
                "description" => $this->request->getPost('description') ?: "",
                "start_date" => $start_date,
                "todo_id" => $todo_id,
                "user_id" => $clock_user_id
            ];

            $save_id = $this->attendanceTaskTodoModel->insert($data);

            if ($save_id) {
                return $this->respond(["success" => true, "message" => lang('record_saved')]);
            } else {
                return $this->respond(["success" => false]);
            }
        }
    }

    public function log_sor_time()
    {
        $validation = \Config\Services::validation();
        $validation->setRule('id', 'numeric');

        if (!$validation->withRequest($this->request)->run()) {
            return $this->respond(["success" => false, "message" => lang('error_occurred')]);
        }

        $data = [
            "note" => $this->request->getPost('sor_note'),
            "in_time" => date("Y-m-d"),
            "status" => "incomplete",
            "user_id" => $this->login_user->id,
            "clockin_location" => $this->request->getPost('result')
        ];

        $save_id = $this->attendanceModel->save($data, $this->request->getPost('id'));

        if ($save_id) {
            return $this->respond(["success" => true, "data" => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved'), "clock_widget" => clock_widget(true)]);
        } else {
            return $this->respond(["success" => false, 'message' => lang('error_occurred')]);
        }
    }
    public function start_day_report_modal_form()
    {
        $view_data["clock_in"] = $this->request->getPost("clock_in");
        $view_data['model_info'] = $this->attendanceModel->find($this->request->getPost('id'));
        return view('attendance/add_todo/start_day_report_modal', $view_data);
    }
    public function todo_view()
    {
        $todo_id = $this->request->getPost('id');
        $model_info = $this->attendanceModel->find($todo_id);

        $view_data['model_info'] = $model_info;
        $view_data['todo_id'] = $todo_id;
        $view_data['project_id'] = 0;

        $projects = $this->tasksModel->get_my_projects_dropdown_list($this->login_user->id);
        $projects_dropdown = [['id' => '', 'text' => '- Project -']];
        foreach ($projects as $project) {
            if ($project['project_id'] && $project['project_title']) {
                $projects_dropdown[] = ['id' => $project['project_id'], 'text' => $project['project_title']];
            }
        }
        $view_data['projects_dropdown'] = json_encode($projects_dropdown);

        $team_members_dropdown = [['id' => '', 'text' => '- Team Member -']];
        $assigned_to_list = $this->usersModel->where(['deleted' => 0, 'user_type' => 'staff'])->findAll();
        foreach ($assigned_to_list as $key => $value) {
            if ($value['id'] == $this->login_user->id) {
                $team_members_dropdown[] = ['id' => $value['id'], 'text' => $value['first_name'] . ' ' . $value['last_name'], 'isSelected' => true];
            } else {
                $team_members_dropdown[] = ['id' => $value['id'], 'text' => $value['first_name'] . ' ' . $value['last_name']];
            }
        }
        $view_data['team_members_dropdown'] = json_encode($team_members_dropdown);

        $view_data['task_statuses'] = $this->tasksModel->get_details()->getResult();
        $view_data['custom_field_headers'] = $this->custom_fields_model->get_custom_field_headers_for_table("tasks", $this->login_user->is_admin, $this->login_user->user_type);

        return view('attendance/add_todo/add_todo', $view_data);
    }

    public function todo_save()
    {
        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'numeric',
            'title' => 'required'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $id = $this->request->getPost('id');
        $now = date("Y-m-d H:i:s");

        $data = [
            "title" => $this->request->getPost('title'),
            "start_date" => $now,
            "todo_id" => $id,
            "user_id" => $this->login_user->id
        ];

        $save_id = $this->attendanceTodoModel->save($data);

        // Update attendance in_time if necessary
        $attendance_user_info = $this->attendanceModel->find($id);
        $timestamp = $attendance_user_info->in_time;
        $splitTimeStamp = explode(" ", $timestamp);
        $time = $splitTimeStamp[1];
        if ($time == '00:00:00') {
            $this->attendanceModel->update($id, ["in_time" => $now]);
        }

        if ($save_id) {
            $item_info = $this->attendanceTodoModel->find($save_id);
            return $this->respondCreated([
                "success" => true,
                "data" => $this->_todo_make_row($item_info),
                'id' => $save_id,
                'clock_widget' => clock_widget(true),
                'message' => lang('record_saved')
            ]);
        } else {
            return $this->failServerError();
        }
    }



    public function add_todo_modal_save()
    {
        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'numeric',
            'todo_id' => 'required'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $id = $this->request->getPost('id');
        $todo_id = $this->request->getPost('todo_id');
        $now = date("Y-m-d H:i:s");

        $data = [
            "title" => $this->request->getPost('title'),
            "description" => $this->request->getPost('description') ?: "",
            "start_date" => $this->request->getPost('start_date') ?: $now,
            "todo_id" => $todo_id,
            "user_id" => $this->login_user->id
        ];

        $save_id = $this->attendanceTodoModel->save($data, $id);

        if ($save_id) {
            $item_info = $this->attendanceTodoModel->find($save_id);
            return $this->respond([
                "success" => true,
                "todo_id" => $item_info->todo_id,
                "data" => $this->_todo_make_row($item_info),
                'id' => $save_id,
                'message' => lang('record_saved')
            ]);
        } else {
            return $this->failServerError();
        }
    }
    /* upadate a task status */
    public function todo_save_status()
    {
        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'required|numeric',
            'status' => 'required'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $id = $this->request->getPost('id');
        $data = [
            "status" => $this->request->getPost('status')
        ];

        $save_id = $this->attendanceTodoModel->save($data, $id);

        if ($save_id) {
            return $this->respondCreated([
                "success" => true,
                "data" => $this->_todo_row_data($save_id),
                'id' => $save_id,
                "message" => lang('record_saved')
            ]);
        } else {
            return $this->failServerError(lang('error_occurred'));
        }
    }
    public function todo_title_view($id)
    {
        $model_info = $this->attendanceTodoModel->find($id);

        if (!$model_info) {
            return $this->failNotFound('TODO item not found');
        }

        $view_data['model_info'] = $model_info;
        return view('attendance/add_todo/todo_title_view', $view_data);
    }

    public function add_todo_modal_form($id)
    {
        $model_info = $this->attendanceTodoModel->find($id);

        if (!$model_info) {
            return $this->failNotFound('TODO item not found');
        }

        $view_data['model_info'] = $model_info;
        $view_data['todo_id'] = $model_info->todo_id;

        return view('attendance/add_todo/add_todo_modal_form', $view_data);
    }

    public function todo_delete($id)
    {
        $todo_info = $this->attendanceTodoModel->find($id);

        if (!$todo_info) {
            return $this->failNotFound('TODO item not found');
        }

        if ($this->attendanceTodoModel->delete($id)) {
            return $this->respondDeleted([
                "success" => true,
                'message' => lang('record_deleted')
            ]);
        } else {
            return $this->failServerError(lang('record_cannot_be_deleted'));
        }
    }

    public function todo_list_data($id = 0)
    {
        $status = $this->request->getPost('status') ? implode(",", $this->request->getPost('status')) : "";
        $options = [
            "todo_id" => $id,
            "user_id" => $this->login_user->id,
            "status" => $status
        ];

        $list_data = $this->attendanceTodoModel->where($options)->findAll();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_todo_make_row($data);
        }

        return $this->respond(['data' => $result]);
    }


    private function _todo_row_data($id)
    {
        $data = $this->attendanceTodoModel->find($id);
        return $this->_todo_make_row($data);
    }

    private function _todo_make_row($data)
    {
        $title = modal_anchor(
            route_to('todo_title_view', $data->id),
            $data->title,
            ['class' => 'edit', 'title' => lang('todo'), 'data-post-id' => $data->id]
        );

        $status_class = "";
        $checkbox_class = "checkbox-blank";
        if ($data->status === "to_do") {
            $status_class = "b-warning";
        } else {
            $checkbox_class = "checkbox-checked";
            $status_class = "b-success";
        }

        $check_status = js_anchor(
            "<span class='$checkbox_class'></span>",
            ['title' => "", "class" => "", "data-id" => $data->id, "data-value" => $data->status === "done" ? "to_do" : "done", "data-act" => "update-todo-status-checkbox"]
        );

        $start_date_text = is_date_exists($data->start_date) ? format_to_date($data->start_date, false) : "";
        if (get_my_local_time("Y-m-d") > $data->start_date && $data->status != "done") {
            $start_date_text = "<span class='text-danger'>" . $start_date_text . "</span>";
        } elseif (get_my_local_time("Y-m-d") == $data->start_date && $data->status != "done") {
            $start_date_text = "<span class='text-warning'>" . $start_date_text . "</span>";
        }

        $edit_button = "";
        if ($data->status != "done") {
            $edit_button = modal_anchor(
                route_to('add_todo_modal_form', $data->id),
                "<i class='fa fa-pencil'></i>",
                ["class" => "edit", "title" => lang('edit'), "data-post-id" => $data->id]
            ) . js_anchor(
                "<i class='fa fa-times fa-fw'></i>",
                [
                    'title' => lang('delete'),
                    "class" => "delete",
                    "data-id" => $data->id,
                    "data-action-url" => route_to('todo_delete', $data->id),
                    "data-action" => "delete-confirmation"
                ]
            );
        }

        return [
            $status_class,
            "<i class='hide'>" . $data->id . "</i>" . $check_status,
            $title,
            $data->start_date,
            $start_date_text,
            $edit_button
        ];
    }
    public function save()
    {
        $id = $this->request->getPost('id');
    
        $validationRules = [
            'id' => 'numeric',
            'in_date' => 'required',
            'out_date' => 'required',
            'in_time' => 'required',
            'out_time' => 'required'
        ];
    
        if (!$this->validate($validationRules)) {
            $errors = $this->validator->getErrors();
            return $this->response->setJSON(['success' => false, 'message' => $errors]);
        }
    
        // Convert to 24hrs time format
        $in_time = $this->request->getPost('in_time');
        $out_time = $this->request->getPost('out_time');
    
        if (get_setting('time_format') !== '24_hours') {
            $in_time = convert_time_to_24hours_format($in_time);
            $out_time = convert_time_to_24hours_format($out_time);
        }
    
        // Join date with time
        $in_date_time = $this->request->getPost('in_date') . ' ' . $in_time;
        $out_date_time = $this->request->getPost('out_date') . ' ' . $out_time;
    
        // Add time offset
        $in_date_time = convert_date_local_to_utc($in_date_time);
        $out_date_time = convert_date_local_to_utc($out_date_time);
    
        $data = [
            'in_time' => $in_date_time,
            'out_time' => $out_date_time,
            'status' => 'pending',
            'note' => $this->request->getPost('note')
        ];
    
        // Save user_id only on insert and it will not be editable
        if ($id) {
            $info = $this->Attendance_model->find($id);
            $user_id = $info['user_id'];
        } else {
            $user_id = $this->request->getPost('user_id');
            $data['user_id'] = $user_id;
        }
    
        $this->access_only_allowed_members($user_id);
    
        $save_id = $this->Attendance_model->save($data, $id);
        if ($save_id) {
            return $this->response->setJSON([
                'success' => true,
                'data' => $this->_row_data($save_id),
                'id' => $save_id,
                'isUpdate' => (bool)$id,
                'message' => lang('record_saved')
            ]);
        } else {
            return $this->response->setJSON([
                'success' => false,
                'message' => lang('error_occurred')
            ]);
        }
    }
    
    public function save_note()
    {
        $id = $this->request->getPost('id');
    
        $validationRules = [
            'id' => 'required|numeric',
        ];
    
        if (!$this->validate($validationRules)) {
            $errors = $this->validator->getErrors();
            return $this->response->setJSON(['success' => false, 'message' => $errors]);
        }
    
        $data = [
            'note' => $this->request->getPost('note'),
        ];
    
        $save_id = $this->Attendance_model->save($data, $id);
        if ($save_id) {
            return $this->response->setJSON([
                'success' => true,
                'data' => $this->_row_data($save_id),
                'id' => $save_id,
                'isUpdate' => true,
                'message' => lang('record_saved')
            ]);
        } else {
            return $this->response->setJSON([
                'success' => false,
                'message' => lang('error_occurred')
            ]);
        }
    }
    
    public function log_time()
    {
        $note = $this->request->getPost('note');
        $result = $this->request->getPost('result');
    
        $this->Attendance_model->log_time($this->login_user->id, $note, $result);
    
        if ($this->request->getPost("clock_out")) {
            return $this->response->setJSON([
                'success' => true,
                'clock_widget' => clock_widget(true)
            ]);
        } else {
            clock_widget();
        }
    }
    

    //delete/undo attendance record
    public function delete()
    {
        $validationRules = [
            'id' => 'required|numeric',
        ];
    
        if (!$this->validate($validationRules)) {
            $errors = $this->validator->getErrors();
            return $this->response->setJSON(['success' => false, 'message' => $errors]);
        }
    
        $id = $this->request->getPost('id');
    
        if ($this->access_type !== 'all') {
            $info = $this->Attendance_model->find($id);
            $this->access_only_allowed_members($info['user_id']);
        }
    
        if ($this->request->getPost('undo')) {
            if ($this->Attendance_model->delete($id, true)) {
                return $this->response->setJSON([
                    'success' => true,
                    'data' => $this->_row_data($id),
                    'message' => lang('record_undone')
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => lang('error_occurred')
                ]);
            }
        } else {
            if ($this->Attendance_model->delete($id)) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => lang('record_deleted')
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => lang('record_cannot_be_deleted')
                ]);
            }
        }
    }

    /* get all attendance of a given duration */

    public function list_data()
{
    $start_date = $this->request->getPost('start_date');
    $end_date = $this->request->getPost('end_date');
    $user_id = $this->request->getPost('user_id');
    $user_ids = $this->request->getPost('userr_id');

    if ($user_id) {
        $options = [
            "start_date" => $start_date,
            "end_date" => $end_date,
            "login_user_id" => $this->login_user->id,
            "user_id" => $user_id,
            "access_type" => $this->access_type,
            "allowed_members" => $this->allowed_members
        ];
    } else if ($user_ids) {
        $options = [
            "start_date" => $start_date,
            "end_date" => $end_date,
            "login_user_id" => $this->login_user->id,
            "user_id" => $user_ids,
            "access_type" => $this->access_type,
            "allowed_members" => $this->allowed_members
        ];
    } else {
        $options = [
            "start_date" => $start_date,
            "end_date" => $end_date,
            "login_user_id" => $this->login_user->id,
            "user_id" => $user_id,
            "access_type" => $this->access_type,
            "allowed_members" => $this->allowed_members
        ];
    }

    $list_data = $this->Attendance_model->get_details($options)->getResult();

    $result = [];
    foreach ($list_data as $data) {
        $result[] = $this->_make_row($data);
    }

    return $this->response->setJSON(["data" => $result]);
}


public function attendance_info()
{
    $this->check_module_availability("module_attendance");

    $viewData = [
        'user_id' => $this->login_user->id
    ];

    if ($this->request->isAJAX()) {
        return view("team_members/attendance_info", $viewData);
    } else {
        $viewData['page_type'] = "full";
        return view("team_members/attendance_info", $viewData);
    }
}
private function _row_data($id)
{
    $options = ["id" => $id];
    $data = $this->Attendance_model->get_details($options)->getRow();

    return $this->_make_row($data);
}


    //prepare a row of attendance list
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

    $option_links = modal_anchor(route_to("attendance/modal_form"), "<i class='fa fa-pencil'></i>", ['class' => 'edit', 'title' => lang('edit_attendance'), 'data-post-id' => $data->id])
        . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_attendance'), 'class' => 'delete', 'data-id' => $data->id, 'data-action-url' => route_to("attendance/delete"), 'data-action' => 'delete-confirmation']);

    if ($this->access_type != "all") {
        // Don't show option links for non-admin user's own records
        if ($data->user_id === $this->login_user->id) {
            $option_links = "";
        }
    }

    $note_link = modal_anchor(route_to("attendance/note_modal_form"), "<i class='fa fa-comment-o p10'></i>", ['class' => 'edit text-muted', 'title' => lang("note"), 'data-post-id' => $data->id]);
    if ($data->note) {
        $note_link = modal_anchor(route_to("attendance/note_modal_form"), "<i class='fa fa-comment p10'></i>", ['class' => 'edit text-muted', 'title' => $data->note, 'data-modal-title' => lang("note"), 'data-post-id' => $data->id]);
    }

    if ($data->user_user_type == "staff") {
        return [
            get_team_member_profile_link($data->user_id, $user),
            $data->in_time,
            format_to_date($data->in_time),
            format_to_time($data->in_time),
            $out_time ? $out_time : 0,
            $out_time ? format_to_date($out_time) : "-",
            $out_time ? format_to_time($out_time) : "-",
            $data->clockin_location,
            $data->clockout_location,
            convert_seconds_to_time_format(abs($to_time - $from_time)),
            $note_link,
            $option_links
        ];
    }
    if ($data->user_user_type == "resource") {
        return [
            get_rm_member_profile_link($data->user_id, $user),
            $data->in_time,
            format_to_date($data->in_time),
            format_to_time($data->in_time),
            $out_time ? $out_time : 0,
            $out_time ? format_to_date($out_time) : "-",
            $out_time ? format_to_time($out_time) : "-",
            $data->clockin_location,
            $data->clockout_location,
            convert_seconds_to_time_format(abs($to_time - $from_time)),
            $note_link,
            $option_links
        ];
    }
}

    //load the custom date view of attendance list 
    public function custom()
    {
        $viewData = [
            'team_members_dropdown' => json_encode($this->_get_members_dropdown_list_for_filter()),
            'team_members_dropdowns' => json_encode($this->_get_rm_members_dropdown_list_for_filter())
        ];
    
        return view("attendance/custom_list", $viewData);
    }
    

    //load the clocked in members list view of attendance list 
    public function members_clocked_in()
{
    return view("attendance/members_clocked_in");
}
private function _get_members_dropdown_list_for_filter()
{
    // Prepare the dropdown list of members
    // Don't show none allowed members in dropdown

    if ($this->access_type === "all") {
        $where = ["user_type" => "staff"];
    } else {
        if (empty($this->allowed_members)) {
            $where = ["user_type" => "nothing"]; // Don't show any users in dropdown
        } else {
            // Add login user in dropdown list
            $allowed_members = $this->allowed_members;
            $allowed_members[] = $this->login_user->id;

            $where = [
                "user_type" => "staff",
                "whereIn" => ["id" => $allowed_members]
            ];
        }
    }

    $members = $this->Users_model->select("first_name, last_name, id")
                                 ->where($where)
                                 ->findAll();

    $members_dropdown = [["id" => "", "text" => "- " . lang("member") . " -"]];
    foreach ($members as $member) {
        $members_dropdown[] = ["id" => $member['id'], "text" => $member['first_name'] . ' ' . $member['last_name']];
    }

    return $members_dropdown;
}

private function _get_rm_members_dropdown_list_for_filter()
{
    // Prepare the dropdown list of members
    // Don't show none allowed members in dropdown

    if ($this->access_type === "all") {
        $where = ["user_type" => "resource"];
    } else {
        if (empty($this->allowed_members)) {
            $where = ["user_type" => "nothing"]; // Don't show any users in dropdown
        } else {
            // Add login user in dropdown list
            $allowed_members = $this->allowed_members;
            $allowed_members[] = $this->login_user->id;

            $where = [
                "user_type" => "staff",
                "whereIn" => ["id" => $allowed_members]
            ];
        }
    }

    $members = $this->Users_model->select("first_name, last_name, id")
                                 ->where($where)
                                 ->findAll();

    $members_dropdowns = [["id" => "", "text" => "- " . lang("outsource_member") . " -"]];
    foreach ($members as $member) {
        $members_dropdowns[] = ["id" => $member['id'], "text" => $member['first_name'] . ' ' . $member['last_name']];
    }

    return $members_dropdowns;
}

public function summary()
{
    $viewData = [
        'team_members_dropdown' => json_encode($this->_get_members_dropdown_list_for_filter()),
        'team_members_dropdowns' => json_encode($this->_get_rm_members_dropdown_list_for_filter())
    ];

    return view("attendance/summary_list", $viewData);
}

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

public function summary_details()
{
    $viewData = [
        'team_members_dropdown' => json_encode($this->_get_members_dropdown_list_for_filter()),
        'team_members_dropdowns' => json_encode($this->_get_rm_members_dropdown_list_for_filter())
    ];

    return view("attendance/summary_details_list", $viewData);
}

public function summary_details_list_data()
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
        "allowed_members" => $this->allowed_members,
        "summary_details" => true
    ];

    $list_data = $this->Attendance_model->get_summary_details($options)->getResult();

    // Group the list by users
    $result = [];
    $last_key = 0;
    $last_user = "";
    $last_total_duration = 0;
    $last_created_by = "";
    $has_data = false;

    foreach ($list_data as $data) {
        $image_url = get_avatar($data->created_by_avatar);
        $user = "<span class='avatar avatar-xs mr10'><img src='$image_url'></span> $data->created_by_user";

        $duration = convert_seconds_to_time_format(abs($data->total_duration));

        // Found a new user, add new row for the total
        if ($last_user != $data->user_id) {
            $last_user = $data->user_id;

            $result[] = [
                $data->created_by_user,
                get_team_member_profile_link($data->user_id, $user),
                "",
                "",
                "",
                ""
            ];

            $result[$last_key][0] = $last_created_by;
            $result[$last_key][3] = "<b>" . convert_seconds_to_time_format($last_total_duration) . "</b>";
            $result[$last_key][4] = "<b>" . to_decimal_format(convert_time_string_to_decimal(convert_seconds_to_time_format($last_total_duration))) . "</b>";
            $result[$last_key][5] = "<b>-</b>";
            $result[$last_key][6] = "<b>-</b>";
            $result[$last_key][7] = "<b>-</b>";
            $result[$last_key][8] = "<b>-</b>";

            $last_total_duration = 0;
            $last_key = count($result) - 1;
        }

        $last_total_duration += abs($data->total_duration);
        $last_created_by = $data->created_by_user;
        $has_data = true;

        $duration = convert_seconds_to_time_format(abs($data->total_duration));

        $options = [
            "start_date" => format_to_date($data->start_date, false),
            "user_id" => $data->user_id
        ];

        $list_data = $this->Attendance_todo_model->get_details($options)->getResult();
        $group_list = "";
        $i = 0;
        if ($list_data) {
            foreach ($list_data as $group) {
                if ($group->start_date) {
                    $i++;
                    $group_list .= "<ul style='text-align:left'>" . $i . ')' . $group->title . '&nbsp&nbsp&nbsp' . "</ul>";
                }
            }
        }
        if ($group_list) {
            $group_list = "<ol class='pl15'>" . $group_list . "</ol>";
        }

        /* Attendance task todo */

        $attendance_task_list_data = $this->Attendance_task_todo_model->get_details($options)->getResult();

        // Remove duplicate value
        $task_array = [];
        foreach ($attendance_task_list_data as $group) {
            $task_array[] = $group->title;
        }
        $task_unique = array_unique($task_array);

        $attendance_task_group_list = "";
        $attendance_task_no = 0;
        if ($attendance_task_list_data) {
            foreach ($task_unique as $attendance_task_todo) {
                $attendance_task_no++;
                $attendance_task_group_list_data = $this->Tasks_model->find($attendance_task_todo);
                $attendance_task_group_list .= "<ul style='text-align:left'>" . $attendance_task_no . ')' . $attendance_task_group_list_data->title . '&nbsp&nbsp&nbsp' . "</ul>";
            }
        }
        if ($attendance_task_group_list) {
            $attendance_task_group_list = "<ol class='pl15'>" . $attendance_task_group_list . "</ol>";
        }

        /* End attendance task todo  */

        $result[] = [
            $data->created_by_user,
            "",
            format_to_date($data->start_date, false),
            $duration,
            to_decimal_format(convert_time_string_to_decimal($duration)),
            $data->clock_in,
            $data->clock_out,
            $attendance_task_group_list,
            $group_list
        ];
    }

    if ($has_data) {
        $result[$last_key][0] = $data->created_by_user;
        $result[$last_key][3] = "<b>" . convert_seconds_to_time_format($last_total_duration) . "</b>";
        $result[$last_key][4] = "<b>" . to_decimal_format(convert_time_string_to_decimal(convert_seconds_to_time_format($last_total_duration))) . "</b>";
        $result[$last_key][5] = "<b>-</b>";
        $result[$last_key][6] = "<b>-</b>";
        $result[$last_key][7] = "<b>-</b>";
        $result[$last_key][8] = "<b>-</b>";
    }

    return $this->response->setJSON(["data" => $result]);
}

public function clocked_in_members_list_data()
{
    $options = [
        "login_user_id" => $this->login_user->id,
        "access_type" => $this->access_type,
        "allowed_members" => $this->allowed_members,
        "only_clocked_in_members" => true
    ];

    $list_data = $this->Attendance_model->get_details($options)->getResult();

    $result = [];
    foreach ($list_data as $data) {
        $result[] = $this->_make_row($data);
    }

    return $this->response->setJSON(["data" => $result]);
}
public function get_status_suggestion()
{
    $item_name = $this->request->getPost("item_name");
    $item = $this->Attendance_todo_model->get_status_info_suggestion($item_name);

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

    $viewData = [
        'team_members_dropdown' => json_encode($this->_get_members_dropdown_list_for_filter()),
        'team_members_dropdowns' => json_encode($this->_get_rm_members_dropdown_list_for_filter())
    ];

    return view("ot_handler/index", $viewData);
}



public function summary_ot_handler_details()
{
    $viewData = [
        'team_members_dropdown' => json_encode($this->_get_members_dropdown_list_for_filter()),
        'team_members_dropdowns' => json_encode($this->_get_rm_members_dropdown_list_for_filter())
    ];

    return view("ot_handler/summary_ot_handler", $viewData);
}

public function monthly_ot_handler()
{
    $viewData = [
        'team_members_dropdown' => json_encode($this->_get_members_dropdown_list_for_filter()),
        'team_members_dropdowns' => json_encode($this->_get_rm_members_dropdown_list_for_filter())
    ];

    return view("ot_handler/monthly_ot_handler", $viewData);
}
public function yearly_ot_handler()
{
    $viewData = [
        'team_members_dropdown' => json_encode($this->_get_members_dropdown_list_for_filter()),
        'team_members_dropdowns' => json_encode($this->_get_rm_members_dropdown_list_for_filter())
    ];

    return view("ot_handler/yearly_ot_handler", $viewData);
}
public function weekly_ot_handler()
{
    $viewData = [
        'team_members_dropdown' => json_encode($this->_get_members_dropdown_list_for_filter()),
        'team_members_dropdowns' => json_encode($this->_get_rm_members_dropdown_list_for_filter())
    ];

    return view("ot_handler/weekly_ot_handler", $viewData);
}

    /* get data the ot handler summary details tab */

    public function summary_details_list_ot_handler_data()
    {
        $start_date = $this->request->getPost('start_date');
        $end_date = $this->request->getPost('end_date');
        $user_id = $this->request->getPost('user_id');
        $user_ids = $this->request->getPost('userr_id');
    
        if ($user_id) {
            $options = [
                "start_date" => $start_date,
                "end_date" => $end_date,
                "login_user_id" => $this->login_user->id,
                "user_id" => $user_id,
                "access_type" => $this->access_type,
                "allowed_members" => $this->allowed_members,
                "summary_details" => true
            ];
        } else if ($user_ids) {
            $options = [
                "start_date" => $start_date,
                "end_date" => $end_date,
                "login_user_id" => $this->login_user->id,
                "user_id" => $user_ids,
                "access_type" => $this->access_type,
                "allowed_members" => $this->allowed_members,
                "summary_details" => true
            ];
        } else {
            $options = [
                "start_date" => $start_date,
                "end_date" => $end_date,
                "login_user_id" => $this->login_user->id,
                "user_id" => $user_id,
                "access_type" => $this->access_type,
                "allowed_members" => $this->allowed_members,
                "summary_details" => true
            ];
        }
    
        $list_data = $this->Attendance_model->get_summary_details($options)->getResult();
    
        // Prepare data for JSON response
        $result = [];
        $last_key = 0;
        $last_user = "";
        $last_total_duration = 0;
        $last_created_by = "";
        $has_data = false;
    
        foreach ($list_data as $data) {
            $one_day_working_hours = get_setting('company_working_hours_for_one_day');
            $one_day_working_seconds = $one_day_working_hours * 60 * 60;
            $ot_handler = $data->total_duration - $one_day_working_seconds;
    
            if ($ot_handler >= 0) {
                $ot_handler_duration = $ot_handler;
    
                $image_url = get_avatar($data->created_by_avatar);
                $user = "<span class='avatar avatar-xs mr10'><img src='$image_url'></span> $data->created_by_user";
    
                $duration = convert_seconds_to_time_format(abs($ot_handler_duration));
    
                // Add new row for each user
                if ($last_user != $data->user_id) {
                    $last_user = $data->user_id;
    
                    $result[] = [
                        $data->created_by_user,
                        get_team_member_profile_link($data->user_id, $user),
                        "",
                        "",
                        ""
                    ];
    
                    $result[$last_key][0] = $last_created_by;
                    $result[$last_key][3] = "<b>" . convert_seconds_to_time_format($last_total_duration) . "</b>";
                    $result[$last_key][4] = "<b>" . to_decimal_format(convert_time_string_to_decimal(convert_seconds_to_time_format($last_total_duration))) . "</b>";
                    $result[$last_key][5] = "<b>-</b>";
                    $result[$last_key][6] = "<b>-</b>";
                    $result[$last_key][7] = "<b>-</b>";
                    $result[$last_key][8] = "<b>-</b>";
    
                    $last_total_duration = 0;
                    $last_key = count($result) - 1;
                }
    
                $last_total_duration += abs($ot_handler_duration);
                $last_created_by = $data->created_by_user;
                $has_data = true;
    
                $duration = convert_seconds_to_time_format(abs($ot_handler_duration));
                $options = [
                    "start_date" => format_to_date($data->start_date, false),
                    "user_id" => $data->user_id
                ];
    
                // Get details from Attendance_todo_model
                $list_data = $this->Attendance_todo_model->get_details($options)->getResult();
                $group_list = "";
                $i = 0;
    
                if ($list_data) {
                    foreach ($list_data as $group) {
                        if ($group->start_date) {
                            $i++;
                            $group_list .= "<ul style='text-align:left'>" . $i . ')' . $group->title . '&nbsp&nbsp&nbsp' . "</ul>";
                        }
                    }
                }
                if ($group_list) {
                    $group_list = "<ol class='pl15'>" . $group_list . "</ol>";
                }
    
                // Get details from Attendance_task_todo_model
                $attendance_task_list_data = $this->Attendance_task_todo_model->get_details($options)->getResult();
    
                $task_array = [];
                foreach ($attendance_task_list_data as $group) {
                    $task_array[] = $group->title;
                }
                $task_unique = array_unique($task_array);
    
                $attendance_task_group_list = "";
                $attendance_task_no = 0;
                if ($attendance_task_list_data) {
                    foreach ($task_unique as $attendance_task_todo) {
                        $attendance_task_no++;
                        $attendance_task_group_list_data = $this->Tasks_model->get_one($attendance_task_todo);
                        $attendance_task_group_list .= "<ul style='text-align:left'>" . $attendance_task_no . ')' . $attendance_task_group_list_data->title . '&nbsp&nbsp&nbsp' . "</ul>";
                    }
                }
                if ($attendance_task_group_list) {
                    $attendance_task_group_list = "<ol class='pl15'>" . $attendance_task_group_list . "</ol>";
                }
    
                // Push data into result array
                $result[] = [
                    $data->created_by_user,
                    "",
                    format_to_date($data->start_date, false),
                    $duration,
                    to_decimal_format(convert_time_string_to_decimal($duration)),
                    $data->clock_in,
                    $data->clock_out,
                    $attendance_task_group_list,
                    $group_list
                ];
            }
        }
    
        // Final adjustments
        if ($has_data) {
            $result[$last_key][0] = $data->created_by_user;
            $result[$last_key][3] = "<b>" . convert_seconds_to_time_format($last_total_duration) . "</b>";
            $result[$last_key][4] = "<b>" . to_decimal_format(convert_time_string_to_decimal(convert_seconds_to_time_format($last_total_duration))) . "</b>";
            $result[$last_key][5] = "<b>-</b>";
            $result[$last_key][6] = "<b>-</b>";
            $result[$last_key][7] = "<b>-</b>";
            $result[$last_key][8] = "<b>-</b>";
        }
    
        // Return JSON response
        return $this->response->setJSON(["data" => $result]);
    }
    public function daily_details_list_ot_handler_data()
{
    $start_date = $this->request->getPost('start_date');
    $end_date = $this->request->getPost('end_date');
    $user_ids = $this->request->getPost('userr_id');
    
    // Determine user_id based on condition
    $user_id = $this->request->getPost('user_id') ?? null;

    // Prepare options based on input
    if ($user_id) {
        $options = [
            "start_date" => $start_date,
            "end_date" => $end_date,
            "login_user_id" => $this->login_user->id,
            "user_id" => $user_id,
            "access_type" => $this->access_type,
            "allowed_members" => $this->allowed_members,
            "summary_details" => true
        ];
    } else if ($user_ids) {
        $options = [
            "start_date" => $start_date,
            "end_date" => $end_date,
            "login_user_id" => $this->login_user->id,
            "user_id" => $user_ids,
            "access_type" => $this->access_type,
            "allowed_members" => $this->allowed_members,
            "summary_details" => true
        ];
    } else {
        $options = [
            "start_date" => $start_date,
            "end_date" => $end_date,
            "login_user_id" => $this->login_user->id,
            "user_id" => $user_id,
            "access_type" => $this->access_type,
            "allowed_members" => $this->allowed_members,
            "summary_details" => true
        ];
    }

    // Fetch data from Attendance_model
    $list_data = $this->Attendance_model->get_summary_details($options)->getResult();

    // Initialize result array
    $result = [];

    foreach ($list_data as $data) {
        $one_day_working_hours = get_setting('company_working_hours_for_one_day');
        $one_day_working_seconds = $one_day_working_hours * 60 * 60;
        $ot_handler = $data->total_duration - $one_day_working_seconds;

        // Process data if ot_handler is greater than or equal to 0
        if ($ot_handler >= 0) {
            $ot_handler_duration = $ot_handler;

            $image_url = get_avatar($data->created_by_avatar);
            $user = "<span class='avatar avatar-xs mr10'><img src='$image_url'></span> $data->created_by_user";

            $duration = convert_seconds_to_time_format(abs($ot_handler_duration));

            // Fetch details from Attendance_todo_model
            $options = [
                "start_date" => format_to_date($data->start_date, false),
                "user_id" => $data->user_id
            ];
            $todo_list_data = $this->Attendance_todo_model->get_details($options)->getResult();
            $group_list = "";
            $i = 0;

            // Prepare group list for output
            if ($todo_list_data) {
                foreach ($todo_list_data as $group) {
                    if ($group->start_date) {
                        $i++;
                        $group_list .= "<ul style='text-align:left'>" . $i . ') ' . $group->title . '&nbsp&nbsp&nbsp' . "</ul>";
                    }
                }
            }
            if ($group_list) {
                $group_list = "<ol class='pl15'>" . $group_list . "</ol>";
            }

            // Fetch details from Attendance_task_todo_model
            $attendance_task_list_data = $this->Attendance_task_todo_model->get_details($options)->getResult();

            // Remove duplicate values from task list
            $task_array = [];
            foreach ($attendance_task_list_data as $group) {
                $task_array[] = $group->title;
            }
            $task_unique = array_unique($task_array);

            $attendance_task_group_list = "";
            $attendance_task_no = 0;

            // Prepare attendance task list for output
            if ($attendance_task_list_data) {
                foreach ($task_unique as $attendance_task_todo) {
                    $attendance_task_no++;
                    $attendance_task_group_list_data = $this->Tasks_model->get_one($attendance_task_todo);
                    $attendance_task_group_list .= "<ul style='text-align:left'>" . $attendance_task_no . ') ' . $attendance_task_group_list_data->title . '&nbsp&nbsp&nbsp' . "</ul>";
                }
            }
            if ($attendance_task_group_list) {
                $attendance_task_group_list = "<ol class='pl15'>" . $attendance_task_group_list . "</ol>";
            }

            // Prepare data for result array
            $result[] = [
                get_team_member_profile_link($data->user_id, $user),
                format_to_date($data->start_date, false),
                $duration,
                to_decimal_format(convert_time_string_to_decimal($duration)),
                $data->clock_in,
                $data->clock_out,
                $attendance_task_group_list,
                $group_list
            ];
        }
    }

    // Return JSON response
    return $this->response->setJSON(["data" => $result]);
}
}
/* End of file attendance.php */
/* Location: ./application/controllers/attendance.php */