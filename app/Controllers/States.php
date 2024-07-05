<?php

namespace App\Controllers;

use App\Models\StatesModel;
use App\Models\CountriesModel;
use App\Models\UsersModel;
use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;

class States extends Controller
{
    use ResponseTrait;

    protected $statesModel;
    protected $countriesModel;
    protected $usersModel;
    protected $session;

    public function __construct()
    {
        $this->statesModel = new StatesModel(); // Replace with your StatesModel class
        $this->countriesModel = new CountriesModel(); // Replace with your CountriesModel class
        $this->usersModel = new UsersModel(); // Replace with your UsersModel class

        // Load session service
        $this->session = \Config\Services::session();
    }

    public function index()
    {
        $this->check_module_availability("module_state");

        if ($this->session->login_user->is_admin == "1") {
            return view('states/index'); // Assuming you load views directly in CI4 controllers
        } elseif ($this->session->login_user->user_type == "staff" || $this->session->login_user->user_type == "resource") {
            $this->access_only_allowed_members();
            return view('states/index');
        } else {
            return view('states/index');
        }
    }
  public function modal_form()
    {
        $this->validate([
            'id' => 'numeric'
        ]);

        $viewData['model_info'] = $this->statesModel->find($this->request->getPost('id'));
        $viewData['country_dropdown'] = $this->_get_countries_dropdown();

        return view('states/modal_form', $viewData);
    }

    private function _get_countries_dropdown()
    {
        $country_dropdown = [];
        $countries = $this->countriesModel->findAll();

        foreach ($countries as $country) {
            $country_dropdown[$country->numberCode] = $country->countryName;
        }

        return $country_dropdown;
    }

    public function save()
    {
        $this->validate([
            'id' => 'numeric'
        ]);

        $id = $this->request->getPost('id');
        $data = [
            'title' => $this->request->getPost('title'),
            'country_code' => $this->request->getPost('country_code'),
            'state_code' => $this->request->getPost('state_code'),
            'last_activity_user' => $this->session->login_user->id,
            'last_activity' => date('Y-m-d H:i:s')
        ];

        // Check for duplicates
        if (!$id) {
            if ($this->statesModel->is_state_exists($this->request->getPost('state_code'))) {
                return $this->fail(lang('duplicate_state'));
            }
            if ($this->statesModel->is_state_name_exists($this->request->getPost('title'))) {
                return $this->fail(lang('duplicate_state_name'));
            }
        } else {
            $existingState = $this->statesModel->find($id);
            if ($existingState->state_code != $this->request->getPost('state_code') && $this->statesModel->is_state_exists($this->request->getPost('state_code'))) {
                return $this->fail(lang('duplicate_state'));
            }
            if (strtoupper($existingState->title) != strtoupper($this->request->getPost('title')) && $this->statesModel->is_state_name_exists($this->request->getPost('title'))) {
                return $this->fail(lang('duplicate_state_name'));
            }
        }

        // Save data
        $save_id = $this->statesModel->save($data, $id);

        if ($save_id) {
            return $this->respond(["success" => true, "data" => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

    public function delete()
    {
        $this->validate([
            'id' => 'numeric|required'
        ]);

        $id = $this->request->getPost('id');
        $data = [
            "last_activity_user" => $this->session->login_user->id,
            "last_activity" => date('Y-m-d H:i:s')
        ];

        if ($this->request->getPost('undo')) {
            if ($this->statesModel->delete($id, true)) {
                return $this->respond(["success" => true, "data" => $this->_row_data($id), "message" => lang('record_undone')]);
            } else {
                return $this->fail(lang('error_occurred'));
            }
        } else {
            if ($this->statesModel->delete($id)) {
                return $this->respond(["success" => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->fail(lang('record_cannot_be_deleted'));
            }
        }
    }

    public function list_data()
    {
        $list_data = $this->statesModel->findAll();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        return $this->respond(["data" => $result]);
    }
    
    private function _row_data($id)
    {
        $data = $this->statesModel->find($id);
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        // Adjust as per your view needs in CI4
        return [
            $data->title,
            $data->countryName,
            $data->state_code,
            // Add other fields as needed
        ];
    }
}
