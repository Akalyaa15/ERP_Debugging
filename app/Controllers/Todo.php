<?php
namespace App\Controllers;
use App\Models\TodoModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Todo extends BaseController
{
    protected $todoModel;
    public function __construct()
    {
        $this->todoModel = new TodoModel(); // Example model initialization

        // Ensure to call the parent constructor
        parent::__construct();
    }

    protected function validateAccess($todoInfo)
    {
        if ($this->login_user->id !== $todoInfo->created_by) {
            return redirect()->to('forbidden');
        }
    }

    // Load todo list view
    public function index()
    {
        $this->check_module_availability("module_todo");

        return view('todo/index');
    }

    public function modal_form()
    {
        $viewData['model_info'] = $this->todoModel->find($this->request->getPost('id'));

        $labels = explode(",", $this->todoModel->get_label_suggestions($this->login_user->id));

        // Check permission for saved todo list
        if (!empty($viewData['model_info'])) {
            $this->validateAccess($viewData['model_info']);
        }

        $labelSuggestions = [];
        foreach ($labels as $label) {
            if ($label && !in_array($label, $labelSuggestions)) {
                $labelSuggestions[] = $label;
            }
        }
        if (empty($labelSuggestions)) {
            $labelSuggestions = ["Important"];
        }
        $viewData['label_suggestions'] = $labelSuggestions;

        return view('todo/modal_form', $viewData);
    }

    public function save()
    {
        // Validate input
        $validationRules = [
            'id' => 'numeric',
            'title' => 'required',
        ];

        if (!$this->validate($validationRules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $id = $this->request->getPost('id');

        $data = [
            'title' => $this->request->getPost('title'),
            'description' => $this->request->getPost('description') ?? '',
            'created_by' => $this->login_user->id,
            'labels' => $this->request->getPost('labels') ?? '',
            'start_date' => $this->request->getPost('start_date'),
        ];

        // Clean data
        $data = clean_data($data);

        // Set null value after cleaning the data
        if (!$data['start_date']) {
            $data['start_date'] = null;
        }

        if ($id) {
            // Saving existing todo. Check permission
            $todoInfo = $this->todoModel->find($id);

            $this->validateAccess($todoInfo);
        } else {
            $data['created_at'] = get_current_utc_time();
        }

        $saveId = $this->todoModel->save($data, $id);

        if ($saveId) {
            return $this->respond([
                'success' => true,
                'data' => $this->_rowData($saveId),
                'id' => $saveId,
                'message' => lang('record_saved'),
            ]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

    public function save_status()
    {
        // Validate input
        $validationRules = [
            'id' => 'numeric|required',
            'status' => 'required',
        ];

        if (!$this->validate($validationRules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $this->access_only_team_members();

        $data = [
            'status' => $this->request->getPost('status'),
        ];

        $saveId = $this->todoModel->save($data, $this->request->getPost('id'));

        if ($saveId) {
            return $this->respond([
                'success' => true,
                'data' => $this->_rowData($saveId),
                'id' => $saveId,
                'message' => lang('record_saved'),
            ]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }
    public function delete()
    {
        // Validate input
        $validationRules = [
            'id' => 'required|numeric',
        ];

        if (!$this->validate($validationRules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $id = $this->request->getPost('id');

        $todoInfo = $this->todoModel->find($id);
        $this->validateAccess($todoInfo);

        if ($this->request->getPost('undo')) {
            if ($this->todoModel->delete($id, true)) {
                return $this->respond([
                    'success' => true,
                    'data' => $this->_rowData($id),
                    'message' => lang('record_undone'),
                ]);
            } else {
                return $this->fail(lang('error_occurred'));
            }
        } else {
            if ($this->todoModel->delete($id)) {
                return $this->respond([
                    'success' => true,
                    'message' => lang('record_deleted'),
                ]);
            } else {
                return $this->fail(lang('record_cannot_be_deleted'));
            }
        }
    }

    public function list_data()
    {
        $status = $this->request->getPost('status') ?? '';
        $options = [
            'created_by' => $this->login_user->id,
            'status' => implode(",", $status),
        ];

        $listData = $this->todoModel->get_details($options)->getResult();
        $result = [];
        foreach ($listData as $data) {
            $result[] = $this->_makeRow($data);
        }

        return $this->respond([
            'data' => $result,
        ]);
    }

    private function _rowData($id)
    {
        $options = ['id' => $id];
        $data = $this->todoModel->get_details($options)->getRow();
        return $this->_makeRow($data);
    }

    private function _makeRow($data)
    {
        $title = modal_anchor(get_uri("todo/view/{$data->id}"), $data->title, [
            'class' => 'edit',
            'title' => lang('todo'),
            'data-post-id' => $data->id,
        ]);

        $todoLabels = "";
        if ($data->labels) {
            $labels = explode(",", $data->labels);
            foreach ($labels as $label) {
                if ($label && !in_array($label, $todoLabels)) {
                    $todoLabels .= "<span class='label label-info clickable'>$label</span> ";
                }
            }
            $title .= "<span class='pull-right'>$todoLabels</span>";
        }

        $statusClass = $data->status === "to_do" ? "b-warning" : "b-success";
        $checkboxClass = $data->status === "done" ? "checkbox-checked" : "checkbox-blank";

        $checkStatus = js_anchor("<span class='$checkboxClass'></span>", [
            'title' => '',
            'class' => '',
            'data-id' => $data->id,
            'data-value' => $data->status === "done" ? "to_do" : "done",
            'data-act' => 'update-todo-status-checkbox',
        ]);

        $startDateText = "";
        if (is_date_exists($data->start_date)) {
            $startDateText = format_to_date($data->start_date, false);
            if (get_my_local_time("Y-m-d") > $data->start_date && $data->status !== "done") {
                $startDateText = "<span class='text-danger'>$startDateText</span> ";
            } elseif (get_my_local_time("Y-m-d") == $data->start_date && $data->status !== "done") {
                $startDateText = "<span class='text-warning'>$startDateText</span> ";
            }
        }

        return [
            $statusClass,
            "<i class='hide'>$data->id</i>$checkStatus",
            $title,
            $data->start_date,
            $startDateText,
            modal_anchor(get_uri("todo/modal_form"), "<i class='fa fa-pencil'></i>", [
                'class' => 'edit',
                'title' => lang('edit'),
                'data-post-id' => $data->id,
            ]) . js_anchor("<i class='fa fa-times fa-fw'></i>", [
                'title' => lang('delete'),
                'class' => 'delete',
                'data-id' => $data->id,
                'data-action-url' => get_uri("todo/delete"),
                'data-action' => 'delete',
            ]),
        ];
    }

    public function view()
    {
        // Validate input
        $validationRules = [
            'id' => 'required|numeric',
        ];

        if (!$this->validate($validationRules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $modelInfo = $this->todoModel->find($this->request->getPost('id'));
        $this->validateAccess($modelInfo);

        $viewData['model_info'] = $modelInfo;

        return view('todo/view', $viewData);
    }
}
