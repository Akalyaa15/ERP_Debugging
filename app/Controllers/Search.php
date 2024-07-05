<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class Search extends BaseController {
    protected $tasksmodel;
    protected $projectsmodel;
    protected $clientsmodel;
    protected $vendorsmodel;
    protected $companysmodel;
    protected $todomodel;

    public function __construct() {
        parent::__construct();
        $this->access_only_team_members();
    }

    public function index() {
        // Add any necessary logic for index method in CodeIgniter 4
    }

    private function can_access_clients() {
        $permissions = $this->login_user->permissions;
        if ($this->login_user->is_admin || get_array_value($permissions, "client")) {
            return true;
        }
    }

    private function can_access_vendors() {
        $permissions = $this->login_user->permissions;
        if ($this->login_user->is_admin || get_array_value($permissions, "vendor")) {
            return true;
        }
    }

    private function can_access_company() {
        $access_company = $this->get_access_info("company");
        if ($this->login_user->is_admin || ($access_company->access_type == "all" || in_array($this->login_user->id, $access_company->allowed_members))) {
            return true;
        }
    }

    private function can_manage_all_projects() {
        if ($this->login_user->is_admin || get_array_value($this->login_user->permissions, "can_manage_all_projects") == "1") {
            return true;
        }
    }

    public function search_modal_form() {
        $search_fields = [
            "task",
            "project",
        ];

        if ($this->can_access_clients()) {
            $search_fields[] = "client";
        }

        if ($this->can_access_vendors()) {
            $search_fields[] = "vendor";
        }

        if ($this->can_access_company()) {
            $search_fields[] = "company";
        }

        if (get_setting("module_todo")) {
            $search_fields[] = "todo";
        }

        $search_fields_dropdown = [];
        foreach ($search_fields as $search_field) {
            $search_fields_dropdown[] = ["id" => $search_field, "text" => lang($search_field)];
        }

        $view_data['search_fields_dropdown'] = json_encode($search_fields_dropdown);

        return view("search/modal_form", $view_data);
    }

    public function get_search_suggestion() {
        $search = $this->request->getPost("search");
        $search_field = $this->request->getPost("search_field");

        if ($search && $search_field) {
            $options = [];
            $result = [];

            switch ($search_field) {
                case "task":
                    $options["show_assigned_tasks_only_user_id"] = $this->login_user->id;
                    $result = $this->tasksmodel->get_search_suggestion($search, $options)->getResult();
                    break;

                case "project":
                    if (!$this->can_manage_all_projects()) {
                        $options["user_id"] = $this->login_user->id;
                    }
                    $result = $this->projectsmodel->get_search_suggestion($search, $options)->getResult();
                    break;

                case "client":
                    if (!$this->can_access_clients()) {
                        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
                    }
                    $result = $this->clientsmodel->get_search_suggestion($search, $options)->getResult();
                    break;

                case "vendor":
                    if (!$this->can_access_vendors()) {
                        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
                    }
                    $result = $this->vendorsmodel->get_search_suggestion($search, $options)->getResult();
                    break;

                case "company":
                    if (!$this->can_access_company()) {
                        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
                    }
                    $result = $this->companysmodel->get_search_suggestion($search, $options)->getResult();
                    break;

                case "todo":
                    if (get_setting("module_todo")) {
                        $result = $this->todomodel->get_search_suggestion($search, $this->login_user->id)->getResult();
                    }
                    break;

                default:
                    // Handle default case if needed
                    break;
            }

            $result_array = [];
            foreach ($result as $value) {
                switch ($search_field) {
                    case "task":
                        $result_array[] = ["value" => $value->id, "label" => lang("task") . " $value->id: " . $value->title];
                        break;

                    case "company":
                        $result_array[] = ["value" => $value->cr_id, "label" => $value->title];
                        break;

                    default:
                        $result_array[] = ["value" => $value->id, "label" => $value->title];
                        break;
                }
            }

            return $this->response->setJSON($result_array);
        }
    }
}
