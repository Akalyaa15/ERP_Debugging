<?php

namespace App\Controllers;

use App\Models\UsersModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\Exceptions\HTTPException;

class T_ot_handler extends Controller
{
    protected $usersModel;

    public function __construct()
    {
        $this->usersModel = new UsersModel(); // Initialize UsersModel
    }

    private function _getMembersDropdownListForFilter()
    {
        // Replace with your allowed members logic
        $allowedMembers = []; // Example: $allowedMembers = [1, 2, 3];

        // Prepare the dropdown list of staff members
        $where = [
            'user_type' => 'staff',
            'whereIn' => ['id' => $allowedMembers]
        ];

        $members = $this->usersModel->getDropdownList(['first_name', 'last_name'], 'id', $where);

        $membersDropdown = [['id' => '', 'text' => '- ' . lang('member') . ' -']];
        foreach ($members as $id => $name) {
            $membersDropdown[] = ['id' => $id, 'text' => $name];
        }
        return $membersDropdown;
    }

    private function _getRMMembersDropdownListForFilter()
    {
        // Replace with your allowed members logic
        $allowedMembers = []; // Example: $allowedMembers = [1, 2, 3];

        // Prepare the dropdown list of resource members
        $where = [
            'user_type' => 'resource',
            'whereIn' => ['id' => $allowedMembers]
        ];

        $members = $this->usersModel->getDropdownList(['first_name', 'last_name'], 'id', $where);

        $membersDropdowns = [['id' => '', 'text' => '- ' . lang('outsource_member') . ' -']];
        foreach ($members as $id => $name) {
            $membersDropdowns[] = ['id' => $id, 'text' => $name];
        }
        return $membersDropdowns;
    }

    public function index()
    {
        helper('language');

        $viewData = [
            'team_members_dropdown' => json_encode($this->_getMembersDropdownListForFilter()),
            'team_members_dropdowns' => json_encode($this->_getRMMembersDropdownListForFilter())
        ];

        // Uncomment or integrate module availability check as needed
        // $this->checkModuleAvailability("module_attendance");

        return view('project_ot_handler/index', $viewData);
    }

 }