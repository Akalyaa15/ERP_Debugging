<?php

namespace App\Controllers;

use App\Models\TeamModel;
use App\Models\UsersModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;

class Team extends Controller
{
    use ResponseTrait;

    protected $usersModel;
    protected $teamModel;

    public function __construct()
    {
        $this->usersModel = new UsersModel();
        $this->teamModel = new TeamModel();
    }

    public function index()
    {
        return view('team/index');
    }
   public function modal_form()
    {
        helper(['form', 'url']);

        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'numeric'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->fail($validation->getErrors());
        }

        $teamMembers = $this->usersModel->where(['deleted' => 0])->where('user_type !=', 'client')->findAll();
        $membersDropdown = [];

        foreach ($teamMembers as $teamMember) {
            $membersDropdown[] = ['id' => $teamMember['id'], 'text' => $teamMember['first_name'] . ' ' . $teamMember['last_name']];
        }

        $data = [
            'members_dropdown' => json_encode($membersDropdown),
            'model_info' => $this->teamModel->find($this->request->getPost('id'))
        ];

        return view('team/modal_form', $data);
    }

    public function save()
    {
        helper('form');

        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'numeric',
            'title' => 'required',
            'members' => 'required'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->fail($validation->getErrors());
        }

        $id = $this->request->getPost('id');
        $data = [
            'title' => $this->request->getPost('title'),
            'members' => $this->request->getPost('members')
        ];

        $saveId = $this->teamModel->save($data, $id);
        if ($saveId) {
            return $this->respondCreated(['success' => true, 'data' => $saveId, 'message' => lang('record_saved')]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

    public function delete()
    {
        helper('form');

        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'required|numeric'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->fail($validation->getErrors());
        }

        $id = $this->request->getPost('id');
        $undo = $this->request->getPost('undo');

        if ($undo) {
            if ($this->teamModel->delete($id, true)) {
                return $this->respond(['success' => true, 'data' => $id, 'message' => lang('record_undone')]);
            } else {
                return $this->fail(lang('error_occurred'));
            }
        } else {
            if ($this->teamModel->delete($id)) {
                return $this->respond(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->fail(lang('record_cannot_be_deleted'));
            }
        }
    }

    public function list_data()
    {
        $listData = $this->teamModel->findAll();
        $result = [];

        foreach ($listData as $data) {
            $result[] = $this->_make_row($data);
        }

        return $this->respond(['data' => $result]);
    }

    private function _make_row($data)
    {
        $totalMembers = '<span class="label label-light w100"><i class="fa fa-users"></i> ' . count(explode(",", $data['members'])) . '</span>';

        return [
            $data['title'],
            '<a href="' . base_url('team/members_list') . '" data-post-members="' . $data['members'] . '">' . $totalMembers . '</a>',
            '<a href="' . base_url('team/modal_form') . '" class="edit" data-post-id="' . $data['id'] . '"><i class="fa fa-pencil"></i></a>' .
            '<a href="#" class="delete" data-id="' . $data['id'] . '" data-action-url="' . base_url('team/delete') . '" data-action="delete-confirmation"><i class="fa fa-times fa-fw"></i></a>'
        ];
    }

    public function members_list()
    {
        $teamMembers = $this->usersModel->where(['id' => $this->request->getPost('members')])->findAll();
        $data = [
            'team_members' => $teamMembers
        ];

        return view('team/members_list', $data);
    }
}
