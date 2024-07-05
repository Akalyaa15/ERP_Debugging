<?php

namespace App\Controllers;

use App\Models\Messages_model;
use App\Models\Users_model;

class Messages extends BaseController
{
    protected $usersModel;
    protected $messagesModel;

    public function __construct()
    {
        $this->usersModel = new Users_model();
        $this->messagesModel = new Messages_model();
    }

    private function isMyMessage($messageInfo)
    {
        if ($messageInfo->from_user_id == $this->login_user->id || $messageInfo->to_user_id == $this->login_user->id) {
            return true;
        }
    }

    private function checkClientsPermission()
    {
        if ($this->login_user->user_type == "client" && !get_setting("client_message_users")) {
            return redirect()->to(site_url('forbidden'));
        }
    }

    public function index()
    {
        $this->checkClientsPermission();
        return redirect()->to(site_url('messages/inbox'));
    }

    public function modal_form($user_id = 0)
    {
        $clientMessageUsers = get_setting("client_message_users");

        if ($this->login_user->user_type === "staff") {
            $clientMessageUsersArray = explode(",", $clientMessageUsers);
            $users = in_array($this->login_user->id, $clientMessageUsersArray) ?
                $this->usersModel->get_team_members_and_clients("", "", $this->login_user->id)->getResult() :
                $this->usersModel->get_team_members_and_clients("staff", "", $this->login_user->id)->getResult();
        } else {
            if ($clientMessageUsers) {
                $users = $this->usersModel->get_team_members_and_clients("staff", $clientMessageUsers)->getResult();
            } else {
                return redirect()->to(site_url('forbidden'));
            }
        }

        $viewData = [
            'users_dropdown' => ['' => '-']
        ];

        if ($user_id) {
            $viewData['message_user_info'] = $this->usersModel->find($user_id);
        } else {
            foreach ($users as $user) {
                $clientTag = "";
                if ($user->user_type === "client" && $user->company_name) {
                    $clientTag = " - " . lang("client") . ": " . $user->company_name;
                }
                $viewData['users_dropdown'][$user->id] = $user->first_name . " " . $user->last_name . $clientTag;
            }
        }

        return view('messages/modal_form', $viewData);
    }

    public function inbox($auto_select_index = "")
    {
        $this->checkClientsPermission();
        $this->check_module_availability("module_message");

        $viewData = [
            'mode' => 'inbox',
            'auto_select_index' => $auto_select_index
        ];

        return view('messages/index', $viewData);
    }

    public function sent_items($auto_select_index = "")
    {
        $this->checkClientsPermission();
        $this->check_module_availability("module_message");

        $viewData = [
            'mode' => 'sent_items',
            'auto_select_index' => $auto_select_index
        ];

        return view('messages/index', $viewData);
    }

    public function list_data($mode = "inbox")
    {
        $this->checkClientsPermission();

        if ($mode !== "inbox") {
            $mode = "sent_items";
        }

        $options = [
            "user_id" => $this->login_user->id,
            "mode" => $mode
        ];

        $listData = $this->messagesModel->get_list($options)->getResult();
        $result = [];

        foreach ($listData as $data) {
            $result[] = $this->_make_row($data, $mode);
        }

        return $this->response->setJSON(['data' => $result]);
    }

    public function view($message_id = 0, $mode = "", $reply = 0)
    {
        $messageMode = $mode;

        if ($reply == 1 && $mode == "inbox") {
            $messageMode = "sent_items";
        } elseif ($reply == 1 && $mode == "sent_items") {
            $messageMode = "inbox";
        }

        $options = [
            "id" => $message_id,
            "user_id" => $this->login_user->id,
            "mode" => $messageMode
        ];

        $messageInfo = $this->messagesModel->get_details($options)->getRow();

        if (!$this->isMyMessage($messageInfo)) {
            return redirect()->to(site_url('forbidden'));
        }

        // Change message status to read
        $this->messagesModel->set_message_status_as_read($messageInfo->id, $this->login_user->id);

        $repliesOptions = [
            "message_id" => $message_id,
            "user_id" => $this->login_user->id,
            "limit" => 4
        ];

        $messages = $this->messagesModel->get_details($repliesOptions)->getResult();

        $viewData = [
            "message_info" => $messageInfo,
            "replies" => $messages,
            "found_rows" => count($messages),
            "mode" => $mode,
            "is_reply" => $reply
        ];

        return $this->response->setJSON([
            "success" => true,
            "data" => view("messages/view", $viewData),
            "message_id" => $message_id
        ]);
    }

