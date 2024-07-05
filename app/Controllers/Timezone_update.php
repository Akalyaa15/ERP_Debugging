<?php

namespace App\Controllers;

use App\Models\PostsModel;
use App\Models\UsersModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Timeline extends Controller
{
    use ResponseTrait;

    protected $usersModel;
    protected $postsModel;

    public function __construct()
    {
        $this->usersModel = new UsersModel(); // Example model initialization
        $this->postsModel = new PostsModel(); // Example model initialization

        // Ensure to call the parent constructor
        parent::__construct();
    }

    /* load timeline view */
    public function index()
    {
        $this->check_module_availability("module_timeline");

        $membersOptions = [
            "status" => "active",
            "user_type" => "staff",
            "exclude_user_id" => $this->login_user->id
        ];

        $viewData['team_members'] = $this->usersModel->where($membersOptions)->findAll();

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
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $id = $this->request->getPost('id');
        $filesData = $this->moveFilesFromTempDirToPermanentDir(get_setting("timeline_file_path"), "timeline_post");

        $data = [
            "created_by" => $this->login_user->id,
            "created_at" => get_current_utc_time(),
            "post_id" => $this->request->getPost('post_id'),
            "description" => $this->request->getPost('description'),
            "share_with" => "",
            "files" => $filesData // Assuming $filesData is properly populated
        ];

        $saveId = $this->postsModel->save($data, $id);

        if ($saveId) {
            $data = "";

            if ($this->request->getPost("reload_list")) {
                $options = ["id" => $saveId];
                $viewData['posts'] = $this->postsModel->where($options)->findAll();
                $viewData['result_remaining'] = 0;
                $viewData['is_first_load'] = false;
                $data = view("timeline/post_list", $viewData);
            }

            return $this->respond([
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

        $postInfo = $this->postsModel->find($id);

        // Only allow admin and creator to delete the post
        if (!($this->login_user->is_admin || $postInfo['created_by'] == $this->login_user->id)) {
            return redirect()->to('forbidden');
        }

        // Delete the post and files
        if ($this->postsModel->delete($id) && $postInfo['files']) {
            $timelineFilePath = get_setting("timeline_file_path");
            $files = unserialize($postInfo['files']);

            foreach ($files as $file) {
                $sourcePath = $timelineFilePath . get_array_value($file, "file_name");
                delete_file_from_directory($sourcePath);
            }

            return $this->respond([
                "success" => true,
                "message" => lang('record_deleted')
            ]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

    /* load all replies of a post */
    public function view_post_replies($postId)
    {
        $viewData['reply_list'] = $this->postsModel->where("post_id", $postId)->findAll();

        return view("timeline/reply_list", $viewData);
    }

    /* show post reply form */
    public function post_reply_form($postId)
    {
        $viewData['post_id'] = $postId;

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
        $files = $this->postsModel->find($id)['files'];
        download_app_files(get_setting("timeline_file_path"), $files);
    }

    /* load more posts */
    public function load_more_posts($offset = 0)
    {
        timeline_widget(["limit" => 20, "offset" => $offset]);
    }
}
