<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UnitTypeModel;
use App\Models\JobIdGenerationModel;
use App\Models\ServiceIdGenerationModel;
use App\Models\ServiceCategoriesModel;
use App\Models\HsnSacCodeModel;
use App\Models\ManufacturerModel;
use App\Models\UsersModel;

class ServiceIdGeneration extends BaseController
{
    protected $unitTypeModel;
    protected $jobIdGenerationModel;
    protected $serviceIdGenerationModel;
    protected $serviceCategoriesModel;
    protected $hsnSacCodeModel;
    protected $manufacturerModel;
    protected $usersModel;

    public function __construct()
    {
        $this->unitTypeModel = new UnitTypeModel();
        $this->jobIdGenerationModel = new JobIdGenerationModel();
        $this->serviceIdGenerationModel = new ServiceIdGenerationModel();
        $this->serviceCategoriesModel = new ServiceCategoriesModel();
        $this->hsnSacCodeModel = new HsnSacCodeModel();
        $this->manufacturerModel = new ManufacturerModel();
        $this->usersModel = new UsersModel();
        
        parent::__construct();
        $this->initPermissionChecker("production_data");
    }

    private function getUnitTypeDropdownSelect2Data()
    {
        $unitTypes = $this->unitTypeModel->where(['deleted' => 0, 'status' => 'active'])->findAll();
        $unitTypeDropdown = [];

        foreach ($unitTypes as $type) {
            $unitTypeDropdown[] = ['id' => $type['title'], 'text' => $type['title']];
        }

        return $unitTypeDropdown;
    }

    public function index()
    {
        $this->checkModuleAvailability("module_production_data");

        if ($this->loginUser->is_admin == "1") {
            return view('service_id_generation/index');
        } elseif ($this->loginUser->user_type == "staff") {
            $this->accessOnlyAllowedMembers();

            return view('service_id_generation/index');
        } else {
            return view('service_id_generation/index');
        }
    }

    public function modalForm()
    {
        $this->validate([
            'id' => 'numeric'
        ]);

        $teamMembers = $this->jobIdGenerationModel->where(['deleted' => 0])->findAll();
        $partNoDropdown = [];

        foreach ($teamMembers as $member) {
            $partNoDropdown[] = ['id' => $member['id'], 'text' => $member['title'] . '(' . $member['description'] . ')'];
        }

        $viewData = [
            'model_info' => $this->serviceIdGenerationModel->find($this->request->getVar('id')),
            'part_no_dropdown' => json_encode($partNoDropdown),
            'unit_type_dropdown' => $this->getUnitTypeDropdownSelect2Data(),
            'product_categories_dropdown' => json_encode($this->getServiceCategoriesDropdown()),
        ];

        return view('service_id_generation/modal_form', $viewData);
    }

