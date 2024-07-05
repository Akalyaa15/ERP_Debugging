<?php

namespace App\Controllers;

use App\Models\PartNoGenerationModel;
use App\Models\TaxesModel;
use App\Models\VendorsModel;
use App\Models\ManufacturerModel;
use App\Models\ProductCategoriesModel;
use App\Models\UnitTypeModel;
use App\Models\HsnSacCodeModel;
use App\Models\UsersModel;
use CodeIgniter\API\ResponseTrait;

class Part_no_generation extends BaseController
{
    protected $partNoGenerationModel;
    protected $taxesModel;
    protected $vendorsModel;
    protected $manufacturerModel;
    protected $productCategoriesModel;
    protected $unitTypeModel;
    protected $hsnSacCodeModel;
    protected $usersModel;

    public function __construct()
    {
        $this->partNoGenerationModel = new PartNoGenerationModel();
        $this->taxesModel = new TaxesModel();
        $this->vendorsModel = new VendorsModel();
        $this->manufacturerModel = new ManufacturerModel();
        $this->productCategoriesModel = new ProductCategoriesModel();
        $this->unitTypeModel = new UnitTypeModel();
        $this->hsnSacCodeModel = new HsnSacCodeModel();
        $this->usersModel = new UsersModel();

        helper(['form', 'url']); // Load helpers if needed
    }

    use ResponseTrait;

    public function index()
    {
        $this->checkModuleAvailability("module_production_data");

        if ($this->session->is_admin == "1") {
            $viewData['groups_dropdown'] = json_encode($this->_getGroupsDropdownSelect2Data());
            return view('part_no_generation/index', $viewData);
        } elseif ($this->session->user_type == "staff" || $this->session->user_type == "resource") {
            if ($this->access_type != "all" && !in_array($this->session->id, $this->allowed_members)) {
                return redirect()->to('forbidden');
            }
            $viewData['groups_dropdown'] = json_encode($this->_getGroupsDropdownSelect2Data());
            return view('part_no_generation/index', $viewData);
        } else {
            $viewData['groups_dropdown'] = json_encode($this->_getGroupsDropdownSelect2Data());
            return view('part_no_generation/index', $viewData);
        }
    }

    public function modal_form()
    {
        $this->validate([
            'id' => 'numeric',
        ]);

        $viewData['model_info'] = $this->partNoGenerationModel->getOne($this->request->getPost('id'));
        $teamMembers = $this->vendorsModel->where('deleted', 0)->findAll();
        $vendorsDropdown = [];

        foreach ($teamMembers as $teamMember) {
            $vendorsDropdown[] = ['id' => $teamMember->id, 'text' => $teamMember->company_name];
        }

        $viewData['vendors_dropdown'] = json_encode($vendorsDropdown);
        $viewData['unit_type_dropdown'] = json_encode($this->_getUnitTypeDropdownSelect2Data());
        $viewData['make_dropdown'] = json_encode($this->_getMakeDropdownSelect2Data());
        $viewData['product_categories_dropdown'] = json_encode($this->_getProductCategoriesDropdownSelect2Data());

        return view('part_no_generation/modal_form', $viewData);
    }

    private function _getUnitTypeDropdownSelect2Data()
    {
        $unitTypes = $this->unitTypeModel->where('deleted', 0)->where('status', 'active')->findAll();
        $unitTypeDropdown = [];

        foreach ($unitTypes as $unitType) {
            $unitTypeDropdown[] = ['id' => $unitType->title, 'text' => $unitType->title];
        }

        return $unitTypeDropdown;
    }

    private function _getMakeDropdownSelect2Data()
    {
        $makes = $this->manufacturerModel->where('deleted', 0)->where('status', 'active')->findAll();
        $makeDropdown = [['id' => '', 'text' => '-']];

        foreach ($makes as $make) {
            $makeDropdown[] = ['id' => $make->id, 'text' => $make->title];
        }

        $makeDropdown[] = ['id' => '+', 'text' => '+ ' . lang('create_new_manufacturer')];

        return $makeDropdown;
    }

