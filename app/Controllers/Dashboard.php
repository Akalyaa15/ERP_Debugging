<?php

namespace App\Controllers;

use App\Models\CustomWidgetsModel;
use App\Models\CustomFieldsModel;
use App\Models\SettingsModel;
use App\Models\ClientsModel;
use App\Models\VendorsModel;
use App\Models\UsersModel;
use App\Models\DashboardsModel;

class Dashboard extends BaseController {
    protected $dashboardsModel;
    protected $customWidgetsModel;
    protected $customFieldsModel;
    protected $settingsModel;
    protected $clientsModel;
    protected $vendorsModel;
    protected $usersModel;

    public function __construct() {
        parent::__construct();
        $this->dashboardsModel = new DashboardsModel();
        $this->customWidgetsModel = new CustomWidgetsModel();
        $this->customFieldsModel = new CustomFieldsModel();
        $this->settingsModel = new SettingsModel();
        $this->clientsModel = new ClientsModel();
        $this->vendorsModel = new VendorsModel();
        $this->usersModel = new UsersModel();
    }

    public function index() {
        $widgets = $this->_checkWidgetsPermissions();

        $viewData["dashboards"] = $this->dashboardsModel->getDetails(['user_id' => $this->loginUser->id])->getResult();
        $viewData["dashboard_type"] = "default";

        if (in_array($this->loginUser->user_type, ['staff', 'resource'])) {
            $viewData["show_timeline"] = $widgets['new_posts'] ?? null;
            $viewData["show_attendance"] = $widgets['clock_in_out'] ?? null;
            $viewData["show_event"] = $widgets['events_today'] ?? null;
            $viewData["show_project_timesheet"] = $widgets['timesheet_statistics'] ?? null;
            $viewData["show_income_vs_expenses"] = $widgets['income_vs_expenses'] ?? null;
            if ($this->loginUser->user_type === "staff") {
                $viewData["show_invoice_statistics"] = $widgets['invoice_statistics'] ?? null;
            }
            $viewData["show_ticket_status"] = $widgets['ticket_status'] ?? null;
            $viewData["show_clock_status"] = $widgets['clock_status'] ?? null;

            return view('dashboards/index', $viewData);
        } elseif ($this->loginUser->user_type === "client") {
            $viewData['show_invoice_info'] = $widgets['show_invoice_info'] ?? null;
            $viewData['hidden_menu'] = $widgets['hidden_menu'] ?? null;
            $viewData['client_info'] = $widgets['client_info'] ?? null;
            $viewData['client_id'] = $widgets['client_id'] ?? null;
            $viewData['page_type'] = $widgets['page_type'] ?? null;
            $viewData["custom_field_headers"] = $this->customFieldsModel->getCustomFieldHeadersForTable("projects", $this->loginUser->is_admin, $this->loginUser->user_type);

            return view('dashboards/client_dashboard', $viewData);
        } elseif ($this->loginUser->user_type === "vendor") {
            $viewData['show_invoice_info'] = $widgets['show_invoice_info'] ?? null;
            $viewData['show_estimate_info'] = $widgets['show_estimate_info'] ?? null;
            $viewData['hidden_menu'] = $widgets['hidden_menu'] ?? null;
            $viewData['vendor_info'] = $widgets['vendor_info'] ?? null;
            $viewData['vendor_id'] = $widgets['vendor_id'] ?? null;
            $viewData['page_type'] = $widgets['page_type'] ?? null;
            $viewData["custom_field_headers"] = $this->customFieldsModel->getCustomFieldHeadersForTable("projects", $this->loginUser->is_admin, $this->loginUser->user_type);

            return view('dashboards/vendor_dashboard', $viewData);
        }

        $this->settingsModel->saveSetting("user_" . $this->loginUser->id . "_dashboard", "", "user");
    }

    private function _checkWidgetsPermissions() {
        if (in_array($this->loginUser->user_type, ['staff', 'resource'])) {
            return $this->_checkWidgetsForStaffs();
        } elseif ($this->loginUser->user_type === "client") {
            return $this->_checkWidgetsForClients();
        } elseif ($this->loginUser->user_type === "vendor") {
            return $this->_checkWidgetsForVendors();
        }
    }

