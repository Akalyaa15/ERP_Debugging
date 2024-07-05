<?php

namespace App\Controllers;

use App\Models\OutsourceJobsModel;
use App\Models\TaxesModel;
use App\Models\ClientsModel;
use App\Models\ProjectsModel;
use App\Models\UnitTypeModel;
use App\Models\HsnSacCodeModel;
use App\Models\UsersModel;

class Outsource_jobs extends BaseController {
    protected $outsourceJobsModel;
    protected $taxesModel;
    protected $clientsModel;
    protected $projectsModel;
    protected $unitTypeModel;
    protected $hsnSacCodeModel;
    protected $usersModel;

    public function __construct() {
        $this->outsourceJobsModel = new OutsourceJobsModel();
        $this->taxesModel = new TaxesModel();
        $this->clientsModel = new ClientsModel();
        $this->projectsModel = new ProjectsModel();
        $this->unitTypeModel = new UnitTypeModel();
        $this->hsnSacCodeModel = new HsnSacCodeModel();
        $this->usersModel = new UsersModel();

        $this->access_only_team_members();
    }

    protected function validate_access_to_items() {
        $access_invoice = $this->get_access_info("work_order");

        if (get_setting("module_work_order") != "1") {
            return redirect()->to('forbidden');
        }
        
        if ($this->session->get('is_admin')) {
            return true;
        } else if ($access_invoice->access_type === "all") {
            return true;
        } else {
            return redirect()->to('forbidden');
        }
    }

    public function index() {
        $this->validate_access_to_items();
        return view('outsource_jobs/index');
    }

    public function modal_form() {
        $this->validate_access_to_items();

        $this->validate([
            'id' => 'numeric'
        ]);

        $view_data['model_info'] = $this->outsourceJobsModel->find($this->request->getPost('id'));
        $view_data["unit_type_dropdown"] = $this->_get_unit_type_dropdown_select2_data();
        $view_data['clients_dropdown'] = array_merge(["" => "-"], $this->clientsModel->get_dropdown_list(['company_name']));
        $projects = $this->projectsModel->get_dropdown_list(['title'], 'id', ['client_id' => $view_data['model_info']->client_id]);

        $suggestion = [["id" => "", "text" => "-"]];
        foreach ($projects as $key => $value) {
            $suggestion[] = ["id" => $key, "text" => $value];
        }
        $view_data['projects_suggestion'] = $suggestion;
        return view('outsource_jobs/modal_form', $view_data);
    }

    private function _get_unit_type_dropdown_select2_data() {
        $unit_types = $this->unitTypeModel->where(['deleted' => 0, 'status' => 'active'])->findAll();
        $unit_type_dropdown = [];

        foreach ($unit_types as $code) {
            $unit_type_dropdown[] = ["id" => $code->title, "text" => $code->title];
        }
        return $unit_type_dropdown;
    }

