<?php

defined('BASEPATH') or exit('No direct script access allowed');

use App\Models\UsersModel;

class T_ot_handler extends CI_Controller
{
    protected $usersModel;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('UsersModel'); // Load your UsersModel
        $this->usersModel = new UsersModel();
    }

    private function _get_members_dropdown_list_for_filter()
    {
        // Replace with your allowed members logic
        $allowed_members = []; // Example: $allowed_members = [1, 2, 3];

        // Prepare the dropdown list of staff members
        $where = [
            'user_type' => 'staff',
            'where_in' => ['id' => $allowed_members]
        ];

        $members = $this->usersModel->getDropdownList(['first_name', 'last_name'], 'id', $where);

        $members_dropdown = [['id' => '', 'text' => '- ' . lang('member') . ' -']];
        foreach ($members as $id => $name) {
            $members_dropdown[] = ['id' => $id, 'text' => $name];
        }
        return $members_dropdown;
    }

    private function _get_rm_members_dropdown_list_for_filter()
    {
        // Replace with your allowed members logic
        $allowed_members = []; // Example: $allowed_members = [1, 2, 3];

        // Prepare the dropdown list of resource members
        $where = [
            'user_type' => 'resource',
            'where_in' => ['id' => $allowed_members]
        ];

        $members = $this->usersModel->getDropdownList(['first_name', 'last_name'], 'id', $where);

        $members_dropdowns = [['id' => '', 'text' => '- ' . lang('outsource_member') . ' -']];
        foreach ($members as $id => $name) {
            $members_dropdowns[] = ['id' => $id, 'text' => $name];
        }
        return $members_dropdowns;
    }

    public function index()
    {
        $this->load->helper('language');

        $viewData = [
            'team_members_dropdown' => json_encode($this->_get_members_dropdown_list_for_filter()),
            'team_members_dropdowns' => json_encode($this->_get_rm_members_dropdown_list_for_filter())
        ];

        // $this->check_module_availability("module_attendance"); // Uncomment or integrate as needed

        $this->load->view('project_ot_handler/index', $viewData);
    }
}
