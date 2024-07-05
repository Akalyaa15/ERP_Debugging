<?php

namespace App\Controllers;

use App\Models\CustomFieldsModel;
use App\Models\ExpenseCategoriesModel;
use App\Models\ClientsModel;
use App\Models\VendorsModel;
use App\Models\UsersModel;
use App\Models\ProjectsModel;
use CodeIgniter\Controller;

class Loan extends BaseController
{
    protected $customFieldsModel;
    protected $expenseCategoriesModel;
    protected $clientsModel;
    protected $vendorsModel;
    protected $usersModel;
    protected $projectsModel;

    public function __construct()
    {
        $this->customFieldsModel = new CustomFieldsModel();
        $this->expenseCategoriesModel = new ExpenseCategoriesModel();
        $this->clientsModel = new ClientsModel();
        $this->vendorsModel = new VendorsModel();
        $this->usersModel = new UsersModel();
        $this->projectsModel = new ProjectsModel();
        
        parent::__construct();
        $this->init_permission_checker("loan");
    }

    public function index()
    {
        $this->check_module_availability("module_loan");

        $view_data["custom_field_headers"] = $this->customFieldsModel->get_custom_field_headers_for_table("loan", $this->login_user->is_admin, $this->login_user->user_type);
        $view_data['categories_dropdown'] = $this->_get_categories_dropdown();
        $view_data['members_dropdown'] = $this->_get_team_members_dropdown();
        $view_data['clients_dropdown'] = json_encode($this->_get_clients_dropdown());
        $view_data['vendors_dropdown'] = json_encode($this->_get_vendors_dropdown());
        $view_data['rm_members_dropdown'] = $this->_get_rm_members_dropdown();
        $view_data['projects_dropdown'] = $this->_get_projects_dropdown();

        return view("loan/index", $view_data);
    }

    private function _get_categories_dropdown()
    {
        $categories = $this->expenseCategoriesModel->where(['deleted' => 0, 'status' => 'active'])->findAll();

        $categories_dropdown = [['id' => '', 'text' => "- " . lang("category") . " -"]];
        foreach ($categories as $category) {
            $categories_dropdown[] = ['id' => $category['id'], 'text' => $category['title']];
        }

        return json_encode($categories_dropdown);
    }

    private function _get_clients_dropdown()
    {
        $clients_dropdown = [['id' => '', 'text' => "- " . lang("client") . " -"]];
        $clients = $this->clientsModel->get_dropdown_list(["company_name"]);
        foreach ($clients as $key => $value) {
            $clients_dropdown[] = ['id' => $key, 'text' => $value];
        }
        return $clients_dropdown;
    }

    private function _get_vendors_dropdown()
    {
        $vendors_dropdown = [['id' => '', 'text' => "- " . lang("vendor") . " -"]];
        $vendors = $this->vendorsModel->get_dropdown_list(["company_name"]);
        foreach ($vendors as $key => $value) {
            $vendors_dropdown[] = ['id' => $key, 'text' => $value];
        }
        return $vendors_dropdown;
    }

    private function _get_team_members_dropdown()
    {
        $team_members = $this->usersModel->where(['deleted' => 0, 'user_type' => 'staff'])->findAll();

        $members_dropdown = [['id' => '', 'text' => "- " . lang("member") . " -"]];
        foreach ($team_members as $team_member) {
            $members_dropdown[] = ['id' => $team_member['id'], 'text' => $team_member['first_name'] . " " . $team_member['last_name']];
        }

        return json_encode($members_dropdown);
    }

    private function _get_rm_members_dropdown()
    {
        $rm_members = $this->usersModel->where(['deleted' => 0, 'user_type' => 'resource'])->findAll();

        $rm_members_dropdown = [['id' => '', 'text' => "- " . lang("outsource_member") . " -"]];
        foreach ($rm_members as $rm_member) {
            $rm_members_dropdown[] = ['id' => $rm_member['id'], 'text' => $rm_member['first_name'] . " " . $rm_member['last_name']];
        }

        return json_encode($rm_members_dropdown);
    }

    private function _get_projects_dropdown()
    {
        $projects = $this->projectsModel->where(['deleted' => 0])->findAll();

        $projects_dropdown = [['id' => '', 'text' => "- " . lang("project") . " -"]];
        foreach ($projects as $project) {
            $projects_dropdown[] = ['id' => $project['id'], 'text' => $project['title']];
        }

        return json_encode($projects_dropdown);
    }

