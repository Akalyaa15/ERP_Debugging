<?php

namespace App\Controllers;

use App\Models\AnnouncementsModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class Announcements extends BaseController
{
    protected $announcementsModel;

    public function __construct()
    {
        parent::__construct();
        $this->announcementsModel = new AnnouncementsModel();
        $this->init_permission_checker('announcement');
    }

    // Show announcements list
    public function index()
    {
        $this->check_module_availability('module_announcement');

        $view_data['show_add_button'] = ($this->access_type === 'all');
        $view_data['show_option'] = ($this->access_type === 'all');

        return view('announcements/index', $view_data);
    }

    // Show add/edit announcement form
    public function form($id = 0)
    {
        $this->access_only_allowed_members();

        $view_data['model_info'] = $this->announcementsModel->find($id);
        $view_data['share_with'] = $id ? explode(',', $view_data['model_info']->share_with) : ['all_members'];

        return view('announcements/modal_form', $view_data);
    }

    // Show a specific announcement
    public function view($id = '')
    {
        if ($id) {
            // Show only the allowed announcement
            $options = ['id' => $id];
            $options = $this->_prepare_access_options($options);

            $announcement = $this->announcementsModel->where($options)->first();
            if ($announcement) {
                $view_data['announcement'] = $announcement;

                // Mark the announcement as read for logged-in user
                $this->announcementsModel->mark_as_read($id, $this->login_user->id);
                return view('announcements/view', $view_data);
            }
        }

        // Not matched the requirement. Show 404 page
        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }

    private function _prepare_access_options($options)
    {
        if ($this->access_type !== 'all') {
            if ($this->login_user->user_type === 'staff') {
                $options['share_with'] = 'all_members';
            } elseif ($this->login_user->user_type === 'resource') {
                $options['share_with'] = 'all_resource';
            } elseif ($this->login_user->partner_id) {
                $options['share_with'] = 'all_partners';
            } elseif ($this->login_user->user_type === 'client') {
                $options['share_with'] = 'all_clients';
            } elseif ($this->login_user->user_type === 'vendor') {
                $options['share_with'] = 'all_vendors';
            } else {
                $options['share_with'] = 'none';
            }
        }
        return $options;
    }

    // Mark the announcement as read for logged-in user
    public function mark_as_read($id)
    {
        $this->announcementsModel->mark_as_read($id, $this->login_user->id);
    }

    // Add/edit an announcement
    public function save()
    {
        $this->access_only_allowed_members();

        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'numeric',
            'title' => 'required',
            'start_date' => 'required',
            'end_date' => 'required'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return redirect()->back()->with('error_message', $validation->getErrors());
        }

        $id = $this->request->getPost('id');

        $target_path = get_setting('timeline_file_path');
        $files_data = $this->move_files_from_temp_dir_to_permanent_dir($target_path, 'announcement');
        $new_files = unserialize($files_data);

        $data = [
            'title' => $this->request->getPost('title'),
            'description' => decode_ajax_post_data($this->request->getPost('description')),
            'start_date' => $this->request->getPost('start_date'),
            'end_date' => $this->request->getPost('end_date'),
            'created_by' => $this->login_user->id,
            'created_at' => date('Y-m-d H:i:s'),
            'share_with' => $this->request->getPost('share_with') ? implode(',', $this->request->getPost('share_with')) : ''
        ];

        // If editing, update the files if required
        if ($id) {
            $announcement_info = $this->announcementsModel->find($id);
            $timeline_file_path = get_setting('timeline_file_path');

            $new_files = $this->update_saved_files($timeline_file_path, $announcement_info['files'], $new_files);
        }

        $data['files'] = serialize($new_files);

        if (!$id) {
            $data['read_by'] = 0; // Set default value
        }

        $save_id = $this->announcementsModel->save($data);

        // Send log notification
        if (!$id) {
            if ($data['share_with']) {
                log_notification('new_announcement_created', ['announcement_id' => $save_id]);
            }
        }

        if ($save_id) {
            return redirect()->to(site_url('announcements/form/' . $save_id));
        } else {
            return redirect()->to(site_url('announcements/form/'))->with('error_message', lang('error_occurred'));
        }
    }

    // Upload a file
    public function upload_file()
    {
        $this->access_only_allowed_members();

        $this->upload_file_to_temp();
    }

    // Check valid file for announcement
    public function validate_announcement_file()
    {
        return $this->validate_post_file($this->request->getPost('file_name'));
    }

    // Download files
    public function download_announcement_files($id = 0)
    {
        $options = ['id' => $id];
        $options = $this->_prepare_access_options($options);

        $info = $this->announcementsModel->where($options)->first();

        $this->download_app_files(get_setting('timeline_file_path'), $info['files']);
    }

    // Delete/undo an announcement
    public function delete()
    {
        $this->access_only_allowed_members();

        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'required|numeric'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
        }

        $id = $this->request->getPost('id');
        if ($this->request->getPost('undo')) {
            if ($this->announcementsModel->delete($id, true)) {
                return $this->response->setJSON(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_undone')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($this->announcementsModel->delete($id)) {
                return $this->response->setJSON(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }

    // Prepare the list data for announcement list
    public function list_data()
    {
        // Show only the allowed announcements
        $options = [];
        if ($this->access_type !== 'all') {
            if ($this->login_user->user_type === 'staff') {
                $options['share_with'] = 'all_members';
            } elseif ($this->login_user->user_type === 'resource') {
                $options['share_with'] = 'all_resource';
            } elseif ($this->login_user->partner_id) {
                $options['share_with'] = 'all_partners';
            } elseif ($this->login_user->user_type === 'client') {
                $options['share_with'] = 'all_clients';
            } elseif ($this->login_user->user_type === 'vendor') {
                $options['share_with'] = 'all_vendors';
            } else {
                $options['share_with'] = 'none';
            }
        }

        $list_data = $this->announcementsModel->where($options)->findAll();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        return $this->response->setJSON(['data' => $result]);
    }

    // Get a row of announcement list row
    private function _row_data($id)
    {
        $options = ['id' => $id];
        $data = $this->announcementsModel->where($options)->first();
        return $this->_make_row($data);
    }

    // Make a row of announcement list
    private function _make_row($data)
    {
        $image_url = get_avatar($data['created_by_avatar']);
        $user = "<span class='avatar avatar-xs mr10'><img src='$image_url' alt=''></span> {$data['created_by_user']}";
        $option = '';
        if ($this->access_type === 'all') {
            $option = anchor(site_url('announcements/form/' . $data['id']), "<i class='fa fa-pencil'></i>", ['class' => 'edit', 'title' => lang('edit_announcement')])
                    . js_anchor("<i class='fa fa-times fa-fw'></i>", ['title' => lang('delete_announcement'), 'class' => 'delete', 'data-id' => $data['id'], 'data-action-url' => site_url('announcements/delete'), 'data-action' => 'delete']);
        }
        return [
            anchor(site_url('announcements/view/' . $data['id']), $data['title'], ['class' => '', 'title' => lang('view')]),
            get_team_member_profile_link($data['created_by'], $user),
            $data['start_date'],
            format_to_date($data['start_date'], false),
            $data['end_date'],
            format_to_date($data['end_date'], false),
            $option
        ];
    }
}


/* End of file announcements.php */
/* Location: ./application/controllers/announcements.php */