    private function _checkWidgetsForStaffs() {
        //check which widgets are viewable to current logged in user
        $widget = [];

        $showAttendance = get_setting("module_attendance");
        $showInvoice = get_setting("module_invoice");
        $showExpense = get_setting("module_expense");
        $showTicket = get_setting("module_ticket");
        $showEvents = get_setting("module_event");
        $showMessage = get_setting("module_message");

        $accessExpense = $this->getAccessInfo("expense");
        $accessInvoice = $this->getAccessInfo("invoice");
        $accessTicket = $this->getAccessInfo("ticket");
        $accessTimecards = $this->getAccessInfo("attendance");

        $widget["new_posts"] = get_setting("module_timeline");
        $widget["timesheet_statistics"] = get_setting("module_project_timesheet");

        if ($showAttendance) {
            $widget["clock_in_out"] = true;
            $widget["timecard_statistics"] = true;
        }

        if ($showEvents) {
            $widget["events_today"] = true;
            $widget["events"] = true;
        }

        if (get_setting("module_todo")) {
            $widget["todo_list"] = true;
        }

        //check module availability and access permission to show any widget
        if ($showInvoice && $showExpense && $accessExpense->access_type === "all" && $accessInvoice->access_type === "all") {
            $widget["income_vs_expenses"] = true;
        }

        if ($showInvoice && $accessInvoice->access_type === "all") {
            $widget["invoice_statistics"] = true;
        }

        if ($showTicket && $accessTicket->access_type === "all") {
            $widget["ticket_status"] = true;
        }

        if ($showAttendance && $accessTimecards->access_type === "all") {
            $widget["clock_status"] = true;
            $widget["members_clocked_in"] = true;
            $widget["members_clocked_out"] = true;
        }

        if ($showTicket && ($this->loginUser->is_admin || $accessTicket->access_type)) {
            $widget["new_tickets"] = true;
            $widget["open_tickets"] = true;
            $widget["closed_tickets"] = true;
        }

        if ($this->canViewTeamMembersList()) {
            $widget["all_team_members"] = true;
        }

        if ($this->canViewTeamMembersList() && $showAttendance && $accessTimecards->access_type === "all") {
            $widget["clocked_in_team_members"] = true;
            $widget["clocked_out_team_members"] = true;
        }

        if ($this->canViewTeamMembersList() && $showMessage) {
            $widget["latest_online_team_members"] = true;
        }

        $this->initPermissionChecker("client");
        if ($showMessage) {
            if ($this->access_type === "all") {
                $widget["latest_online_client_contacts"] = true;
            } else if ($this->module_group === "ticket" && $this->access_type === "specific") {
                $widget["latest_online_client_contacts"] = true;
            }
        }

        if ($showInvoice && ($this->loginUser->is_admin || $accessInvoice->access_type)) {
            $widget["total_invoices"] = true;
            $widget["total_payments"] = true;
        }

        if ($showExpense && $showInvoice && $accessInvoice->access_type) {
            $widget["total_due"] = true;
        }

        //universal widgets
        $widget["my_open_tasks"] = true;
        $widget["open_projects"] = true;
        $widget["completed_projects"] = true;
        $widget["project_timeline"] = true;
        $widget["task_status"] = true;
        $widget["sticky_note"] = true;
        $widget["all_tasks_kanban"] = true;
        $widget["open_projects_list"] = true;
        $widget["starred_projects"] = true;
        $widget["my_tasks_list"] = true;

        return $widget;
    }