    private function _getProductCategoriesDropdownSelect2Data()
    {
        $categories = $this->productCategoriesModel->where('deleted', 0)->where('status', 'active')->findAll();
        $categoriesDropdown = [['id' => '', 'text' => '-']];

        foreach ($categories as $category) {
            $categoriesDropdown[] = ['id' => $category->id, 'text' => $category->title];
        }

        $categoriesDropdown[] = ['id' => '+', 'text' => '+ ' . lang('create_new_category')];

        return $categoriesDropdown;
    }

    public function save()
    {
        $this->validate([
            'id' => 'numeric',
            // Add more validation rules as needed
        ]);

        $id = $this->request->getPost('id');
        $partNo = $this->request->getPost('title');

        $itemData = [
            'title' => $this->request->getPost('title'),
            'category' => $this->request->getPost('category'),
            'make' => $this->request->getPost('make'),
            'description' => $this->request->getPost('description'),
            'hsn_description' => $this->request->getPost('hsn_description'),
            'unit_type' => $this->request->getPost('unit_type'),
            'stock' => unformat_currency($this->request->getPost('item_stock')),
            'hsn_code' => $this->request->getPost('hsn_code'),
            'gst' => $this->request->getPost('gst'),
            'rate' => unformat_currency($this->request->getPost('item_rate')),
            'vendor_id' => $this->request->getPost('vendor_id'),
            'last_activity_user' => $this->session->id, // Adjust based on actual session handling
            'last_activity' => date('Y-m-d H:i:s'), // Adjust based on actual session handling
        ];

        // Check for duplicate part number
        if (!$id) {
            $options = ['part_no' => $partNo];
            $itemInfo = $this->partNoGenerationModel->getDetails($options)->getRow();
            if ($itemInfo) {
                return $this->fail(['message' => lang('duplicate_part_no')]);
            }
        } elseif ($id) {
            $options = ['id' => $id];
            $itemInfos = $this->partNoGenerationModel->getDetails($options)->getRow();
            if ($partNo != $itemInfos->title) {
                $options = ['part_no' => $partNo];
                $itemInfo = $this->partNoGenerationModel->getDetails($options)->getRow();
                if ($itemInfo) {
                    return $this->fail(['message' => lang('duplicate_part_no')]);
                }
            }
        }

// check the make and vendors 

$add_new_make_to_library = $this->input->post('add_new_make_to_library');
            if ($add_new_make_to_library) {


                $library_make_data = array(
                    "title" => $this->input->post('make'),
            

                    
                );

                // check the manufacturer name     
        $library_make_data["title"] =$this->input->post('make');
        if ($this->Manufacturer_model->is_manufacturer_list_exists($library_make_data["title"])) {
                echo json_encode(array("success" => false, 'message' => lang('manufacturer_already')));
                exit();
            }
        }


        //check product category
            $add_new_category_to_library = $this->input->post('add_new_category_to_library');
            if ($add_new_category_to_library) {


                $library_category_data = array(
                    "title" => $this->input->post('category'),
            

                    
                );

                // check the manufacturer name     
        $library_category_data["title"] =$this->input->post('category');
       if ($this->Product_categories_model->is_product_category_list_exists($library_category_data["title"])) {
            echo json_encode(array("success" => false, 'message' => lang("product_category_already")));
            exit();
        }
    }



         $item_id = $this->Part_no_generation_model->save($item_data, $id);
        if ($item_id) {

            $add_new_item_to_library = $this->input->post('add_new_item_to_library');
            if ($add_new_item_to_library) {
                $library_item_data = array(
                    "hsn_code" => $this->input->post('hsn_code'),
                    "gst" => $this->input->post('gst'),
                    "hsn_description" => $this->input->post('hsn_description')

                    
                );
                $this->Hsn_sac_code_model->save($library_item_data);
            }
// new make 
            $add_new_make_to_library = $this->input->post('add_new_make_to_library');
            if ($add_new_make_to_library) {


                $library_make_data = array(
                    "title" => $this->input->post('make'),
                    "last_activity_user"=>$this->login_user->id,
                    "last_activity" => get_current_utc_time(),
            

                    
                );

                // check the manufacturer name     
        $library_make_data["title"] =$this->input->post('make');
        if ($this->Manufacturer_model->is_manufacturer_list_exists($library_make_data["title"])) {
                echo json_encode(array("success" => false, 'message' => lang('manufacturer_already')));
                exit();
            }

          $save_make_generation = $this->Manufacturer_model->save($library_make_data);
         //save product id items table
     $product_make_data = array(
               "make" => $save_make_generation

                    );
             
             $this->Part_no_generation_model->save($product_make_data, $item_id);
            }

            // new product category
            //vendor name 
            $add_new_category_to_library = $this->input->post('add_new_category_to_library');
            if ($add_new_category_to_library) {


                $library_category_data = array(
                    "title" => $this->input->post('category'),
                    "last_activity_user"=>$this->login_user->id,
                    "last_activity" => get_current_utc_time(),
            

                    
                );

                // check the manufacturer name     
        $library_category_data["title"] =$this->input->post('category');
       if ($this->Product_categories_model->is_product_category_list_exists($library_category_data["title"])) {
            echo json_encode(array("success" => false, 'message' => lang("product_category_already")));
            exit();
        }

        $save_category_generation = $this->Product_categories_model->save($library_category_data);
         //save product id items table
     $product_category_data = array(
               "category" => $save_category_generation

                    );
             
             $this->Part_no_generation_model->save($product_category_data, $item_id);
    }


            $options = array("id" => $item_id);
            $item_info = $this->Part_no_generation_model->get_details($options)->row();
            echo json_encode(array("success" => true, "id" => $item_info->id, "data" => $this->_make_item_row($item_info), 'message' => lang('record_saved')));
        } else {
            echo json_encode(array("success" => false, 'message' => lang('error_occurred')));
        }
    }