    public function yearly()
    {
        return view("loan/yearly_income");
    }

    public function custom()
    {
        return view("loan/custom_income");
    }
    
    public function modal_form()
    {
        helper('form');
        helper('url');

        // Validate input data
        $validationRules = [
            'id' => 'numeric'
        ];
        $this->validate($validationRules);

        $model_info = $this->loanModel->find($this->request->getPost('id'));
        $model_infos = $this->usersModel->find($this->request->getPost('user_id'));

        $view_data = [
            'categories_dropdown' => $this->expenseCategoriesModel->dropdown('title', 'id', ['status' => 'active']),
            'voucher_dropdown' => ['0' => '-'] + $this->voucherModel->dropdown('id', 'id', ['voucher_type_id' => '1']),
            'payment_status_dropdown' => $this->paymentStatusModel->dropdown('title', 'id', ['status' => 'active']),
            'members_dropdown' => ['0' => '-'] + $this->_get_team_members_dropdown(),
            'others_dropdown' => ['0' => '-'] + $this->_get_others_dropdown(),
            'rm_members_dropdown' => ['0' => '-'] + $this->_get_rm_members_dropdown(),
            'projects_dropdown' => ['0' => '-'] + $this->projectsModel->dropdown('title', 'id'),
            'taxes_dropdown' => ['' => '-'] + $this->taxesModel->dropdown('title', 'id'),
            'vendors_dropdown' => ['' => '-'] + $this->vendorsModel->dropdown('company_name', 'id'),
            'clients_dropdown' => ['' => '-'] + $this->clientsModel->dropdown('company_name', 'id'),
            'client_members_dropdown' => $this->_get_users_dropdown_select2_data(),
            'vendor_members_dropdown' => $this->_get_users_dropdown_select2_data(),
            'model_infos' => $model_infos,
            'model_info' => $model_info
        ];

        // Prepare voucher_id_dropdown
        $po_info = $this->voucherModel->find($model_info->voucher_no);
        $voucher_id_dropdown = [['id' => '', 'text' => '-']];
        $voucher_id_dropdown[] = [
            'id' => $model_info->voucher_no,
            'text' => $po_info->voucher_no ? $po_info->voucher_no : get_voucher_id($model_info->voucher_no)
        ];
        $view_data['voucher_id_dropdown'] = $voucher_id_dropdown;

        // Load custom fields
        $view_data['custom_fields'] = $this->customFieldsModel->get_combined_details('Loan', $view_data['model_info']->id, $this->login_user->is_admin, $this->login_user->user_type);

        echo view('loan/modal_form', $view_data);
    }

    private function _get_team_members_dropdown()
    {
        $team_members = $this->usersModel->where(['deleted' => 0, 'user_type' => 'staff'])->findAll();
        $members_dropdown = [];
        foreach ($team_members as $team_member) {
            $members_dropdown[$team_member['id']] = $team_member['first_name'] . ' ' . $team_member['last_name'];
        }
        return $members_dropdown;
    }

    private function _get_others_dropdown()
    {
        $others = $this->voucherExpensesModel->where(['deleted' => 0, 'member_type' => 'others'])->findAll();
        $others_dropdown = [];
        foreach ($others as $other) {
            $others_dropdown[$other['phone']] = $other['f_name'] . ' ' . $other['l_name'];
        }
        return $others_dropdown;
    }

    private function _get_rm_members_dropdown()
    {
        $rm_members = $this->usersModel->where(['deleted' => 0, 'user_type' => 'resource'])->findAll();
        $rm_members_dropdown = [];
        foreach ($rm_members as $rm_member) {
            $rm_members_dropdown[$rm_member['id']] = $rm_member['first_name'] . ' ' . $rm_member['last_name'];
        }
        return $rm_members_dropdown;
    }

    private function _get_users_dropdown_select2_data($show_header = false)
    {
        $luts = $this->usersModel->findAll();
        $lut_dropdown = [['id' => '', 'text' => '-']];
        foreach ($luts as $code) {
            $lut_dropdown[] = ['id' => $code['id'], 'text' => $code['first_name'] . ' ' . $code['last_name']];
        }
        return $lut_dropdown;
    }