    private function _make_row($data, $mode = "", $return_only_message = false, $online_status = false)
    {
        $imageUrl = get_avatar($data->user_image);
        $createdAt = format_to_relative_time($data->created_at);
        $messageId = $data->main_message_id;
        $label = "";
        $reply = "";
        $status = "";
        $attachmentIcon = "";
        $subject = $data->subject;

        if ($mode == "inbox") {
            $status = $data->status;
        }

        if ($data->reply_subject) {
            $label = " <label class='label label-success inline-block'>" . lang('reply') . "</label>";
            $reply = "1";
            $subject = $data->reply_subject;
        }

        if ($data->files && count(unserialize($data->files))) {
            $attachmentIcon = "<i class='fa fa-paperclip font-16 mr15'></i>";
        }

        $message = "<div class='pull-left message-row $status' data-id='$messageId' data-index='$data->main_message_id' data-reply='$reply'>
                        <div class='media-left'>
                            <span class='avatar avatar-xs'>
                                <img src='$imageUrl' />
                            </span>
                        </div>
                        <div class='media-body'>
                            <div class='media-heading'>
                                <strong> $data->user_name</strong>
                                <span class='text-off pull-right time'>$attachmentIcon $createdAt</span>
                            </div>
                            $label $subject
                        </div>
                    </div>";

        if ($return_only_message) {
            return $message;
        } else {
            return [
                $message,
                $data->created_at,
                $status
            ];
        }
    }
   /* send new message */

   public function send_message()
   {
       // Validate input data
       $validatedData = $this->validate([
           'message' => 'required',
           'to_user_id' => 'required|numeric'
       ]);

       $to_user_id = $validatedData['to_user_id'];

       // Validate client message permission
       $this->validate_client_message($to_user_id);

       // Move files from temp directory to permanent directory
       $target_path = get_setting("timeline_file_path");
       $files_data = move_files_from_temp_dir_to_permanent_dir($target_path, "message");

       // Prepare message data
       $message_data = [
           "from_user_id" => $this->login_user->id,
           "to_user_id" => $to_user_id,
           "subject" => $this->request->getPost('subject'),
           "message" => $this->request->getPost('message'),
           "created_at" => get_current_utc_time(),
           "deleted_by_users" => "",
           "files" => $files_data
       ];

       $message_data = clean_data($message_data);

       // Save message
       $save_id = $this->messagesModel->save($message_data);

       if ($save_id) {
           log_notification("new_message_sent", ["actual_message_id" => $save_id]);
           return $this->response->setJSON([
               "success" => true,
               'message' => lang('message_sent'),
               "id" => $save_id
           ]);
       } else {
           return $this->response->setJSON([
               "success" => false,
               'message' => lang('error_occurred')
           ]);
       }
   }

    //check messages between client and team members.
    private function validate_client_message($to_user_id)
    {
        if ($this->login_user->user_type === "client") {
            $this->check_message_sending_permission($to_user_id);
        } else {
            $to_user_info = $this->usersModel->find($to_user_id);
            if ($to_user_info && $to_user_info->user_type == "client") {
                $this->check_message_sending_permission($this->login_user->id);
            }
        }
    }
    //we have to check permission between clent and team members message.
    private function check_message_sending_permission($user_id)
    {
        $client_message_users = get_setting("client_message_users");
        if (!$client_message_users) {
            return redirect()->to(site_url('forbidden'));
        }

        $client_message_users_array = explode(",", $client_message_users);

        if (!in_array($user_id, $client_message_users_array)) {
            return redirect()->to(site_url('forbidden'));
        }
    }
    /* reply to an existing message */
    public function reply($is_chat = 0)
    {
        $message_id = $this->request->getPost('message_id');

        // Validate input data
        $validatedData = $this->validate([
            'reply_message' => 'required',
            'message_id' => 'required|numeric'
        ]);

        $message_info = $this->messagesModel->find($message_id);

        if (!$this->is_my_message($message_info)) {
            return redirect()->to(site_url('forbidden'));
        }

        // Determine recipient and validate client message permission
        $to_user_id = ($message_info->from_user_id === $this->login_user->id) ? $message_info->to_user_id : $message_info->from_user_id;
        $this->validate_client_message($to_user_id);

        // Move files from temp directory to permanent directory
        $target_path = get_setting("timeline_file_path");
        $files_data = move_files_from_temp_dir_to_permanent_dir($target_path, "message");

        // Prepare reply message data
        $message_data = [
            "from_user_id" => $this->login_user->id,
            "to_user_id" => $to_user_id,
            "message_id" => $message_id,
            "subject" => "",
            "message" => $this->request->getPost('reply_message'),
            "created_at" => get_current_utc_time(),
            "deleted_by_users" => "",
            "files" => $files_data
        ];

        $message_data = clean_data($message_data);

        // Save reply message
        $save_id = $this->messagesModel->save($message_data);

        if ($save_id) {
            // Log notification if user is not online
            if ($this->request->getPost("is_user_online") !== "1") {
                log_notification("message_reply_sent", [
                    "actual_message_id" => $save_id,
                    "parent_message_id" => $message_id
                ]);
            }

            // Clear deleted status if message was deleted
            $this->messagesModel->clear_deleted_status($message_id);

            if ($is_chat) {
                return $this->response->setJSON([
                    "success" => true,
                    'data' => $this->_load_messages($message_id, $this->request->getPost("last_message_id"), 0, true, $to_user_id)
                ]);
            } else {
                $options = ["id" => $save_id, "user_id" => $this->login_user->id];
                $viewData['reply_info'] = $this->messagesModel->get_details($options)->getRow();
                return $this->response->setJSON([
                    "success" => true,
                    'message' => lang('message_sent'),
                    'data' => view("messages/reply_row", $viewData)
                ]);
            }
        }

        return $this->response->setJSON([
            "success" => false,
            'message' => lang('error_occurred')
        ]);
    }
    //load messages right panel when clicking load more button
    public function view_messages()
    {
        $this->check_clients_permission();

        // Validate input data
        $validatedData = $this->validate([
            'message_id' => 'required|numeric',
            'last_message_id' => 'numeric',
            'top_message_id' => 'numeric'
        ]);

        $message_id = $this->request->getPost("message_id");

        $this->_load_more_messages(
            $message_id,
            $this->request->getPost("last_message_id"),
            $this->request->getPost("top_message_id")
        );
    }

