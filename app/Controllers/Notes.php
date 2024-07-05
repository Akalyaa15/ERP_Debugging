<?php

namespace App\Controllers;

use App\Models\NotesModel;
use CodeIgniter\Controller;

class Notes extends BaseController {
    protected $notesModel;

    public function __construct() {
        $this->notesModel = new NotesModel();
        helper(['form', 'url']);
        $this->access_only_team_members();
    }

    protected function validate_access_to_note($note_info) {
        if ($note_info->client_id) {
            $access_info = $this->get_access_info("client");
            if ($access_info->access_type != "all") {
                return redirect()->to('forbidden');
            }
        } else if ($note_info->vendor_id) {
            $access_info = $this->get_access_info("vendor");
            if ($access_info->access_type != "all") {
                return redirect()->to('forbidden');
            }
        } else if ($note_info->user_id) {
            return redirect()->to('forbidden');
        } else {
            if ($this->login_user->id !== $note_info->created_by) {
                return redirect()->to('forbidden');
            }
        }
    }

    public function index() {
        $this->check_module_availability("module_note");
        return view('notes/index');
    }

    public function modal_form() {
        $view_data['model_info'] = $this->notesModel->find($this->request->getPost('id'));
        $view_data['project_id'] = $this->request->getPost('project_id') ? $this->request->getPost('project_id') : $view_data['model_info']->project_id;
        $view_data['client_id'] = $this->request->getPost('client_id') ? $this->request->getPost('client_id') : $view_data['model_info']->client_id;
        $view_data['vendor_id'] = $this->request->getPost('vendor_id') ? $this->request->getPost('vendor_id') : $view_data['model_info']->vendor_id;
        $view_data['company_id'] = $this->request->getPost('company_id') ? $this->request->getPost('company_id') : $view_data['model_info']->company_id;
        $view_data['user_id'] = $this->request->getPost('user_id') ? $this->request->getPost('user_id') : $view_data['model_info']->user_id;

        $labels = explode(",", $this->notesModel->get_label_suggestions($this->login_user->id));

        if ($view_data['model_info']->id) {
            $this->validate_access_to_note($view_data['model_info']);
        }

        $label_suggestions = array_unique(array_filter($labels));
        if (empty($label_suggestions)) {
            $label_suggestions = ["0" => "Important"];
        }
        $view_data['label_suggestions'] = $label_suggestions;
        return view('notes/modal_form', $view_data);
    }

    public function save() {
        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'numeric',
            'title' => 'required',
            'project_id' => 'numeric',
            'client_id' => 'numeric',
            'vendor_id' => 'numeric',
            'user_id' => 'numeric'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON(['success' => false, 'message' => $validation->getErrors()]);
        }

        $id = $this->request->getPost('id');
        $target_path = get_setting("timeline_file_path");
        $files_data = move_files_from_temp_dir_to_permanent_dir($target_path, "note");
        $new_files = unserialize($files_data);

        $data = [
            'title' => $this->request->getPost('title'),
            'description' => $this->request->getPost('description'),
            'created_by' => $this->login_user->id,
            'labels' => $this->request->getPost('labels'),
            'project_id' => $this->request->getPost('project_id') ?? 0,
            'client_id' => $this->request->getPost('client_id') ?? 0,
            'vendor_id' => $this->request->getPost('vendor_id') ?? 0,
            'user_id' => $this->request->getPost('user_id') ?? 0,
            'company_id' => $this->request->getPost('company_id') ?? 0,
        ];

        if ($id) {
            $note_info = $this->notesModel->find($id);
            $timeline_file_path = get_setting("timeline_file_path");
            $new_files = update_saved_files($timeline_file_path, $note_info->files, $new_files);
        }

        $data['files'] = serialize($new_files);

        if ($id) {
            $note_info = $this->notesModel->find($id);
            $this->validate_access_to_note($note_info);
        } else {
            $data['created_at'] = gmdate('Y-m-d H:i:s');
        }

        $data = clean_data($data);
        $save_id = $this->notesModel->save($data, $id);

