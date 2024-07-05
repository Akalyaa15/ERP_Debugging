<?php

namespace App\Controllers;

use App\Models\DepartmentModel;
use App\Models\UsersModel;
use CodeIgniter\Controller;

class Department extends BaseController
{
    protected $departmentmodel;
    protected $usersmodel;

    public function __construct()
    {
        $this->departmentmodel = new DepartmentModel();
        $this->usersmodel = new UsersModel();
        $this->initPermissionChecker("department");
    }

    public function index()
    {
        $this->checkModuleAvailability("module_department");

        if ($this->login_user->is_admin == "1" || $this->login_user->user_type == "staff" || $this->login_user->user_type == "resource") {
            if ($this->access_type != "all" && !in_array($this->login_user->id, $this->allowed_members)) {
                return redirect()->to('forbidden');
            }
        }

        return view('department/index');
    }

    public function modal_form()
    {
        $this->validate([
            'id' => 'numeric'
        ]);

        $view_data['model_info'] = $this->departmentmodel->find($this->request->getPost('id'));
        return view('department/modal_form', $view_data);
    }

    public function save()
    {
        $this->validate([
            'id' => 'numeric',
            'title' => 'required',
            'department_code' => 'required'
        ]);

        $id = $this->request->getPost('id');
        $data = [
            "title" => $this->request->getPost('title'),
            "department_code" => $this->request->getPost('department_code'),
            "description" => $this->request->getPost('description'),
            "last_activity_user" => $this->login_user->id,
            "last_activity" => get_current_utc_time(),
        ];

        // Check for duplicates if updating
        if ($id) {
            $existing = $this->departmentmodel->find($id);
            if ($existing['department_code'] != $data['department_code'] && $this->departmentmodel->isDepartmentExists($data['department_code'])) {
                return json_encode(['success' => false, 'message' => lang('duplicate_department')]);
            }
            if (strtoupper($existing['title']) != strtoupper($data['title']) && $this->departmentmodel->isDepartmentNameExists($data['title'])) {
                return json_encode(['success' => false, 'message' => lang('duplicate_department_name')]);
            }
        } else {
            // Check for duplicates if creating new
            if ($this->departmentmodel->isDepartmentExists($data['department_code'])) {
                return json_encode(['success' => false, 'message' => lang('duplicate_department')]);
            }
            if ($this->departmentmodel->isDepartmentNameExists($data['title'])) {
                return json_encode(['success' => false, 'message' => lang('duplicate_department_name')]);
            }
        }

        $save_id = $this->departmentmodel->save($data, $id);
        if ($save_id) {
            return json_encode(['success' => true, 'data' => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
        } else {
            return json_encode(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function delete()
    {
        $this->validate([
            'id' => 'required|numeric'
        ]);

        $id = $this->request->getPost('id');
        $data = [
            "last_activity_user" => $this->login_user->id,
            "last_activity" => get_current_utc_time(),
        ];

        $save_id = $this->departmentmodel->save($data, $id);
        if ($this->request->getPost('undo')) {
            if ($this->departmentmodel->delete($id, true)) {
                return json_encode(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_undone')]);
            } else {
                return json_encode(['success' => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($this->departmentmodel->delete($id)) {
                return json_encode(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return json_encode(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }

    public function list_data()
    {
        $list_data = $this->departmentmodel->getDetails()->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        return json_encode(['data' => $result]);
    }

    private function _row_data($id)
    {
        $options = ['id' => $id];
        $data = $this->departmentmodel->getDetails($options)->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        // Implementing the logic for $last_activity_by_user_name and $last_activity_date

        return [
            $data->title,
            $data->department_code,
            $data->description,
            $last_activity_by_user_name,
            $last_activity_date,
            modal_anchor("department/modal_form", "<i class='fa fa-pencil'></i>", ['class' => 'edit', 'title' => lang('edit_department'), 'data-post-id' => $data->id])
                . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_tax'), 'class' => 'delete', 'data-id' => $data->id, 'data-action-url' => "department/delete", 'data-action' => 'delete-confirmation'])
        ];
    }
}

/* End of file Department.php */
/* Location: ./app/Controllers/Department.php */