    private function _check_widgets_for_clients() {
        $widget = [];

        $options = ["id" => $this->loginUser->client_id];
        $client_info = $this->clientsModel->getDetails($options)->getRow();
        $hidden_menu = explode(",", get_setting("hidden_client_menus"));

        $show_invoice_info = get_setting("module_invoice");
        $show_events = get_setting("module_event");

        $widget['show_invoice_info'] = $show_invoice_info;
        $widget['hidden_menu'] = $hidden_menu;
        $widget['client_info'] = $client_info;
        $widget['client_id'] = $client_info->id;
        $widget['page_type'] = "dashboard";

        if ($show_invoice_info) {
            if (!in_array("projects", $hidden_menu)) {
                $widget["total_projects"] = true;
            }
            if (!in_array("invoices", $hidden_menu)) {
                $widget["total_invoices"] = true;
            }
            if (!in_array("payments", $hidden_menu)) {
                $widget["total_payments"] = true;
                $widget["total_due"] = true;
            }
        }

        if (!in_array("projects", $hidden_menu)) {
            $widget["open_projects_list"] = true;
        }

        if ($show_events && !in_array("events", $hidden_menu)) {
            $widget["events"] = true;
        }

        if ($show_invoice_info && !in_array("invoices", $hidden_menu)) {
            $widget["invoice_statistics"] = true;
        }

        if ($show_events && !in_array("events", $hidden_menu)) {
            $widget["events_today"] = true;
        }

        if (get_setting("module_todo")) {
            $widget["todo_list"] = true;
        }

        if (!in_array("tickets", $hidden_menu) && get_setting("module_ticket") && $this->access_only_allowed_members_or_client_contact($this->loginUser->client_id)) {
            $widget["new_tickets"] = true;
            $widget["open_tickets"] = true;
            $widget["closed_tickets"] = true;
        }

        $widget["sticky_note"] = true;

        return $widget;
    }

    private function _check_widgets_for_vendors() {
        $builder = $this->db->table('users');
        $builder->select("vendor_id");
        $builder->where('deleted', 0);
        $builder->where('id', $this->loginUser->id);
        $query = $builder->get();
        $vendor_id = $query->getRow()->vendor_id;

        $widget = [];

        $options = ["id" => $vendor_id];
        $vendor_info = $this->vendorsModel->getDetails($options)->getRow();
        $hidden_menu = explode(",", get_setting("hidden_vendor_menus"));

        $show_invoice_info = get_setting("module_purchase_order");
        $show_estimate_info = get_setting("module_work_order");
        $show_events = get_setting("module_event");

        $widget['show_invoice_info'] = $show_invoice_info;
        $widget['show_estimate_info'] = $show_estimate_info;
        $widget['hidden_menu'] = $hidden_menu;
        $widget['vendor_info'] = $vendor_info;
        $widget['vendor_id'] = $vendor_info->id;
        $widget['page_type'] = "dashboard";

        if ($show_invoice_info) {
            if (!in_array("purchase_orders", $hidden_menu)) {
                $widget["total_purchase_order"] = true;
            }
            if (!in_array("purchase_orders", $hidden_menu)) {
                $widget["purchase_order_value"] = true;
            }
            if (!in_array("purchase_orders", $hidden_menu)) {
                $widget["total_payments"] = true;
                $widget["total_due"] = true;
            }
        }

        if ($show_estimate_info) {
            if (!in_array("purchase_orders", $hidden_menu)) {
                $widget["total_purchase_order"] = true;
            }
            if (!in_array("purchase_orders", $hidden_menu)) {
                $widget["purchase_order_value"] = true;
            }
            if (!in_array("purchase_orders", $hidden_menu)) {
                $widget["total_payments"] = true;
                $widget["total_due"] = true;
            }
        }

        if (!in_array("projects", $hidden_menu)) {
            $widget["open_projects_list"] = true;
        }

        if ($show_events && !in_array("events", $hidden_menu)) {
            $widget["events"] = true;
        }

        if ($show_invoice_info && !in_array("invoices", $hidden_menu)) {
            $widget["invoice_statistics"] = true;
        }

        if ($show_events && !in_array("events", $hidden_menu)) {
            $widget["events_today"] = true;
        }

        if (get_setting("module_todo")) {
            $widget["todo_list"] = true;
        }

        $widget["sticky_note"] = true;

        return $widget;
    }
    public function save_sticky_note() {
        $note_data = ["sticky_note" => $this->request->getPost("sticky_note")];
        $this->usersModel->update($this->loginUser->id, $note_data);
    }

    public function modal_form($id = 0) {
        $view_data['model_info'] = $this->dashboardsModel->find($id);
        return view("dashboards/custom_dashboards/modal_form", $view_data);
    }