    /* delete or undo an item */

    public function delete()
    {
        $id = $this->request->getPost('id');

        // Validate input data
        if (!$id || !is_numeric($id)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => lang('invalid_data')
            ]);
        }

        // Prepare data to update
        $data = [
            "last_activity_user" => $this->login_user->id,
            "last_activity" => get_current_utc_time(),
        ];

        // Save data to update
        $saveId = $this->partNoGenerationModel->save($data, $id);

        if ($this->request->getPost('undo')) {
            // Undo delete
            if ($this->partNoGenerationModel->delete($id, true)) {
                $itemInfo = $this->partNoGenerationModel->find($id);
                return $this->response->setJSON([
                    "success" => true,
                    "id" => $itemInfo->id,
                    "data" => $this->_make_item_row($itemInfo),
                    "message" => lang('record_undone')
                ]);
            } else {
                return $this->response->setJSON([
                    "success" => false,
                    "message" => lang('error_occurred')
                ]);
            }
        } else {
            // Perform delete
            if ($this->partNoGenerationModel->delete($id)) {
                return $this->response->setJSON([
                    "success" => true,
                    "message" => lang('record_deleted')
                ]);
            } else {
                return $this->response->setJSON([
                    "success" => false,
                    "message" => lang('record_cannot_be_deleted')
                ]);
            }
        }
    }

    public function list_data()
    {
        $options = [
            "group_id" => $this->request->getPost("group_id")
        ];

        $listData = $this->partNoGenerationModel->getDetails($options)->getResult();
        $result = [];

        foreach ($listData as $data) {
            $result[] = $this->_make_item_row($data);
        }

        return $this->response->setJSON([
            "data" => $result
        ]);
    }

    /* prepare a row of item list table */
    private function _makeItemRow($data)
    {
        $type = $data->unit_type ? $data->unit_type : "";
        $groupList = "";

        if ($data->groups) {
            $groups = explode(",", $data->groups);
            foreach ($groups as $group) {
                if ($group) {
                    $listGroup = $this->vendorsModel->find($group);
                    if ($listGroup) {
                        $groupList .= "<li>" . anchor("vendors/view/$group", $listGroup->company_name) . "</li>";
                    }
                }
            }
        }

        if ($groupList) {
            $groupList = "<ul class='pl15'>$groupList</ul>";
        }

        $makeName = $this->manufacturerModel->find($data->make);
        $categoryName = $this->productCategoriesModel->find($data->category);

        // Last activity user name and date
        $lastActivityByName = "-";
        if ($data->last_activity_user) {
            $lastActivityUser = $this->usersModel->find($data->last_activity_user);
            if ($lastActivityUser) {
                $lastActivityImage = get_avatar($lastActivityUser->image); // Implement your avatar function
                $lastActivityUserName = "<span class='avatar avatar-xs mr10'><img src='$lastActivityImage' alt='...'></span> $lastActivityUser->first_name $lastActivityUser->last_name";

                switch ($lastActivityUser->user_type) {
                    case "resource":
                        $lastActivityByName = get_rm_member_profile_link($data->last_activity_user, $lastActivityUserName); // Implement your profile link functions
                        break;
                    case "client":
                        $lastActivityByName = get_client_contact_profile_link($data->last_activity_user, $lastActivityUserName);
                        break;
                    case "staff":
                        $lastActivityByName = get_team_member_profile_link($data->last_activity_user, $lastActivityUserName);
                        break;
                    case "vendor":
                        $lastActivityByName = get_vendor_contact_profile_link($data->last_activity_user, $lastActivityUserName);
                        break;
                }
            }
        }

        $lastActivityDate = $data->last_activity ? format_to_relative_time($data->last_activity) : "-";

        return [
            $data->title,
            $groupList,
            nl2br($data->description),
            $categoryName ? $categoryName->title : "-",
            $makeName ? $makeName->title : "-",
            $data->hsn_code,
            $data->stock,
            $type,
            to_currency($data->rate),
            $data->gst . "%",
            to_currency($data->stock * $data->rate),
            $lastActivityByName,
            $lastActivityDate,
            modal_anchor("part_no_generation/modal_form", "<i class='fa fa-pencil'></i>", [
                "class" => "edit",
                "title" => lang('edit_item'),
                "data-post-id" => $data->id
            ]) . js_anchor("<i class='fa fa-times fa-fw'></i>", [
                "title" => lang('delete'),
                "class" => "delete",
                "data-id" => $data->id,
                "data-action-url" => "part_no_generation/delete",
                "data-action" => "delete-confirmation"
            ])
        ];
    }

    public function getInvoiceItemSuggestion()
    {
        $key = $this->request->getVar("q");
        $suggestion = [];

        $items = $this->HsnSacCodeModel->get_item_suggestion($key);

        foreach ($items as $item) {
            $suggestion[] = [
                "id" => $item->hsn_code,
                "text" => $item->hsn_code . " (" . $item->hsn_description . ")"
            ];
        }

        $suggestion[] = ["id" => "+", "text" => "+ " . lang("create_new_hsn_code")];

        return $this->respond($suggestion); // Return as JSON response
    }

    public function getInvoiceItemInfoSuggestion()
    {
        $itemName = $this->request->getPost("item_name");
        $item = $this->HsnSacCodeModel->get_item_info_suggestion($itemName);

        if ($item) {
            return $this->respond(["success" => true, "item_info" => $item]); // Return as JSON response
        } else {
            return $this->respond(["success" => false]); // Return as JSON response
        }
    }

    private function _getGroupsDropdownSelect2Data($showHeader = false)
    {
        $vendorGroups = $this->vendorsModel->findAll();
        $groupsDropdown = [];

        if ($showHeader) {
            $groupsDropdown[] = ["id" => "", "text" => "- " . lang("vendor_groups") . " -"];
        }

        foreach ($vendorGroups as $group) {
            $groupsDropdown[] = ["id" => $group->id, "text" => $group->company_name];
        }

        return $groupsDropdown;
    }
}