<?php

namespace App\Controllers;

use App\Models\UsersModel; 
class T_ot_handler extends BaseController
{
    protected $usersModel;

    public function __construct()
    {
        $this->usersModel = new UsersModel(); // Instantiate your UsersModel
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
        // Load language helper if not already loaded
        helper(['language']);

        // $this->check_module_availability("module_attendance"); // Uncomment or integrate as needed

        $viewData = [
            'team_members_dropdown' => json_encode($this->_get_members_dropdown_list_for_filter()),
            'team_members_dropdowns' => json_encode($this->_get_rm_members_dropdown_list_for_filter())
        ];

        // $this->load->view("ot_handler/index", $view_data); // CI3 view loading style
        return view('project_ot_handler/index', $viewData); // CI4 view loading style
    }
}