    public function custom_widget_modal_form($id = 0) {
        $view_data['model_info'] = $this->customWidgetsModel->find($id);
        return view("dashboards/custom_widgets/modal_form", $view_data);
    }
    public function save_custom_widget() {
        $id = $this->request->getPost("id");

        if ($id) {
            $custom_widget_info = $this->_get_my_custom_widget($id);
            if (!$custom_widget_info) {
                return redirect()->to("forbidden");
            }
        }

        $data = [
            "user_id" => $this->loginUser->id,
            "title" => $this->request->getPost("title"),
            "content" => $this->request->getPost("content"),
            "show_title" => $this->request->getPost("show_title") ?? "",
            "show_border" => $this->request->getPost("show_border") ?? ""
        ];

        $save_id = $this->customWidgetsModel->save($data, $id);

        if ($save_id) {
            $custom_widgets_info = $this->customWidgetsModel->find($save_id);

            $custom_widgets_data = [
                $custom_widgets_info->id => $custom_widgets_info->title
            ];

            return $this->response->setJSON([
                "success" => true,
                "id" => $save_id,
                "custom_widgets_row" => $this->_make_widgets_row($custom_widgets_data),
                "custom_widgets_data" => $this->_widgets_row_data($custom_widgets_data),
                'message' => lang('record_saved')
            ]);
        } else {
            return $this->response->setJSON([
                "success" => false,
                'message' => lang('error_occurred')
            ]);
        }
    }

    public function show_my_dashboards()
    {
        $viewData["dashboards"] = $this->dashboardsModel->where('user_id', $this->session->get('user_id'))->findAll();
        return view('dashboards/list/dashboards_list', $viewData);
    }


    public function view($id = 0)
    {
        $id = $this->validateNumericValue($id);

        $selectedDashboardId = get_setting("user_" . $this->session->get('user_id') . "_dashboard");
        if (!$id) {
            $id = $selectedDashboardId;
        }

        $dashboardInfo = $this->_get_my_dashboard($id);

        if ($dashboardInfo) {
            $this->save_setting("user_" . $this->session->get('user_id') . "_dashboard", $dashboardInfo->id, "user");

            $viewData["dashboard_info"] = $dashboardInfo;
            $viewData["widget_columns"] = $this->make_dashboard(unserialize($dashboardInfo->data));

            $viewData["dashboards"] = $this->dashboardsModel->where('user_id', $this->session->get('user_id'))->findAll();
            $viewData["dashboard_type"] = "custom";

            return view("dashboards/custom_dashboards/view", $viewData);
        } else {
            return redirect()->to("dashboard");
        }
    }
    public function view_custom_widget()
    {
        $id = $this->request->getPost("id");

        $this->validateNumericValue($id);

        $widgetInfo = $this->customWidgetsModel->find($id);

        $viewData["model_info"] = $widgetInfo;

        return view("dashboards/custom_widgets/view", $viewData);
    }

    public function view_default_widget()
    {
        $widget = $this->request->getPost("widget");

        $viewData["widget"] = $this->_make_dashboard_widgets($widget);

        return view("dashboards/custom_dashboards/edit/view_default_widget", $viewData);
    }

    private function _get_my_dashboard($id = 0)
    {
        if ($id) {
            return $this->dashboardsModel->where(['user_id' => $this->session->get('user_id'), 'id' => $id])->first();
        }
    }

    private function _get_my_custom_widget($id = 0)
    {
        if ($id) {
            return $this->customWidgetsModel->where(['user_id' => $this->session->get('user_id'), 'id' => $id])->first();
        }
    }


    public function edit_dashboard($id = 0)
    {
        $id = $this->validateNumericValue($id);

        $dashboardInfo = $this->_get_my_dashboard($id);

        if (!$dashboardInfo) {
            return redirect()->to("forbidden");
        }

        $viewData["dashboard_info"] = $dashboardInfo;
        $viewData["widget_sortable_rows"] = $this->_make_editable_rows(unserialize($dashboardInfo->data));
        $viewData["widgets"] = $this->_make_widgets($dashboardInfo->id);

        return view("dashboards/custom_dashboards/edit/index", $viewData);
    }


