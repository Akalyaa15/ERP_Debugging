<?php

namespace App\Controllers;

use App\Models\DesignationModel;
use App\Models\DepartmentModel;
use App\Models\RolesModel;
use App\Models\UsersModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;

class Designation extends Controller
{
    use ResponseTrait;

    protected $designationmodel;
    protected $departmentmodel;
    protected $rolesmodel;
    protected $usersmodel;

    public function __construct()
    {
        $this->designationmodel = new DesignationModel();
        $this->departmentmodel = new DepartmentModel();
        $this->rolesmodel = new RolesModel();
        $this->usersmodel = new UsersModel();

        // Initialize permission checker or any other common functionality
        $this->initPermissionChecker("designation");
    }

    public function index()
    {
        $this->checkModuleAvailability("module_designation");

        if ($this->login_user->is_admin == "1" || $this->login_user->user_type == "staff" || $this->login_user->user_type == "resource") {
            if ($this->access_type != "all" && !in_array($this->login_user->id, $this->allowed_members)) {
                return redirect()->to('forbidden');
            }
        }

        return view('designation/index');
    }

    public function modal_form()
    {
        $this->validate([
            'id' => 'numeric'
        ]);

        $view_data = [
            'model_info' => $this->designationmodel->getOne($this->request->getPost('id')),
            'department_dropdown' => ["0" => "-"] + $this->departmentmodel->getDropdownList(["title"], "department_code", ["deleted" => '0'])
        ];

        return view('designation/modal_form', $view_data);
    }

    public function save()
    {
        $this->validate([
            'id' => 'numeric',
            'title' => 'required',
            'designation_code' => 'required'
        ]);

        $id = $this->request->getPost('id');

        // Check for duplicates if creating new
        if (!$id && $this->designationmodel->isDesignationExists($this->request->getPost('department_code'), $this->request->getPost("designation_code"))) {
            return $this->fail(lang('duplicate_designation'));
        }

        $data = [
            "title" => $this->request->getPost('title'),
            "designation_code" => $this->request->getPost('designation_code'),
            "department_code" => $this->request->getPost('department_code'),
            "description" => $this->request->getPost('description'),
            "last_activity_user" => $this->login_user->id,
            "last_activity" => get_current_utc_time(),
        ];

        $save_id = $this->designationmodel->save($data, $id);

        // Save associated role data
        $options = ["department_code" => $this->request->getPost('department_code')];
        $department_title = $this->departmentmodel->getDetails($options)->getRow()->title ?? '';

        $role_data = [
            "title" => $department_title . '-' . $this->request->getPost('title'),
        ];

        $save_role_data = $this->rolesmodel->save($role_data);

        if ($save_id) {
            return $this->respond(['success' => true, 'data' => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
        } else {
            return $this->fail(lang('error_occurred'));
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

        $save_id = $this->designationmodel->save($data, $id);

        if ($this->request->getPost('undo')) {
            if ($this->designationmodel->delete($id, true)) {
                return $this->respond(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_undone')]);
            } else {
                return $this->fail(lang('error_occurred'));
            }
        } else {
            if ($this->designationmodel->delete($id)) {
                return $this->respond(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->fail(lang('record_cannot_be_deleted'));
            }
        }
    }

    public function list_data()
    {
        $list_data = $this->designationmodel->getDetails()->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        return $this->respond(['data' => $result]);
    }

    private function _row_data($id)
    {
        $options = ['id' => $id];
        $data = $this->designationmodel->getDetails($options)->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        // Implementing the logic for $last_activity_by_user_name and $last_activity_date

        return [
            $data->title,
            $data->designation_code,
            $data->department_title,
            $data->description,
            $last_activity_by_user_name,
            $last_activity_date,
            modal_anchor("designation/modal_form", "<i class='fa fa-pencil'></i>", ['class' => 'edit', 'title' => lang('edit_designation'), 'data-post-id' => $data->id])
                . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_tax'), 'class' => 'delete', 'data-id' => $data->id, 'data-action-url' => "designation/delete", 'data-action' => 'delete-confirmation'])
        ];
    }

    public function get_designation()
    {
        $item = $this->designationmodel->getDesignationDetails($this->request->getPost("dep_code"));
        $suggestions = [];
        foreach ($item as $items) {
            $suggestions[] = ["id" => $items->designation_code, "text" => $items->title /*.'['.$items->title.']'*/];
        }
        return $this->respond($suggestions);
    }
}

/* End of file Designation.php */
/* Location: ./app/Controllers/Designation.php */