    public function save() {
        $this->validate_access_to_items();

        $this->validate([
            'id' => 'numeric'
        ]);

        $id = $this->request->getPost('id');
        $client_id = $this->request->getPost('client_id');

        $item_data = [
            "title" => $this->request->getPost('title'),
            "category" => $this->request->getPost('category'),
            "description" => $this->request->getPost('description'),
            "hsn_description" => $this->request->getPost('hsn_description'),
            "unit_type" => $this->request->getPost('unit_type'),
            "hsn_code" => $this->request->getPost('hsn_code'),
            "gst" => $this->request->getPost('gst'),
            "rate" => unformat_currency($this->request->getPost('item_rate')),
            "last_activity_user" => $this->session->get('user_id'),
            "last_activity" => date('Y-m-d H:i:s'),
            "client_id" => $client_id,
            "project_id" => $this->request->getPost('project_id') ? $this->request->getPost('project_id') : 0,
        ];

        if (!$id && $this->outsourceJobsModel->is_outsource_job_exists($item_data["title"])) {
            return $this->response->setJSON(['success' => false, 'message' => lang('job_id_already')]);
        }

        if ($id && $this->outsourceJobsModel->is_outsource_job_exists($item_data["title"], $id)) {
            return $this->response->setJSON(['success' => false, 'message' => lang('job_id_already')]);
        }

        $item_id = $this->outsourceJobsModel->save($item_data, $id);
        if ($item_id) {
            if ($this->request->getPost('add_new_item_to_library')) {
                $library_item_data = [
                    "hsn_code" => $this->request->getPost('hsn_code'),
                    "gst" => $this->request->getPost('gst'),
                    "hsn_description" => $this->request->getPost('hsn_description')
                ];
                $this->hsnSacCodeModel->save($library_item_data);
            }

            $item_info = $this->outsourceJobsModel->find($item_id);
            return $this->response->setJSON(['success' => true, "id" => $item_info->id, "data" => $this->_make_item_row($item_info), 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function delete() {
        $this->validate_access_to_items();

        $this->validate([
            'id' => 'required|numeric'
        ]);

        $id = $this->request->getPost('id');
        $data = [
            "last_activity_user" => $this->session->get('user_id'),
            "last_activity" => date('Y-m-d H:i:s'),
        ];
        $save_id = $this->outsourceJobsModel->save($data, $id);

        if ($this->request->getPost('undo')) {
            if ($this->outsourceJobsModel->delete($id, true)) {
                $item_info = $this->outsourceJobsModel->find($id);
                return $this->response->setJSON(['success' => true, "id" => $item_info->id, "data" => $this->_make_item_row($item_info), "message" => lang('record_undone')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($this->outsourceJobsModel->delete($id)) {
                $item_info = $this->outsourceJobsModel->find($id);
                return $this->response->setJSON(['success' => true, "id" => $item_info->id, 'message' => lang('record_deleted')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }

    public function list_data() {
        $this->validate_access_to_items();

        $list_data = $this->outsourceJobsModel->findAll();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_item_row($data);
        }
        return $this->response->setJSON(['data' => $result]);
    }

    private function _make_item_row($data) {
        $type = $data->unit_type ? $data->unit_type : "";

        $client_info = $this->clientsModel->find($data->client_id);
        $project_info = $this->projectsModel->find($data->project_id);

        $last_activity_by_user_name = "-";
        if ($data->last_activity_user) {
            $last_activity_user_data = $this->usersModel->find($data->last_activity_user);
            $last_activity_image_url = get_avatar($last_activity_user_data->image);
            $last_activity_user = "<span class='avatar avatar-xs mr10'><img src='$last_activity_image_url' alt='...'></span> $last_activity_user_data->first_name $last_activity_user_data->last_name";

            if ($last_activity_user_data->user_type == "resource") {
                $last_activity_by_user_name = get_rm_member_profile_link($data->last_activity_user, $last_activity_user);
            } elseif ($last_activity_user_data->user_type == "client") {
                $last_activity_by_user_name = get_client_contact_profile_link($data->last_activity_user, $last_activity_user);
            } elseif ($last_activity_user_data->user_type == "staff") {
                $last_activity_by_user_name = get_team_member_profile_link($data->last_activity_user, $last_activity_user);
            } elseif ($last_activity_user_data->user_type == "vendor") {
                $last_activity_by_user_name = get_vendor_contact_profile_link($data->last_activity_user, $last_activity_user);
            }
        }

        $last_activity_date = "-";
        if ($data->last_activity) {
            $last_activity_date = format_to_relative_time($data->last_activity);
        }

        return [
            $data->title,
            $client_info->company_name ? anchor('clients/view/' . $data->client_id, $client_info->company_name) : "-",
            $project_info->title ? anchor('projects/view/' . $data->project_id, $project_info->title) : "-",
            nl2br($data->description),
            $data->category,
            $data->hsn_code,
            $type,
            $data->gst . "%",
            $data->rate,
            $last_activity_by_user_name,
            $last_activity_date,
            modal_anchor('outsource_jobs/modal_form', "<i class='fa fa-pencil'></i>", ["class" => "edit", "title" => lang('edit_item'), "data-post-id" => $data->id])
            . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete'), "class" => "delete", "data-id" => $data->id, "data-action-url" => 'outsource_jobs/delete', "data-action" => "delete-confirmation"])
        ];
    }

    public function get_invoice_item_suggestion() {
        $key = $this->request->getGet("q");
        $suggestion = [];

        $items = $this->hsnSacCodeModel->get_item_suggestion($key);

        foreach ($items as $item) {
            $suggestion[] = ["id" => $item->hsn_code, "text" => $item->hsn_code];
        }

        $suggestion[] = ["id" => "+", "text" => "+ " . lang("create_new_hsn_code")];

        return $this->response->setJSON($suggestion);
    }

    public function get_invoice_item_info_suggestion() {
        $item = $this->hsnSacCodeModel->get_item_info_suggestion($this->request->getPost("item_name"));
        if ($item) {
            return $this->response->setJSON(['success' => true, "item_info" => $item]);
        } else {
            return $this->response->setJSON(['success' => false]);
        }
    }
}

/* End of file Outsource_jobs.php */
/* Location: ./app/Controllers/Outsource_jobs.php */