    public function save()
    {
        $this->validate([
            'id' => 'numeric',
            'title' => 'required'
        ]);

        $id = $this->request->getVar('id');
        $serviceId = $this->request->getVar('title');
        $data = [
            'title' => $this->request->getVar('title'),
            'associated_with_part_no' => $this->request->getVar('associated_with_part_no'),
            'description' => $this->request->getVar('description'),
            'category' => $this->request->getVar('category'),
            'hsn_description' => $this->request->getVar('hsn_description'),
            'unit_type' => $this->request->getVar('unit_type'),
            'hsn_code' => $this->request->getVar('hsn_code'),
            'gst' => $this->request->getVar('gst'),
            'last_activity_user' => $this->loginUser->id,
            'last_activity' => date('Y-m-d H:i:s')
        ];

        // Check for duplicate service ID
        if (!$id) {
            $existingService = $this->serviceIdGenerationModel->where('service_id', $serviceId)->first();
            if ($existingService) {
                return $this->response->setJSON(['success' => false, 'message' => lang('duplicate_service_id')]);
            }
        } elseif ($id) {
            $existingService = $this->serviceIdGenerationModel->where('id', $id)->first();
            if ($serviceId != $existingService['title']) {
                $checkServiceId = $this->serviceIdGenerationModel->where('service_id', $serviceId)->first();
                if ($checkServiceId) {
                    return $this->response->setJSON(['success' => false, 'message' => lang('duplicate_service_id')]);
                }
            }
        }

        $savedId = $this->serviceIdGenerationModel->save($data, $id);

        if ($savedId) {
            // Handle additional actions if needed
            return $this->response->setJSON(['success' => true, 'data' => $this->_rowData($savedId), 'id' => $savedId, 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function delete()
    {
        $this->validate([
            'id' => 'numeric|required'
        ]);

        $id = $this->request->getVar('id');
        $data = [
            'last_activity_user' => $this->loginUser->id,
            'last_activity' => date('Y-m-d H:i:s')
        ];

        $savedId = $this->serviceIdGenerationModel->save($data, $id);

        if ($this->request->getVar('undo')) {
            if ($this->serviceIdGenerationModel->delete($id, true)) {
                return $this->response->setJSON(['success' => true, 'data' => $this->_rowData($id), 'message' => lang('record_undone')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($this->serviceIdGenerationModel->delete($id)) {
                return $this->response->setJSON(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }

    public function listData()
    {
        $listData = $this->serviceIdGenerationModel->findAll();
        $result = [];

        foreach ($listData as $data) {
            $result[] = $this->_makeRow($data);
        }

        return $this->response->setJSON(['data' => $result]);
    }

    private function _rowData($id)
    {
        $data = $this->serviceIdGenerationModel->find($id);
        return $this->_makeRow($data);
    }

    private function _makeRow($data)
    {
        $groupList = 0;
        $groups = explode(',', $data['associated_with_part_no']);
        
        foreach ($groups as $group) {
            if (!empty($group)) {
                $groupData = $this->jobIdGenerationModel->find($group);
                $groupList += $groupData['rate'];
            }
        }

        $categoryName = $this->serviceCategoriesModel->find($data['category']);

        $lastActivityByUserName = "-";
        if ($data['last_activity_user']) {
            $lastActivityUserData = $this->usersModel->find($data['last_activity_user']);
            $lastActivityImage = getAvatar($lastActivityUserData['image']);
            $lastActivityUser = "<span class='avatar avatar-xs mr10'><img src='$lastActivityImage' alt='...'></span> $lastActivityUserData->first_name $lastActivityUserData->last_name";

            switch ($lastActivityUserData['user_type']) {
                case "resource":
                    $lastActivityByUserName = getRmMemberProfileLink($data['last_activity_user'], $lastActivityUser);
                    break;
                case "client":
                    $lastActivityByUserName = getClientContactProfileLink($data['last_activity_user'], $lastActivityUser);
                    break;
                case "staff":
                    $lastActivityByUserName = getTeamMemberProfileLink($data['last_activity_user'], $lastActivityUser);
                    break;
                case "vendor":
                    $lastActivityByUserName = getVendorContactProfileLink($data['last_activity_user'], $lastActivityUser);
                    break;
                default:
                    $lastActivityByUserName = "-";
            }
        }

        $lastActivityDate = $data['last_activity'] ? formatToRelativeTime($data['last_activity']) : "-";

        return [
            $data['title'],
            $data['description'],
            $categoryName['title'] ? $categoryName['title'] : "-",
            $data['hsn_code'],
            $data['gst'] . "%",
            $data['unit_type'] ?: "",
            toCurrency($groupList),
            $lastActivityByUserName,
            $lastActivityDate,
            modalAnchor(getUri("service_id_generation/modal_form"), "<i class='fa fa-pencil'></i>", ['class' => 'edit', 'title' => lang('edit_service'), 'data-post-id' => $data['id']])
                . jsAnchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete'), 'class' => 'delete', 'data-id' => $data['id'], 'data-action-url' => getUri("service_id_generation/delete"), 'data-action' => 'delete-confirmation'])
        ];
    }

    public function getInvoiceItemSuggestion()
    {
        $key = $this->request->getVar("q");
        $suggestion = [];
        $items = $this->hsnSacCodeModel->getItemSuggestion($key);

        foreach ($items as $item) {
            $suggestion[] = ['id' => $item['hsn_code'], 'text' => $item['hsn_code'] . " (" . $item['hsn_description'] . ")"];
        }

        $suggestion[] = ['id' => "+", 'text' => "+ " . lang('create_new_hsn_code')];
        return $this->response->setJSON($suggestion);
    }

    public function getInvoiceItemInfoSuggestion()
    {
        $itemName = $this->request->getVar("item_name");
        $item = $this->hsnSacCodeModel->getItemInfoSuggestion($itemName);

        if ($item) {
            return $this->response->setJSON(['success' => true, 'item_info' => $item]);
        } else {
            return $this->response->setJSON(['success' => false]);
        }
    }
}