    public function save()
    {
        helper('form');

        // Validate input data
        $validationRules = [
            'id' => 'numeric',
            'loan_date' => 'required',
            'category_id' => 'required',
            'amount' => 'required'
        ];
        $this->validate($validationRules);

        // Process form data
        $data = [
            'loan_date' => $this->request->getPost('loan_date'),
            'due_date' => $this->request->getPost('due_date'),
            'title' => $this->request->getPost('title'),
            'description' => $this->request->getPost('description'),
            'category_id' => $this->request->getPost('category_id'),
            'amount' => unformat_currency($this->request->getPost('amount')),
            'project_id' => $this->request->getPost('loan_project_id'),
            'user_id' => $this->request->getPost('loan_user_id'),
            'interest_amount' => $this->calculate_interest_amount(),
            'total' => $this->calculate_total_amount(),
            'voucher_no' => $this->request->getPost('voucher_no'),
            'currency' => $this->request->getPost('currency'),
            'currency_symbol' => $this->request->getPost('currency_symbol'),
            'payment_status' => $this->request->getPost('payment_status'),
            'interest' => $this->request->getPost('interest'),
            'member_type' => $this->request->getPost('member_type'),
            'phone' => $this->request->getPost('loan_user_idss'),
            'company' => $this->request->getPost('client_member'),
            'vendor_company' => $this->request->getPost('vendor_member')
        ];

        // Additional processing as needed

        // Save data to database
        $save_id = $this->loanModel->save($data, $this->request->getPost('id'));

        if ($save_id) {
            // Handle custom fields saving if needed
            save_custom_fields("loan", $save_id, $this->login_user->is_admin, $this->login_user->user_type);

            echo json_encode(['success' => true, 'data' => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
        } else {
            echo json_encode(['success' => false, 'message' => lang('error_occurred')]);
        }
    }
  // Delete an expense
  public function delete()
  {
      $this->validation->setRules([
          'id' => 'required|numeric'
      ]);

      if (!$this->validation->withRequest($this->request)->run()) {
          echo json_encode(['success' => false, 'message' => $this->validation->getErrors()]);
          return;
      }

      $id = $this->request->getPost('id');
      $loan_info = $this->loanModel->find($id);

      $data = [
          "last_activity_user" => $this->login_user->id,
          "last_activity" => date('Y-m-d H:i:s')
      ];
      $save_id = $this->loanModel->save($data, $id);

      if ($this->loanModel->delete($id)) {
          // Delete associated files
          $file_path = get_setting("timeline_file_path");
          if ($loan_info->files) {
              $files = unserialize($loan_info->files);

              foreach ($files as $file) {
                  $source_path = $file_path . get_array_value($file, "file_name");
                  delete_file_from_directory($source_path);
              }
          }

          echo json_encode(['success' => true, 'message' => lang('record_deleted')]);
      } else {
          echo json_encode(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
      }
  }

  // Get expense list data
  public function list_data()
  {
      $start_date = $this->request->getPost('start_date');
      $end_date = $this->request->getPost('end_date');
      $category_id = $this->request->getPost('category_id');
      $project_id = $this->request->getPost('project_id');
      $user_id = $this->request->getPost('user_id');
      $user_ids = $this->request->getPost('user_ids');
      $client_id = $this->request->getPost('client_id');
      $vendor_id = $this->request->getPost('vendor_id');
      $status = $this->request->getPost("status");

      $custom_fields = $this->customFieldsModel->get_available_fields_for_table("loan", $this->login_user->is_admin, $this->login_user->user_type);

      $options = [
          "start_date" => $start_date,
          "end_date" => $end_date,
          "category_id" => $category_id,
          "project_id" => $project_id,
          "user_id" => $user_ids ? $user_ids : $user_id,
          "client_id" => $client_id,
          "vendor_id" => $vendor_id,
          "status" => $status,
          "custom_fields" => $custom_fields
      ];

      $list_data = $this->loanModel->get_details($options)->getResult();
      $result = [];
      foreach ($list_data as $data) {
          $result[] = $this->_make_row($data, $custom_fields);
      }
      echo json_encode(["data" => $result]);
  }

  // Get a row of expense list
  private function _row_data($id)
  {
      $custom_fields = $this->customFieldsModel->get_available_fields_for_table("loan", $this->login_user->is_admin, $this->login_user->user_type);
      $options = ["id" => $id, "custom_fields" => $custom_fields];
      $data = $this->loanModel->get_details($options)->getRow();
      return $this->_make_row($data, $custom_fields);
  }

  private function _make_row($data, $custom_fields)
  {
      $description = $data->description;
      if ($data->project_title) {
          $description .= ($description ? "<br />" : "") . lang("project") . ": " . $data->project_title;
      }
  
      switch ($data->member_type) {
          case 'tm':
              if ($data->linked_user_name) {
                  $description .= ($description ? "<br />" : "") . lang("team_member") . ": " . $data->linked_user_name;
              }
              break;
          case 'om':
              if ($data->linked_user_name) {
                  $description .= ($description ? "<br />" : "") . lang("outsource_member") . ": " . $data->linked_user_name;
              }
              break;
          case 'clients':
              if ($data->client_company) {
                  $description .= ($description ? "<br />" : "") . lang("client_company") . ": " . $data->client_company . "<br>";
                  $description .= lang("client_contact_member") . ": " . $data->linked_user_name;
              }
              break;
          case 'vendors':
              if ($data->vendor_company) {
                  $description .= ($description ? "<br />" : "") . lang("vendor_company") . ": " . $data->vendor_company . "<br>";
                  $description .= lang("vendor_contact_member") . ": " . $data->linked_user_name;
              }
              break;
          case 'others':
              if ($data->phone) {
                  $description .= ($description ? "<br />" : "") . lang("other_contact") . ": " . $data->phone . " " . $data->l_name;
              }
              break;
      }
  
      $due = ignor_minor_value($data->total - $data->paid_amount);
  
      $title = modal_anchor(route_to('loan_view'), $data->category_title . $icon, [
          'title' => lang('loan_info') . " #$data->id",
          'data-post-id' => $data->id,
          'class' => $unread_comments_class
      ]);
  
      $item = "<div style='color:#4e5e6a;font-size:12px;'>$description</div>";
  
      $files_link = "";
      if ($data->files) {
          $files = unserialize($data->files);
          if (!empty($files)) {
              foreach ($files as $file) {
                  $file_name = $file['file_name'];
                  $link = " fa fa-" . get_file_icon(strtolower(pathinfo($file_name, PATHINFO_EXTENSION)));
                  $files_link .= js_anchor(" ", [
                      'title' => "",
                      'data-toggle' => "app-modal",
                      'data-sidebar' => "0",
                      'class' => "pull-left font-22 mr10 $link",
                      'title' => remove_file_prefix($file_name),
                      'data-url' => route_to('file_preview', $file_name)
                  ]);
              }
          }
      }
  
      $files_links = "";
      $payment_files = $this->Loan_payments_list_model->get_details(["loan_id" => $data->id])->getResult();
      foreach ($payment_files as $payment_file) {
          if ($payment_file->files) {
              $files = unserialize($payment_file->files);
              if (!empty($files)) {
                  foreach ($files as $file) {
                      $file_name = $file['file_name'];
                      $link = " fa fa-" . get_file_icon(strtolower(pathinfo($file_name, PATHINFO_EXTENSION)));
                      $files_links .= js_anchor(" ", [
                          'title' => "",
                          'data-toggle' => "app-modal",
                          'data-sidebar' => "0",
                          'class' => "pull-left font-22 mr10 $link",
                          'title' => remove_file_prefix($file_name),
                          'data-url' => route_to('file_preview', $file_name)
                      ]);
                  }
              }
          }
      }
  
      // last activity
      $last_activity_by_user_name = "-";
      if ($data->last_activity_user) {
          $last_activity_user_data = $this->Users_model->find($data->last_activity_user);
          $last_activity_image_url = get_avatar($last_activity_user_data->image);
          $last_activity_user = "<span class='avatar avatar-xs mr10'><img src='$last_activity_image_url' alt='...'></span> $last_activity_user_data->first_name $last_activity_user_data->last_name";
  
          switch ($last_activity_user_data->user_type) {
              case "resource":
                  $last_activity_by_user_name = get_rm_member_profile_link($data->last_activity_user, $last_activity_user);
                  break;
              case "client":
                  $last_activity_by_user_name = get_client_contact_profile_link($data->last_activity_user, $last_activity_user);
                  break;
              case "staff":
                  $last_activity_by_user_name = get_team_member_profile_link($data->last_activity_user, $last_activity_user);
                  break;
              case "vendor":
                  $last_activity_by_user_name = get_vendor_contact_profile_link($data->last_activity_user, $last_activity_user);
                  break;
          }
      }
  
      $last_activity_date = $data->last_activity ? format_to_relative_time($data->last_activity) : "-";
  
      // voucher no
      if ($data->voucher_no) {
          $voucher_info = $this->Voucher_model->find($data->voucher_no);
          $voucher_order_url = anchor(route_to('voucher_view', $data->voucher_no), $voucher_info->voucher_no ?: get_voucher_id($data->voucher_no));
      } else {
          $voucher_order_url = "-";
      }
  
      $row_data = [
          $voucher_order_url,
          $data->loan_date,
          format_to_date($data->loan_date, false),
          //$data->category_title,
          $data->due_date,
          $title,
          $data->title,
          $item,
          //nl2br($description),
          to_currency($data->amount, $data->currency_symbol),
          $data->interest,
          to_currency($data->interest_amount, $data->currency_symbol),
          to_currency($data->total, $data->currency_symbol),
          to_currency($data->paid_amount, $data->currency_symbol),
          to_currency($due, $data->currency_symbol),
          $files_link . $files_links,
          $this->_get_loan_status_label($data),
          $this->_get_loan_voucher_status_label($data),
          $last_activity_by_user_name,
          $last_activity_date,
          //to_currency($data->igst_tax),
          //to_currency($data->total)
      ];
  
      foreach ($custom_fields as $field) {
          $cf_id = "cfv_" . $field->id;
          $row_data[] = view("custom_fields/output_" . $field->field_type, ["value" => $data->$cf_id]);
      }
  
      $row_data[] = modal_anchor(route_to('modal_form'), "<i class='fa fa-pencil'></i>", ['class' => "edit", 'title' => lang('edit_loan'), 'data-post-id' => $data->id])
          . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_loan'), 'class' => "delete", 'data-id' => $data->id, 'data-action-url' => route_to('loan_delete'), 'data-action' => "delete-confirmation"]);
  
      return $row_data;
  }
  
  private function _get_loan_status_label($data, $return_html = true)
  {
      return get_loan_status_label($data, $return_html); // Assuming get_loan_status_label is a helper or utility function.
  }
  
  private function _get_loan_voucher_status_label($estimate_info, $return_html = true)
  {
      $estimate_status_class = "label-default";
  
      // Get payment status information
      $payment_status_info = $this->Payment_status_model->find($estimate_info->payment_status);
  
      // Adjust status display logic
      $estimate_status = "<span class='label $estimate_status_class large'>" . $payment_status_info->title . "</span>";
  
      if ($return_html) {
          return $estimate_status;
      } else {
          return $estimate_info->status; // Assuming there's a status field in $estimate_info.
      }
  }
  
  public function file_preview($file_name = "")
  {
      if ($file_name) {
          $view_data = [
              "file_url" => get_file_uri(get_setting("timeline_file_path") . $file_name),
              "is_image_file" => is_image_file($file_name),
              "is_google_preview_available" => is_google_preview_available($file_name)
          ];
  
          return view("loan/file_preview", $view_data);
      } else {
          throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
      }
  }  
    /* upload a file */

    public function upload_file()
    {
        upload_file_to_temp();
    }
    
    /* check valid file for ticket */
    public function validate_loan_file()
    {
        return validate_post_file($this->request->getPost("file_name"));
    }
    
    //load the expenses yearly chart view
    public function yearly_chart()
    {
        return view("loan/yearly_chart");
    }
    
    public function yearly_chart_data()
    {
        $months = ["january", "february", "march", "april", "may", "june", "july", "august", "september", "october", "november", "december"];
        $result = [];
    
        $year = $this->request->getPost("year");
        if ($year) {
            $loan = $this->Loan_model->get_yearly_loan_chart($year);
            $values = [];
            foreach ($loan as $value) {
                $values[$value->month - 1] = $value->total;
            }
    
            foreach ($months as $key => $month) {
                $value = get_array_value($values, $key);
                $result[] = [lang("short_" . $month), $value ? $value : 0];
            }
    
            return $this->response->setJSON(["data" => $result]);
        }
    }
    public function get_voucher_details()
{
    $item_name = $this->request->getPost("item_name");
    $item = $this->Loan_model->get_voucher_expense_details($item_name);

    $files_link = "";
    if ($item->files) {
        $files = unserialize($item->files);
        if (count($files)) {
            foreach ($files as $file) {
                $file_name = get_array_value($file, "file_name");
                $link = " fa fa-" . get_file_icon(strtolower(pathinfo($file_name, PATHINFO_EXTENSION)));
                $files_link .= js_anchor(" ", [
                    'title' => "",
                    "data-toggle" => "app-modal",
                    "data-sidebar" => "0",
                    "class" => "pull-left font-22 mr10 $link",
                    "title" => remove_file_prefix($file_name),
                    "data-url" => get_uri("voucher/file_preview/" . $file_name)
                ]);
            }
        }
    }
    $files_link .= "<a title='Voucher pdf' href='" . get_uri("voucher/download_pdf/" . $item->estimate_id) . "' class='pull-left font-22 mr10 fa fa-file-pdf-o'></a>";

    $items = $this->Voucher_model->find($item_name);
    $status = lang($items->status);

    if ($item) {
        return $this->response->setJSON(["success" => true, "item_info" => $item, "item_files" => $files_link, "item_status" => $status]);
    } else {
        return $this->response->setJSON(["success" => false]);
    }
}

public function get_voucher_id()
{
    $team_member = $this->request->getPost("team_member");

    $options = ["user_id" => $team_member];
    $list_data = $this->Loan_model->get_details($options)->getResult();

    if ($list_data) {
        $loan_items = [];
        foreach ($list_data as $code) {
            $loan_items[] = $code->voucher_no;
        }
        $loan_voucher_no = json_encode($loan_items);
        $loan_voucher_no = str_replace("[", "(", $loan_voucher_no);
        $loan_voucher_no = str_replace("]", ")", $loan_voucher_no);
    } else {
        $loan_voucher_no = "('empty')";
    }

    $itemss = $this->Loan_model->get_voucher_id($team_member, $loan_voucher_no);
    $suggestions = [];

    foreach ($itemss as $items) {
        $po_info = $this->Voucher_model->find($items->estimate_id);
        $suggestions[] = [
            "id" => $items->estimate_id,
            "text" => $po_info->voucher_no ? $po_info->voucher_no : get_voucher_id($items->estimate_id),
        ];
    }

    return $this->response->setJSON($suggestions);
}
public function get_client_contacts()
{
    $team_member = $this->request->getPost("team_member");

    $itemss = $this->Loan_model->get_client_contacts($team_member);
    $suggestions = [];

    foreach ($itemss as $items) {
        $suggestions[] = [
            "id" => $items->id,
            "text" => $items->first_name . " " . $items->last_name,
        ];
    }

    return $this->response->setJSON($suggestions);
}

/*public function get_vendor_contacts()
{
    $team_member = $this->request->getPost("team_member");

    $itemss = $this->Loan_model->get_vendor_contacts($team_member);
    $suggestions = [];

    foreach ($itemss as $items) {
        $suggestions[] = [
            "id" => $items->id,
            "text" => $items->first_name . " " . $items->last_name,
        ];
    }

    return $this->response->setJSON($suggestions);
}*/



public function loan_view()
{
    $loan_id = $this->request->getPost('id');
    $model_info = $this->Loan_model->find($loan_id);

    if (!$model_info) {
        throw PageNotFoundException::forPageNotFound();
    }

    $view_data = [
        'model_info' => $model_info,
        'loan_id' => $loan_id,
        'payment_methods_dropdown' => $this->Payment_methods_model->getDropdownList(["title"], "id", ["online_payable" => 0, "deleted" => 0]),
    ];

    return view('loan/view', $view_data);
}



/* checklist */

public function save_checklist_item()
{
    $id = $this->request->getPost("id");

    $validation = \Config\Services::validation();
    $validation->setRules([
        'id' => 'required|numeric'
    ]);

    if (!$validation->withRequest($this->request)->run()) {
        return $this->response->setJSON(["success" => false, "message" => $validation->getErrors()]);
    }

    helper(['form', 'file']);

    $target_path = get_setting("timeline_file_path");
    $files_data = move_files_from_temp_dir_to_permanent_dir($target_path, "loan_payment");
    $new_files = unserialize($files_data);

    $data = [
        "loan_id" => $id,
        "title" => $this->request->getPost("checklist-add-item"),
        "payment_date" => $this->request->getPost("checklist-add-item-date"),
        "payment_method_id" => $this->request->getPost("payment_method_id"),
        "description" => $this->request->getPost("description"),
        "reference_number" => $this->request->getPost("reference_number"),
    ];

    $note_info = $this->Loan_payments_list_model->find($id);
    $timeline_file_path = get_setting("timeline_file_path");
    $new_files = update_saved_files($timeline_file_path, $note_info->files, $new_files);
    $data["files"] = serialize($new_files);

    if (empty($new_files)) {
        return $this->response->setJSON(["success" => false, "message" => "*Uploading files are required"]);
    }

    $save_id = $this->Loan_payments_list_model->save($data);

    if ($save_id) {
        $item_info = $this->Loan_payments_list_model->find($save_id);
        return $this->response->setJSON(["success" => true, "data" => $this->_make_payment_row($item_info), 'id' => $save_id]);
    } else {
        return $this->response->setJSON(["success" => false]);
    }
}
/* list of invoice payments, prepared for datatable  */
public function loan_payment_list_data($id = 0)
{
    $start_date = $this->request->getPost('start_date');
    $end_date = $this->request->getPost('end_date');
    //$payment_method_id = $this->request->getPost('payment_method_id');
    $options = ["loan_id" => $id];

    $list_data = $this->Loan_payments_list_model->get_details($options)->getResult();
    $result = [];

    foreach ($list_data as $data) {
        $result[] = $this->_make_payment_row($data);
    }

    return $this->response->setJSON(["data" => $result]);
}

/* prepare a row of invoice payment list table */

private function _make_payment_row($data)
{
    $invoice_url = "";
    $files_link = "";

    if ($data->files) {
        $files = unserialize($data->files);
        if (count($files)) {
            foreach ($files as $file) {
                $file_name = get_array_value($file, "file_name");
                $link = " fa fa-" . get_file_icon(strtolower(pathinfo($file_name, PATHINFO_EXTENSION)));
                $files_link .= js_anchor(" ", ['title' => "", "data-toggle" => "app-modal", "data-sidebar" => "0", "class" => "pull-left font-22 mr10 $link", "title" => remove_file_prefix($file_name), "data-url" => get_uri("notes/file_preview/" . $file_name)]);
            }
        }
    }

    return [
        $data->payment_date,
        $data->loan_payment_name,
        $data->reference_number,
        $data->title,
        $files_link,
        js_anchor("<i class='fa fa-times'></i>", ['title' => lang('delete'), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("loan/delete_payment"), "data-action" => "delete-confirmation"]),
    ];
}

  /* delete or undo a payment */

  public function delete_payment()
  {
      $id = $this->request->getPost('id');
  
      $validation = \Config\Services::validation();
      $validation->setRules([
          'id' => 'required|numeric'
      ]);
  
      if (!$validation->withRequest($this->request)->run()) {
          return $this->response->setJSON(["success" => false, "message" => $validation->getErrors()]);
      }
  
      if ($this->Loan_payments_list_model->delete($id)) {
          return $this->response->setJSON(["success" => true, 'message' => lang('record_deleted')]);
      } else {
          return $this->response->setJSON(["success" => false, 'message' => lang('record_cannot_be_deleted')]);
      }
  }
  
  public function get_vendors_invoice_paid_suggestion()
  {
      $item = $this->Loan_payments_list_model->get_vendors_invoice_paid_amount_suggestion($this->request->getPost("item_name"));
  
      if ($item) {
          return $this->response->setJSON(["success" => true, "item_info" => $item]);
      } else {
          return $this->response->setJSON(["success" => false]);
      }
  }

}
  

/* End of file expenses.php */
/* Location: ./application/controllers/expenses.php */