<?php

namespace App\Controllers;

use App\Models\PostsModel;
use App\Models\UsersModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;

class Timeline extends ResourceController
{
    protected $usersmodel;
    protected $postsmodel;

    public function __construct()
    {
        $this->usersmodel = new UsersModel(); // Example model initialization
        $this->postsmodel = new PostsModel(); // Example model initialization

        // Ensure to call the parent constructor
        parent::__construct();

        // Apply access control or authentication checks
        $this->access_only_team_members();
    }

    /* load timeline view */
    public function index()
    {
        $this->check_module_availability("module_timeline");

        $members_options = [
            "status" => "active",
            "user_type" => "staff",
            "exclude_user_id" => $this->login_user->id
        ];

        $viewData['team_members'] = $this->usersmodel->where($members_options)->findAll();

        return view('timeline/index', $viewData);
    }

    /* save a post */
    public function save()
    {
        // Validate input
        $validationRules = [
            'description' => 'required'
        ];

        if (!$this->validate($validationRules)) {
            return $this->fail($this->validator->getErrors());
        }

        $id = $this->request->getPost('id');
        $files_data = $this->move_files_from_temp_dir_to_permanent_dir(get_setting("timeline_file_path"), "timeline_post");

        $data = [
            "created_by" => $this->login_user->id,
            "created_at" => get_current_utc_time(),
            "post_id" => $this->request->getPost('post_id'),
            "description" => $this->request->getPost('description'),
            "share_with" => "",
            "files" => $files_data // Assuming $files_data is properly populated
        ];

        $save_id = $this->postsmodel->save($data, $id);

        if ($save_id) {
            $data = "";

            if ($this->request->getPost("reload_list")) {
                $options = ["id" => $save_id];
                $viewData['posts'] = $this->postsmodel->where($options)->findAll();
                $viewData['result_remaining'] = 0;
                $viewData['is_first_load'] = false;
                $data = view("timeline/post_list", $viewData);
            }

            return $this->response->setJSON([
                "success" => true,
                "data" => $data,
                "message" => lang('comment_submited')
            ]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

    public function delete($id = null)
    {
        if (!$id) {
            return $this->fail('Invalid post ID');
        }

        $post_info = $this->postsmodel->find($id);

        // Only allow admin and creator to delete the post
        if (!($this->login_user->is_admin || $post_info['created_by'] == $this->login_user->id)) {
            return redirect()->to('forbidden');
        }

        // Delete the post and files
        if ($this->postsmodel->delete($id) && $post_info['files']) {
            $timeline_file_path = get_setting("timeline_file_path");
            $files = unserialize($post_info['files']);

            foreach ($files as $file) {
                $source_path = $timeline_file_path . get_array_value($file, "file_name");
                delete_file_from_directory($source_path);
            }

            return $this->response->setJSON([
                "success" => true,
                "message" => lang('record_deleted')
            ]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

    /* load all replies of a post */
    public function view_post_replies($post_id)
    {
        $viewData['reply_list'] = $this->postsmodel->where("post_id", $post_id)->findAll();

        return view("timeline/reply_list", $viewData);
    }

    /* show post reply form */
    public function post_reply_form($post_id)
    {
        $viewData['post_id'] = $post_id;

        return view("timeline/reply_form", $viewData);
    }

    /* upload a post file */
    public function upload_file()
    {
        upload_file_to_temp();
    }

    /* check valid file for post */
    public function validate_post_file()
    {
        return validate_post_file($this->request->getPost("file_name"));
    }

    public function download_files($id)
    {
        $files = $this->postsmodel->find($id)['files'];
        download_app_files(get_setting("timeline_file_path"), $files);
    }

    /* load more posts */
    public function load_more_posts($offset = 0)
    {
        timeline_widget(["limit" => 20, "offset" => $offset]);
    }
}
