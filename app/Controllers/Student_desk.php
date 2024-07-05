<?php

namespace App\Controllers;

use App\Models\VapCategoryModel;
use App\Models\StudentDeskModel;
use App\Models\CountriesModel;
use App\Models\StatesModel;
class StudentDesk extends BaseController {
    protected $vapCategoryModel;
    protected $studentDeskModel;
    protected $countriesModel;
    protected $statesModel;

    public function __construct() {
        $this->vapCategoryModel = new VapCategoryModel();
        $this->studentDeskModel = new StudentDeskModel();
        $this->countriesModel = new CountriesModel();
        $this->statesModel = new StatesModel();
    }

    public function index() {
        $this->check_module_availability("module_student_desk");

        if ($this->login_user->is_admin == "1" || ($this->login_user->user_type == "staff" && ($this->access_type == "all" || in_array($this->login_user->id, $this->allowed_members)))) {
            return view("student_desk/index");
        } else {
            return redirect()->to("forbidden");
        }
    }

    public function yearly() {
        return view("student_desk/yearly_student_desk");
    }

    public function custom() {
        return view("student_desk/custom_student_desk");
    }

    public function modal_form() {
        $id = $this->request->getPost('id');
        $data['model_info'] = $this->studentDeskModel->find($id);

        $country = $this->countriesModel->find($data['model_info']->country);
        $stateCategories = $this->statesModel->where('country_code', $country->numberCode)->findAll();

        $stateCategoriesSuggestion = [["id" => "", "text" => "-"]];
        foreach ($stateCategories as $state) {
            $stateCategoriesSuggestion[] = ["id" => $state->id, "text" => $state->title];
        }

        $data['state_dropdown'] = $stateCategoriesSuggestion;
        $data['vap_category_dropdown'] = $this->vapCategoryModel->where('status', 'active')->findAll();
        $data['time_format_24_hours'] = get_setting("time_format") == "24_hours" ? true : false;

        return view('student_desk/modal_form', $data);
    }

    private function _get_state_dropdown_select2_data() {
        $states = $this->statesModel->findAll();
        $stateDropdown = [];

        foreach ($states as $state) {
            $stateDropdown[] = ["id" => $state->id, "text" => $state->title];
        }
        return $stateDropdown;
    }

    public function save() {
        $id = $this->request->getPost('id');

        $data = [
            "name" => $this->request->getPost('name'),
            "college_name" => $this->request->getPost('college_name'),
            "department" => $this->request->getPost('department'),
            "date" => $this->request->getPost('date'),
            "phone" => $this->request->getPost('phone'),
            "communication_address" => $this->request->getPost('communication_address'),
            "pincode" => $this->request->getPost('pincode'),
            "state" => $this->request->getPost('state'),
            "district" => $this->request->getPost('district'),
            "alternative_phone" => $this->request->getPost('alternative_phone'),
            "gender" => $this->request->getPost('gender'),
            "email" => $this->request->getPost('email'),
            "dob" => $this->request->getPost('dob'),
            "year" => $this->request->getPost('year'),
            "vap_category" => $this->request->getPost('vap_category'),
            "program_title" => $this->request->getPost('program_title'),
            "start_date" => $this->request->getPost('start_date'),
            "end_date" => $this->request->getPost('end_date'),
            "start_time" => $this->convert_time_to_24hours_format($this->request->getPost('start_time')),
            "end_time" => $this->convert_time_to_24hours_format($this->request->getPost('end_time')),
            "parent_name" => $this->request->getPost('parent_name'),
            "permanent_address" => $this->request->getPost('permanent_address'),
            "last_name" => $this->request->getPost('last_name'),
            "aadhar_no" => $this->request->getPost('aadhar_no'),
            "country" => $this->request->getPost('country'),
            "same_address" => $this->request->getPost('same_address'),
            "state_mandatory" => $this->request->getPost('state_mandatory')
        ];

        if ($id && $this->studentDeskModel->is_student_desk_email_exists($data["email"], $id)) {
            return $this->response->setJSON(["success" => false, 'message' => lang('duplicate_email')]);
        }

        $saveId = $this->studentDeskModel->save($data, $id);
        if ($saveId) {
            return $this->response->setJSON(["success" => true, "data" => $this->_row_data($saveId), 'id' => $saveId, 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(["success" => false, 'message' => lang('error_occurred')]);
        }
    }

    public function delete() {
        $id = $this->request->getPost('id');
        $undo = $this->request->getPost('undo');

        if ($undo && $this->studentDeskModel->delete($id, true)) {
            return $this->response->setJSON(["success" => true, "data" => $this->_row_data($id), "message" => lang('record_undone')]);
        } elseif ($this->studentDeskModel->delete($id)) {
            return $this->response->setJSON(["success" => true, 'message' => lang('record_deleted')]);
        } else {
            return $this->response->setJSON(["success" => false, 'message' => lang('record_cannot_be_deleted')]);
        }
    }

    public function list_data() {
        $options = [
            "start_date" => $this->request->getPost('start_date'),
            "end_date" => $this->request->getPost('end_date')
        ];

        $listData = $this->studentDeskModel->get_details($options)->getResult();
        $result = [];
        foreach ($listData as $data) {
            $result[] = $this->_make_row($data);
        }
        return $this->response->setJSON(["data" => $result]);
    }

    private function _row_data($id) {
        $options = ["id" => $id];
        $data = $this->studentDeskModel->get_details($options)->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data) {
        $timeFormat24Hours = get_setting("time_format") == "24_hours" ? true : false;

        $startTime = $data->start_time ? date("H:i", strtotime($data->start_time)) : "";
        $endTime = $data->end_time ? date("H:i", strtotime($data->end_time)) : "";

        if (!$timeFormat24Hours) {
            $startTime = convert_time_to_12hours_format(date("H:i:s", strtotime($data->start_time)));
            $endTime = convert_time_to_12hours_format(date("H:i:s", strtotime($data->end_time)));
        }

        $durationOfCourse = $data->start_date . '</br>' . "To" . '</br>' . $data->end_date;
        $timing = $startTime . '</br>' . "To" . '</br>' . $endTime;

        return [
            anchor(get_uri("student_desk/view/" . $data->id), $data->name . " " . $data->last_name),
            $data->date,
            nl2br($data->college_name),
            $data->department,
            $data->vap_category_title,
            $data->program_title,
            $durationOfCourse,
            $timing,
            $data->phone,
            $data->email,
            modal_anchor(get_uri("student_desk/modal_form"), "<i class='fa fa-pencil'></i>", ["class" => "edit", "title" => lang('edit_student_desk'), "data-post-id" => $data->id])
            . js_anchor("<i class='fa fa-times fa-fw'></i>", ["title" => lang('delete_student_desk'), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("student_desk/delete"), "data-action" => "delete-confirmation"])
        ];
    }

    public function view($studentDeskId = 0, $tab = "") {
        if ($studentDeskId) {
            $options = ["id" => $studentDeskId];
            $studentDeskInfo = $this->studentDeskModel->get_details($options)->getRow();

            if ($studentDeskInfo) {
                $data = [
                    'student_desk_info' => $studentDeskInfo,
                    'tab' => $tab
                ];

                return view("student_desk/view", $data);
            } else {
                show_404();
            }
        }
    }
}