        if ($save_id) {
            return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }
    }

    public function delete() {
        $validation = \Config\Services::validation();
        $validation->setRules(['id' => 'required|numeric']);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON(['success' => false, 'message' => $validation->getErrors()]);
        }

        $id = $this->request->getPost('id');
        $note_info = $this->notesModel->find($id);
        $this->validate_access_to_note($note_info);

        if ($this->notesModel->delete($id)) {
            $file_path = get_setting("timeline_file_path");
            if ($note_info->files) {
                $files = unserialize($note_info->files);
                foreach ($files as $file) {
                    $source_path = $file_path . $file['file_name'];
                    delete_file_from_directory($source_path);
                }
            }
            return $this->response->setJSON(['success' => true, 'message' => lang('record_deleted')]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
        }
    }

    public function list_data($type = "", $id = 0) {
        $options = [];

        if ($type == "project" && $id) {
            $options['created_by'] = $this->login_user->id;
            $options['project_id'] = $id;
        } else if ($type == "client" && $id) {
            $options['client_id'] = $id;
        } else if ($type == "vendor" && $id) {
            $options['vendor_id'] = $id;
        } else if ($type == "user" && $id) {
            $options['user_id'] = $id;
        } else if ($type == "company" && $id) {
            $options['company_id'] = $id;
        } else {
            $options['created_by'] = $this->login_user->id;
            $options['my_notes'] = true;
        }

        $list_data = $this->notesModel->get_details($options)->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        return $this->response->setJSON(['data' => $result]);
    }

    private function _row_data($id) {
        $options = ['id' => $id];
        $data = $this->notesModel->get_details($options)->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data) {
        $title = anchor("notes/view/" . $data->id, $data->title, ["class" => "edit", "title" => lang('note')]);

        $note_labels = "";
        if ($data->labels) {
            $labels = explode(",", $data->labels);
            foreach ($labels as $label) {
                $note_labels .= "<span class='label label-info clickable'>" . $label . "</span> ";
            }
            $title .= "<br />" . $note_labels;
        }

        $files_link = "";
        if ($data->files) {
            $files = unserialize($data->files);
            if (count($files)) {
                foreach ($files as $file) {
                    $file_name = $file['file_name'];
                    $link = " fa fa-" . get_file_icon(strtolower(pathinfo($file_name, PATHINFO_EXTENSION)));
                    $files_link .= anchor("notes/file_preview/" . $file_name, " ", ['title' => "", "class" => "pull-left font-22 mr10 $link", "title" => remove_file_prefix($file_name)]);
                }
            }
        }

        return [
            $data->created_at,
            format_to_relative_time($data->created_at),
            $title,
            $files_link,
            anchor("notes/modal_form", "<i class='fa fa-pencil'></i>", ["class" => "edit", "title" => lang('edit_note'), "data-post-id" => $data->id])
            . anchor("notes/delete", "<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_note'), "class" => "delete", "data-id" => $data->id, "data-action-url" => site_url("notes/delete"), "data-action" => "delete-confirmation"])
        ];
    }

    public function view() {
        $validation = \Config\Services::validation();
        $validation->setRules(['id' => 'required|numeric']);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON(['success' => false, 'message' => $validation->getErrors()]);
        }

        $model_info = $this->notesModel->find($this->request->getPost('id'));
        $this->validate_access_to_note($model_info);

        $view_data['model_info'] = $model_info;
        return view('notes/view', $view_data);
    }

    public function file_preview($file_name = "") {
        if ($file_name) {
            $view_data["file_url"] = get_file_uri(get_setting("timeline_file_path") . $file_name);
            $view_data["is_image_file"] = is_image_file($file_name);
            $view_data["is_google_preview_available"] = is_google_preview_available($file_name);

            return view("notes/file_preview", $view_data);
        } else {
            throw new \CodeIgniter\Exceptions\PageNotFoundException();
        }
    }

    public function upload_file() {
        upload_file_to_temp();
    }

    public function validate_notes_file() {
        return validate_post_file($this->request->getPost("file_name"));
    }
}

/* End of file Notes.php */
/* Location: ./app/Controllers/Notes.php */