    public function save()
    {
        $id = $this->request->getPost("id");

        if ($id) {
            $dashboardInfo = $this->_get_my_dashboard($id);
            if (!$dashboardInfo) {
                return redirect()->to("forbidden");
            }
        }

        $dashboardData = json_decode($this->request->getPost("data"));

        $data = [
            "user_id" => $this->session->get('user_id'),
            "title" => $this->request->getPost("title"),
            "data" => $dashboardData ? serialize($dashboardData) : serialize([]),
            "color" => $this->request->getPost("color")
        ];

        $saveId = $this->dashboardsModel->save($data, $id);

        if ($saveId) {
            return $this->response->setJSON(["success" => true, "dashboard_id" => $saveId, 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(["success" => false, 'message' => lang('error_occurred')]);
        }
    }

    public function delete()
    {
        $id = $this->request->getPost('id');

        $this->validate([
            "id" => "required|numeric"
        ]);

        if ($this->_get_my_dashboard($id) && $this->dashboardsModel->delete($id)) {
            return $this->response->setJSON(["success" => true, 'message' => lang('record_deleted')]);
        } else {
            return $this->response->setJSON(["success" => false, 'message' => lang('record_cannot_be_deleted')]);
        }
    }
    public function delete_custom_widgets()
    {
        $id = $this->request->getPost('id');

        $this->validate([
            "id" => "required|numeric"
        ]);

        if ($this->_get_my_custom_widget($id) && $this->customWidgetsModel->delete($id)) {
            return $this->response->setJSON(["success" => true, "id" => $id, 'message' => lang('record_deleted')]);
        } else {
            return $this->response->setJSON(["success" => false, 'message' => lang('record_cannot_be_deleted')]);
        }
    }
    private function _remove_widgets($widgets = [])
    {
        $widgetsPermission = $this->_check_widgets_permissions();

        foreach ($widgets as $widget) {
            if (!array_key_exists($widget, $widgetsPermission) && !is_numeric($widget)) {
                unset($widgets[array_search($widget, $widgets)]);
            }
        }

        return $widgets;
    }
    
    private function _get_default_widgets()
    {
        if ($this->loginUser->user_type == "staff") {
            $default_widgets_array = [
                "open_projects",
                "open_projects_list",
                "completed_projects",
                "starred_projects",
                "project_timeline",
                "my_open_tasks",
                "my_tasks_list",
                "all_tasks_kanban",
                "task_status",
                "clock_in_out",
                "members_clocked_in",
                "members_clocked_out",
                "all_team_members",
                "clocked_in_team_members",
                "clocked_out_team_members",
                "latest_online_team_members",
                "latest_online_client_contacts",
                "timesheet_statistics",
                "timecard_statistics",
                "total_invoices",
                "total_payments",
                "total_due",
                "invoice_statistics",
                "income_vs_expenses",
                "new_tickets",
                "open_tickets",
                "closed_tickets",
                "ticket_status",
                "events_today",
                "events",
                "sticky_note",
                "todo_list",
                "new_posts",
            ];
        } elseif ($this->loginUser->user_type == "client") {
            $default_widgets_array = [
                "total_projects",
                "open_projects_list",
                "total_invoices",
                "total_payments",
                "total_due",
                "invoice_statistics",
                "new_tickets",
                "open_tickets",
                "closed_tickets",
                "events_today",
                "events",
                "sticky_note",
                "todo_list",
            ];
        } elseif ($this->loginUser->user_type == "vendor") {
            $default_widgets_array = [
                "total_purchase_order",
                "open_projects_list",
                "purchase_order_value",
                "purchase_order_payments",
                "purchase_order_due",
                "invoice_statistics",
                "new_tickets",
                "open_tickets",
                "closed_tickets",
                "events_today",
                "events",
                "sticky_note",
                "todo_list",
            ];
        }

        return $default_widgets_array;
    }

    private function _make_widgets($dashboard_id = 0)
    {
        $default_widgets_array = $this->_get_default_widgets();
        $checked_widgets_array = $this->_remove_widgets($default_widgets_array);

        $widgets_array = array_fill_keys($checked_widgets_array, "default_widgets");

        // Custom widgets
        $custom_widgets = $this->customWidgetsModel->where('user_id', $this->loginUser->id)->findAll();
        if ($custom_widgets) {
            foreach ($custom_widgets as $custom_widget) {
                $widgets_array[$custom_widget->id] = $custom_widget->title;
            }
        }

        // Edit mode: remove already added widgets
        $dashboard_info = $this->dashboardsModel->find($dashboard_id);

        if ($dashboard_info) {
            foreach (unserialize($dashboard_info['data']) as $element) {
                $columns = $element['columns'] ?? null;
                if ($columns) {
                    foreach ($columns as $contents) {
                        foreach ($contents as $content) {
                            $widget = $content['widget'] ?? null;
                            if ($widget && array_key_exists($widget, $widgets_array)) {
                                unset($widgets_array[$widget]);
                            }
                        }
                    }
                }
            }
        }

        return $this->_make_widgets_row($widgets_array);
    }


    private function _make_widgets_row($widgets_array = [], $permissions_array = [])
    {
        $widgets = "";

        foreach ($widgets_array as $key => $value) {
            $error_class = "";
            if (count($permissions_array) && !is_numeric($key) && !array_key_exists($key, $permissions_array)) {
                $error_class = "error";
            }
            $widgets .= "<div data-value='{$key}' class='mb5 widget clearfix p10 bg-white {$error_class}'>" .
                    $this->_widgets_row_data([$key => $value])
                    . "</div>";
        }

        if ($widgets) {
            return $widgets;
        } else {
            return "<span class='text-off empty-area-text'>" . lang('no_more_widgets_available') . "</span>";
        }
    }
    private function _widgets_row_data($widget_array)
    {
        $key = key($widget_array);
        $value = $widget_array[$key];
        $details_button = "";
        if (is_numeric($key)) {
            $widgets_title = $value;
            $details_button = anchor(get_uri("dashboard/view_custom_widget"), "<i class='fa fa-ellipsis-h'></i>", ["class" => "text-off pr10 pl10", "title" => lang('custom_widget_details'), "data-post-id" => $key]);
        } else {
            $details_button = anchor(get_uri("dashboard/view_default_widget"), "<i class='fa fa-ellipsis-h'></i>", ["class" => "text-off pr10 pl10", "title" => lang($key), "data-post-widget" => $key]);
            $widgets_title = lang($key);
        }

        return "<span class='pull-left text-left'>{$widgets_title}</span>
                <span class='pull-right'>{$details_button}<i class='fa fa-arrows text-off'></i></span>";
    }
    private function _make_editable_rows($elements)
    {
        $view = "";
        $permissions_array = $this->_check_widgets_permissions();

        if ($elements) {
            foreach ($elements as $element) {
                $column_ratio = $element['ratio'] ?? null;
                $column_ratio_explode = explode("-", $column_ratio);

                $view .= "<row class='widget-row clearfix block bg-white' data-column-ratio='{$column_ratio}'>
                            <div class='pull-left row-controller text-off font-16'>
                                <i class='fa fa-bars move'></i>
                                <i class='fa fa-times delete delete-widget-row'></i>
                            </div>
                            <div class='pull-left clearfix row-container'>";

                $columns = $element['columns'] ?? null;

                if ($columns) {
                    foreach ($columns as $key => $value) {
                        $column_class_value = $this->_get_column_class_value($key, $columns, $column_ratio_explode);
                        $view .= "<div class='pr0 widget-column col-md-{$column_class_value} col-sm-{$column_class_value}'>
                                    <div id='add-column-panel-" . rand(500, 10000) . "' class='add-column-panel add-column-drop text-center p15'>";

                        foreach ($value as $content) {
                            $widget_value = $content['widget'] ?? null;
                            $view .= $this->_make_widgets_row([$widget_value => $content['title'] ?? null], $permissions_array);
                        }

                        $view .= "</div></div>";
                    }
                }
                $view .= "</div></row>";
            }
            return $view;
        }
    }
    private function make_dashboard($elements) {
        $view = "";
        if ($elements) {
            foreach ($elements as $element) {
                $view .= "<div class='dashboards-row clearfix row'>";

                $columns = get_array_value((array) $element, "columns");
                $column_ratio = explode("-", get_array_value((array) $element, "ratio"));

                if ($columns) {
                    foreach ($columns as $key => $value) {
                        $view .= "<div class='widget-container col-md-" . $this->_get_column_class_value($key, $columns, $column_ratio) . "'>";

                        foreach ($value as $content) {
                            $widget = get_array_value((array) $content, "widget");
                            if ($widget) {
                                $view .= $this->_make_dashboard_widgets($widget);
                            }
                        }
                        $view .= "</div>";
                    }
                }

                $view .= "</div>";
            }
            return $view;
        }
    }


    private function _make_dashboard_widgets($widget = "") {
        $widgets_array = $this->_check_widgets_permissions();

        // Custom widgets
        if (is_numeric($widget)) {
            $view_data["widget_info"] = $this->customWidgetsModel->find($widget);
            return view("dashboards/custom_dashboards/extra_data/custom_widget", $view_data);
        }

        $userType = $this->loginUser->user_type;

        switch ($userType) {
            case 'staff':
                return $this->_get_widgets_for_staffs($widget, $widgets_array);
            case 'client':
                return $this->_get_widgets_for_client($widget, $widgets_array);
            case 'vendor':
                return $this->_get_widgets_for_vendor($widget, $widgets_array);
            default:
                return '';
        }
    }
    private function _get_widgets_for_staffs($widget, $widgets_array) {
        if (get_array_value($widgets_array, $widget)) {
            switch ($widget) {
                case 'clock_in_out':
                    return clock_widget(true);
                case 'events_today':
                    return events_today_widget(true);
                case 'new_posts':
                    return new_posts_widget(true);
                case 'invoice_statistics':
                    return invoice_statistics_widget(true);
                case 'timesheet_statistics':
                    return project_timesheet_statistics_widget(true);
                case 'ticket_status':
                    return ticket_status_widget(true);
                case 'timecard_statistics':
                    return timecard_statistics_widget(true);
                case 'income_vs_expenses':
                    return income_vs_expenses_widget("h370", true);
                case 'events':
                    return events_widget(true);
                case 'my_open_tasks':
                    return my_open_tasks_widget(true);
                case 'project_timeline':
                    return view("dashboards/custom_dashboards/extra_data/widget_with_heading", array("icon" => "fa-clock-o", "widget" => $widget));
                case 'task_status':
                    return my_task_stataus_widget("h370", true);
                case 'sticky_note':
                    return sticky_note_widget("h370", true);
                case 'all_tasks_kanban':
                    return all_tasks_kanban_widget(true);
                case 'todo_list':
                    return todo_list_widget(true);
                case 'open_projects':
                    return open_projects_widget("", true);
                case 'completed_projects':
                    return completed_projects_widget("", true);
                case 'members_clocked_in':
                    return count_clock_in_widget(true);
                case 'members_clocked_out':
                    return count_clock_out_widget(true);
                case 'open_projects_list':
                    return my_open_projects_widget("", true);
                case 'starred_projects':
                    return my_starred_projects_widget("", true);
                case 'new_tickets':
                case 'open_tickets':
                case 'closed_tickets':
                    $this->init_permission_checker("ticket");
                    $explode_widget = explode("_", $widget);
                    return ticket_status_widget_small(array("status" => $explode_widget[0], "allowed_ticket_types" => $this->allowed_ticket_types), true);
                case 'all_team_members':
                    return all_team_members_widget(true);
                case 'clocked_in_team_members':
                    $this->init_permission_checker("attendance");
                    return clocked_in_team_members_widget(array("access_type" => $this->access_type, "allowed_members" => $this->allowed_members), true);
                case 'clocked_out_team_members':
                    $this->init_permission_checker("attendance");
                    return clocked_out_team_members_widget(array("access_type" => $this->access_type, "allowed_members" => $this->allowed_members), true);
                case 'latest_online_team_members':
                    return active_members_and_clients_widget("staff", true);
                case 'latest_online_client_contacts':
                    return active_members_and_clients_widget("client", true);
                case 'total_invoices':
                case 'total_payments':
                case 'total_due':
                    $explode_widget = explode("_", $widget);
                    return get_invoices_value_widget($explode_widget[1], true);
                case 'my_tasks_list':
                    return my_tasks_list_widget(true);
                default:
                    return invalid_access_widget(true);
            }
        } else {
            return invalid_access_widget(true);
        }
    }

    private function _get_widgets_for_client($widget, $widgets_array) {
        // Client's widgets
        $client_info = get_array_value($widgets_array, "client_info");
        $client_id = get_array_value($widgets_array, "client_id");

        if (get_array_value($widgets_array, $widget)) {
            switch ($widget) {
                case 'total_projects':
                    return view("clients/info_widgets/tab", array("tab" => "projects", "client_info" => $client_info));
                case 'total_invoices':
                    return view("clients/info_widgets/tab", array("tab" => "invoice_value", "client_info" => $client_info));
                case 'total_payments':
                    return view("clients/info_widgets/tab", array("tab" => "payments", "client_info" => $client_info));
                case 'total_due':
                    return view("clients/info_widgets/tab", array("tab" => "due", "client_info" => $client_info));
                case 'open_projects_list':
                    return my_open_projects_widget($client_id, true);
                case 'events':
                    return events_widget(true);
                case 'sticky_note':
                    return sticky_note_widget("h370", true);
                case 'invoice_statistics':
                    return invoice_statistics_widget(true);
                case 'events_today':
                    return events_today_widget(true);
                case 'todo_list':
                    return todo_list_widget(true);
                case 'new_tickets':
                case 'open_tickets':
                case 'closed_tickets':
                    $explode_widget = explode("_", $widget);
                    return ticket_status_widget_small(array("status" => $explode_widget[0]), true);
                default:
                    return invalid_access_widget(true);
            }
        } else {
            return invalid_access_widget(true);
        }
    }

    private function _get_widgets_for_vendor($widget, $widgets_array) {
        // Vendor's widgets
        $vendor_info = get_array_value($widgets_array, "vendor_info");
        $vendor_id = get_array_value($widgets_array, "vendor_id");

        if (get_array_value($widgets_array, $widget)) {
            switch ($widget) {
                case 'total_purchase_order':
                    return view("vendors/info_widgets/tab", array("tab" => "total_purchase_order", "vendor_info" => $vendor_info));
                case 'purchase_order_value':
                    return view("vendors/info_widgets/tab", array("tab" => "purchase_order_value", "vendor_info" => $vendor_info));
                case 'purchase_order_payments':
                    return view("vendors/info_widgets/tab", array("tab" => "purchase_order_payments", "vendor_info" => $vendor_info));
                case 'purchase_order_due':
                    return view("vendors/info_widgets/tab", array("tab" => "purchase_order_due", "vendor_info" => $vendor_info));
                case 'todo_list':
                    return todo_list_widget(true);
                default:
                    return invalid_access_widget(true);
            }
        } else {
            return invalid_access_widget(true);
        }
    }


    private function _get_column_class_value($key, $columns, $column_ratio) {
        $columns_array = array(1 => 12, 2 => 6, 3 => 4, 4 => 3);

        $column_count = count($columns);
        $column_ratio_count = count($column_ratio);

        $class_value = $column_ratio[$key];

        if ($column_count < $column_ratio_count) {
            $class_value = $columns_array[$column_count];
        }

        return $class_value;
    }

    public function save_dashboard_sort() {
        $rules = [
            'id' => 'required|numeric'
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON(['success' => false, 'message' => lang('Validation failed')]);
        }

        $id = $this->request->getPost('id');
        $data = [
            'sort' => $this->request->getPost('sort')
        ];

        if ($id) {
            $save_id = $this->dashboardsModel->update($id, $data);

            if ($save_id) {
                return $this->response->setJSON(['success' => true, 'message' => lang('record_saved')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
            }
        }
    }
}

/* End of file dashboard.php */
/* Location: ./application/controllers/dashboard.php */