    private function _load_more_messages($message_id, $last_message_id, $top_message_id, $load_as_data = false)
    {
        $replies_options = [
            "message_id" => $message_id,
            "last_message_id" => $last_message_id,
            "top_message_id" => $top_message_id,
            "user_id" => $this->login_user->id,
            "limit" => 10
        ];

        $viewData["replies"] = $this->messagesModel->get_details($replies_options)->getResult();
        $viewData["message_id"] = $message_id;

        $this->messagesModel->set_message_status_as_read($message_id, $this->login_user->id);

        return view("messages/reply_rows", $viewData, $load_as_data);
    }
    public function get_notifications()
    {
        // Validate input data
        $validatedData = $this->validate([
            'active_message_id' => 'numeric'
        ]);

        $notifications = $this->messagesModel->get_notifications(
            $this->login_user->id,
            $this->login_user->message_checked_at,
            $this->request->getPost("active_message_id")
        );

        $viewData['notifications'] = $notifications->getResult();

        return $this->response->setJSON([
            "success" => true,
            "active_message_id" => $this->request->getPost("active_message_id"),
            'total_notifications' => $notifications->getNumRows(),
            'notification_list' => view("messages/notifications", $viewData)
        ]);
    }

    public function update_notification_checking_status()
    {
        $now = get_current_utc_time();
        $user_data = ["message_checked_at" => $now];
        $this->usersModel->save($user_data, $this->login_user->id);
    }

    /* upload a file */

    public function upload_file()
    {
        $this->upload_file_to_temp();
    }
    

    /* check valid file for message */

    public function validate_message_file()
{
    return validate_post_file($this->request->getPost("file_name"));
}

    /* download files by zip */

    public function download_message_files($message_id = null)
    {
        $model_info = $this->Messages_model->find($message_id);
    
        if (!$this->is_my_message($model_info)) {
            return redirect()->to("forbidden");
        }
    
        $files = $model_info->files;
        $timeline_file_path = get_setting("timeline_file_path");
        download_app_files($timeline_file_path, $files);
    }
    
    public function delete_my_messages($id = 0)
    {
        if (!$id) {
            return;
        }
    
        // Adjust the model method accordingly based on your CI4 implementation
        $this->Messages_model->delete_messages_for_user($id, $this->login_user->id);
    }
    

    //prepare chat inbox list
    public function chat_list()
    {
        $view_data = [
            'show_users_list' => false,
            'show_clients_list' => false
        ];
    
        $client_message_users = get_setting("client_message_users");
    
        if ($this->login_user->user_type === "staff") {
            $client_message_users_array = explode(",", $client_message_users);
    
            if (in_array($this->login_user->id, $client_message_users_array)) {
                $view_data['show_clients_list'] = true;
            }
    
            $view_data['show_users_list'] = true;
        } else {
            $this->check_clients_permission();
            
            if ($client_message_users) {
                $view_data['show_users_list'] = true;
            }
        }
    
        $options = ['login_user_id' => $this->login_user->id];
        $view_data['messages'] = $this->Messages_model->get_chat_list($options);
    
        return view("messages/chat/tabs", $view_data);
    }
    
    public function users_list($type)
{
    $users = [];
    $client_message_users = get_setting("client_message_users");

    if ($this->login_user->user_type === "staff") {
        $client_message_users_array = explode(",", $client_message_users);

        if (in_array($this->login_user->id, $client_message_users_array) && $type === "client") {
            $users = $this->Users_model->get_team_members_and_clients("client", "", $this->login_user->id);
        } elseif ($type === "staff") {
            $users = $this->Users_model->get_team_members_and_clients("staff", "", $this->login_user->id);
        }
    } elseif ($this->login_user->user_type === "client" && $type === "staff") {
        if ($client_message_users) {
            $users = $this->Users_model->get_team_members_and_clients("staff", $client_message_users);
        }
    }

    $view_data = [
        "users" => $users,
        "page_type" => ($type === "staff") ? "team-members-tab" : "clients-tab"
    ];

    return view("messages/chat/team_members", $view_data);
}


    //load messages in chat view
    public function view_chat()
    {
        $this->check_clients_permission();
    
        $message_id = $this->request->getPost("message_id");
        $another_user_id = $this->request->getPost("another_user_id");
    
        if ($this->request->getPost("is_first_load") == "1") {
            $view_data['first_message'] = $this->Messages_model->get_details(['id' => $message_id, 'user_id' => $this->login_user->id])->getRow();
            return view("messages/chat/message_title", $view_data);
        }
    
        $this->_load_messages($message_id, $this->request->getPost("last_message_id"), $this->request->getPost("top_message_id"), false, $another_user_id);
    }
    

    //prepare the chat box messages 
    private function _load_messages($message_id, $last_message_id, $top_message_id, $load_as_data = false, $another_user_id = "") {

        $replies_options = array("message_id" => $message_id, "last_message_id" => $last_message_id, "top_message_id" => $top_message_id, "user_id" => $this->login_user->id);

        $view_data["replies"] = $this->Messages_model->get_details($replies_options)->result;
        $view_data["message_id"] = $message_id;

        $this->Messages_model->set_message_status_as_read($message_id, $this->login_user->id);

        $is_online = false;
        if ($another_user_id) {
            $last_online = $this->Users_model->get_one($another_user_id)->last_online;
            if ($last_online) {
                $is_online = is_online_user($last_online);
            }
        }

        $view_data['is_online'] = $is_online;

        return $this->load->view("messages/chat/message_items", $view_data, $load_as_data);
    }

    function get_active_chat() {

        validate_submitted_data(array(
            "message_id" => "required|numeric"
        ));

        $message_id = $this->input->post("message_id");

        $options = array("id" => $message_id, "user_id" => $this->login_user->id);
        $view_data["message_info"] = $this->Messages_model->get_details($options)->row;

        if (!$this->is_my_message($view_data["message_info"])) {
            redirect("forbidden");
        }

        //$this->Messages_model->set_message_status_as_read($view_data["message_info"]->id, $this->login_user->id);

        $view_data["message_id"] = $message_id;
        $this->load->view("messages/chat/active_chat", $view_data);
    }

    public function getChatlistOfUser()
    {
        $this->checkClientsPermission();

        $this->validate([
            'user_id' => 'required|numeric'
        ]);

        $userId = $this->request->getPost('user_id');

        $options = [
            'user_id' => $userId,
            'login_user_id' => $this->loginUser['id']
        ];
        $data['messages'] = $this->messagesModel->getChatList($options);

        $userInfo = $this->usersModel->where(['id' => $userId, 'status' => 'active', 'deleted' => '0'])->first();
        $data['user_name'] = $userInfo['first_name'] . ' ' . $userInfo['last_name'];

        $data['user_id'] = $userId;
        $data['tab_type'] = $this->request->getPost('tab_type');

        echo view('messages/chat/get_chatlist_of_user', $data);
    }

/* reply messsage delete in individual */
public function delete($id = 0)
{
    if (!$id) {
        exit();
    }

    $postInfo = $this->messagesModel->find($id);

    // Only admin and creator can delete the post
    if (!($this->loginUser['is_admin'] || $postInfo['from_user_id'] == $this->loginUser['id'])) {
        return redirect()->to('forbidden');
    }

    // Delete the post and files
    if ($this->messagesModel->delete($id) && $postInfo['files']) {
        // Delete the files
        $timelineFilePath = getSetting("timeline_file_path");
        $files = unserialize($postInfo['files']);

        foreach ($files as $file) {
            $sourcePath = $timelineFilePath . $file['file_name'];
            deleteFileFromDirectory($sourcePath);
        }
    }
}
}

/* End of file messages.php */
/* Location: ./application/controllers/messages.php */