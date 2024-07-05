<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;

class Messages extends BaseController
{
    protected $usersModel;
    protected $messagesModel;

    public function __construct()
    {
        $this->usersModel = new \App\Models\Users_model(); // Replace with your actual model path
        $this->messagesModel = new \App\Models\Messages_model(); // Adjust as per your model name
    }

    private function is_my_message($message_info)
    {
        if ($message_info->from_user_id == $this->login_user->id || $message_info->to_user_id == $this->login_user->id) {
            return true;
        }
    }

    private function check_clients_permission()
    {
        if ($this->login_user->user_type == "client" && !get_setting("client_message_users")) {
            return redirect()->to("forbidden");
        }
    }

    public function index()
    {
        $this->check_clients_permission();
        return redirect()->to("messages/inbox");
    }

    public function modal_form($user_id = 0)
    {
        $client_message_users = get_setting("client_message_users");
        if ($this->login_user->user_type === "staff") {
            // User is team member
            $client_message_users_array = explode(",", $client_message_users);
            if (in_array($this->login_user->id, $client_message_users_array)) {
                // User can send message to clients
                $users = $this->usersModel->get_team_members_and_clients("", "", $this->login_user->id)->getResult();
            } else {
                // User can send message only to team members
                $users = $this->usersModel->get_team_members_and_clients("staff", "", $this->login_user->id)->getResult();
            }
        } else {
            // User is a client contact
            if ($client_message_users) {
                $users = $this->usersModel->get_team_members_and_clients("staff", $client_message_users)->getResult();
            } else {
                // Client can't send message to any team members
                return redirect()->to("forbidden");
            }
        }

        $view_data = [
            'users_dropdown' => [],
        ];

        if ($user_id) {
            $view_data['message_user_info'] = $this->usersModel->find($user_id);
        } else {
            foreach ($users as $user) {
                $client_tag = "";
                if ($user->user_type === "client" && $user->company_name) {
                    $client_tag = " - " . lang("client") . ": " . $user->company_name;
                }
                $view_data['users_dropdown'][] = [
                    "id" => $user->id,
                    "text" => $user->first_name . " " . $user->last_name . " " . $client_tag,
                ];
            }
        }

        return view('messages/modal_form', $view_data);
    }
     /* show forard message modal */

     public function forward_modal_form($user_id = 0)
     {
         $client_message_users = get_setting("client_message_users");
         if ($this->login_user->user_type === "staff") {
             // User is team member
             $client_message_users_array = explode(",", $client_message_users);
             if (in_array($this->login_user->id, $client_message_users_array)) {
                 // User can send message to clients
                 $users = $this->usersModel->get_team_members_and_clients("", "", $this->login_user->id);
             } else {
                 // User can send message only to team members
                 $users = $this->usersModel->get_team_members_and_clients("staff", "", $this->login_user->id);
             }
         } else {
             // User is a client contact
             if ($client_message_users) {
                 $users = $this->usersModel->get_team_members_and_clients("staff", $client_message_users);
             } else {
                 // Client can't send message to any team members
                 return redirect()->to("forbidden");
             }
         }
 
         $reply_info_id = $this->request->getPost('reply_info_id');
         $message_info_id = $this->request->getPost('message_info_id');
 
         $view_data = [
             'reply_info_id' => $reply_info_id,
             'message_info_id' => $message_info_id,
             'users_dropdown' => [],
             'model_info' => new \stdClass(),
         ];
 
         if ($reply_info_id) {
             $estimate_info = $this->messagesModel->find($reply_info_id);
             $message_info_list = $this->messagesModel->find($estimate_info->message_id);
             $view_data['model_info']->message = $estimate_info->message;
             $view_data['model_info']->subject = $message_info_list->subject;
             $view_data['model_info']->files = $estimate_info->files;
             $view_data['model_info']->message_id = $estimate_info->id;
         } else if ($message_info_id) {
             $estimate_info = $this->messagesModel->find($message_info_id);
             $view_data['model_info']->message = $estimate_info->message;
             $view_data['model_info']->subject = $estimate_info->subject;
             $view_data['model_info']->files = $estimate_info->files;
             $view_data['model_info']->message_id = $estimate_info->id;
         }
 
         foreach ($users->getResult() as $user) {
             $client_tag = ($user->user_type === "client" && $user->company_name) ? " - " . lang("client") . ": " . $user->company_name : "";
             $view_data['users_dropdown'][] = [
                 "id" => $user->id,
                 "text" => $user->first_name . " " . $user->last_name . " " . $client_tag,
             ];
         }
 
         return view('messages/forward_modal_form', $view_data);
     }

    /* show inbox */

    public function inbox($auto_select_index = "")
    {
        $this->check_clients_permission();
        $this->check_module_availability("module_message");
    
        $view_data = [
            'mode' => "inbox",
            'auto_select_index' => $auto_select_index
        ];
    
        return view("messages/index", $view_data);
    }
    
    /* show sent items */

    public function sent_items($auto_select_index = "")
    {
        $this->check_clients_permission();
        $this->check_module_availability("module_message");
    
        $view_data = [
            'mode' => "sent_items",
            'auto_select_index' => $auto_select_index
        ];
    
        return view("messages/index", $view_data);
    }
    
    /* list of messages, prepared for datatable  */

    public function list_data($mode = "inbox")
    {
        $this->check_clients_permission();
    
        if ($mode !== "inbox") {
            $mode = "sent_items";
        }
    
        $options = [
            "user_id" => $this->login_user->id,
            "mode" => $mode
        ];
        $list_data = $this->messagesModel->get_list($options)->getResult();
    
        $result = [];
    
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data, $mode);
        }
    
        return $this->response->setJSON(["data" => $result]);
    }
    

    /* return a message details */

    public function view($message_id = 0, $mode = "", $reply = 0)
{
    $message_mode = $mode;

    if ($reply == 1 && $mode == "inbox") {
        $message_mode = "sent_items";
    } elseif ($reply == 1 && $mode == "sent_items") {
        $message_mode = "inbox";
    }

    $options = [
        "id" => $message_id,
        "user_id" => $this->login_user->id,
        "mode" => $message_mode
    ];

    $message_info = $this->messagesModel->get_details($options)->getRow();

    if (!$this->is_my_message($message_info)) {
        return redirect()->to("forbidden");
    }

    // Change message status to read
    $this->messagesModel->set_message_status_as_read($message_info->id, $this->login_user->id);

    $replies_options = [
        "message_id" => $message_id,
        "user_id" => $this->login_user->id,
        "limit" => 4
    ];

    $messages = $this->messagesModel->get_details($replies_options);

    $view_data = [
        "message_info" => $message_info,
        "replies" => $messages->getResult(),
        "found_rows" => $messages->numRows(),
        "mode" => $mode,
        "is_reply" => $reply
    ];

    return $this->response->setJSON([
        "success" => true,
        "data" => view("messages/view", $view_data)->render(),
        "message_id" => $message_id
    ]);
}

    /* prepare a row of message list table */

    private function _make_row($data, $mode = "", $return_only_message = false, $online_status = false)
{
    $image_url = get_avatar($data->user_image);
    $created_at = format_to_relative_time($data->created_at);
    $message_id = $data->main_message_id;
    $label = "";
    $reply = "";
    $status = "";
    $attachment_icon = "";
    $subject = $data->subject;

    if ($mode == "inbox") {
        $status = $data->status;
    }

    if ($data->reply_subject) {
        $label = "<label class='label label-success inline-block'>" . lang('reply') . "</label>";
        $reply = "1";
        $subject = $data->reply_subject;
    }

    if ($data->files && count(unserialize($data->files))) {
        $attachment_icon = "<i class='fa fa-paperclip font-16 mr15'></i>";
    }

    $online = "";
    if ($online_status && is_online_user($data->last_online)) {
        $online = "<i class='online'></i>";
    }

    $message = "<div class='pull-left message-row $status' data-id='$message_id' data-index='$data->main_message_id' data-reply='$reply'><div class='media-left'>
                    <span class='avatar avatar-xs'>
                        <img src='$image_url' />
                        $online
                    </span>
                </div>
                <div class='media-body'>
                    <div class='media-heading'>
                        <strong> $data->user_name</strong>
                        <span class='text-off pull-right time'>$attachment_icon $created_at</span>
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
        $validationRules = [
            'message' => 'required',
            'to_user_id' => 'required'
        ];
    
        if (!$this->validate($validationRules)) {
            echo json_encode(['success' => false, 'message' => lang('error_occurred')]);
            return;
        }
    
        $to_user_id = $this->request->getPost('to_user_id');
        $target_path = get_setting("timeline_file_path");
        $files_data = move_files_from_temp_dir_to_permanent_dir($target_path, "message");
    
        $multiple_users = explode(",", $to_user_id);
        foreach ($multiple_users as $user_id) {
            $message_data = [
                'from_user_id' => $this->login_user->id,
                'to_user_id' => $user_id,
                'subject' => $this->request->getPost('subject'),
                'message' => $this->request->getPost('message'),
                'created_at' => date('Y-m-d H:i:s'), // Ensure to adjust as per your timezone
                'deleted_by_users' => ''
            ];
    
            $message_data = clean_data($message_data);
            $message_data['files'] = $files_data;
    
            $save_id = $this->Messages_model->save($message_data);
        }
    
        if ($save_id) {
            log_notification("new_message_sent", ["actual_message_id" => $save_id]);
            echo json_encode(['success' => true, 'message' => lang('message_sent'), 'id' => $save_id]);
        } else {
            echo json_encode(['success' => false, 'message' => lang('error_occurred')]);
        }
    }
    
    //check messages between client and team members.
   // Check messages between client and team members
private function validate_client_message($to_user_id)
{
    if ($this->login_user->user_type === "client") {
        // Sending message from client
        $this->check_message_sending_permission($to_user_id);
    } else {
        // From team member to client messages, check who can communicate with client
        $to_user_info = $this->Users_model->find($to_user_id);
        if ($to_user_info && $to_user_info->user_type == "client") {
            // Sending message from team members to client. Check the permission
            $this->check_message_sending_permission($this->login_user->id);
        }
    }
}

    //we have to check permission between clent and team members message.
   // Check permission between client and team members message
private function check_message_sending_permission($user_id)
{
    $client_message_users = get_setting("client_message_users");
    if (!$client_message_users) {
        return redirect()->to("forbidden");
    }

    $client_message_users_array = explode(",", $client_message_users);

    if (!in_array($user_id, $client_message_users_array)) {
        return redirect()->to("forbidden");
    }
}


/*send  forward message  */
// Send forward message
public function forward_send_message()
{
    $validationRules = [
        'message' => 'required',
        'to_user_id' => 'required'
    ];

    if (!$this->validate($validationRules)) {
        echo json_encode(['success' => false, 'message' => lang('error_occurred')]);
        return;
    }

    $to_user_id = $this->request->getPost('to_user_id');
    $target_path = get_setting("timeline_file_path");
    $files_data = move_files_from_temp_dir_to_permanent_dir($target_path, "message");

    $multiple_users = explode(",", $to_user_id);
    foreach ($multiple_users as $user_id) {
        $new_files = unserialize($files_data);
        $message_data = [
            'from_user_id' => $this->login_user->id,
            'to_user_id' => $user_id,
            'subject' => $this->request->getPost('subject'),
            'message' => $this->request->getPost('message'),
            'created_at' => date('Y-m-d H:i:s'), // Ensure to adjust as per your timezone
            'deleted_by_users' => ''
        ];

        $message_data = clean_data($message_data);
        
        $message_id = $this->request->getPost('forward_files');
        $message_id_info = $this->Messages_model->find($message_id);
        $timeline_file_path = get_setting("timeline_file_path");
        $new_files = update_saved_files($timeline_file_path, $message_id_info->files, $new_files);
        $message_data['files'] = serialize($new_files);

        $save_id = $this->Messages_model->save($message_data);
    }

    if ($save_id) {
        log_notification("new_message_sent", ["actual_message_id" => $save_id]);
        echo json_encode(['success' => true, 'message' => lang('message_sent'), 'id' => $save_id]);
    } else {
        echo json_encode(['success' => false, 'message' => lang('error_occurred')]);
    }
}
    /* reply to an existing message */
    public function reply($is_chat = 0)
    {
        $message_id = $this->request->getPost('message_id');
    
        $validationRules = [
            'reply_message' => 'required',
            'message_id' => 'required|numeric'
        ];
    
        if (!$this->validate($validationRules)) {
            echo json_encode(['success' => false, 'message' => lang('error_occurred')]);
            return;
        }
    
        $message_info = $this->Messages_model->find($message_id);
    
        if (!$this->is_my_message($message_info)) {
            return redirect()->to("forbidden");
        }
    
        // Determine recipient based on message direction
        $to_user_id = ($message_info->from_user_id === $this->login_user->id) ? $message_info->to_user_id : $message_info->from_user_id;
    
        $this->validate_client_message($to_user_id);
    
        $target_path = get_setting("timeline_file_path");
        $files_data = move_files_from_temp_dir_to_permanent_dir($target_path, "message");
    
        $message_data = [
            'from_user_id' => $this->login_user->id,
            'to_user_id' => $to_user_id,
            'message_id' => $message_id,
            'subject' => "",
            'message' => $this->request->getPost('reply_message'),
            'created_at' => date('Y-m-d H:i:s'), // Adjust as per your timezone
            'deleted_by_users' => ''
        ];
    
        $message_data = clean_data($message_data);
        $message_data['files'] = $files_data; // Don't clean serialized data
    
        $save_id = $this->Messages_model->save($message_data);
    
        if ($save_id) {
            // Send notification if user is not online
            if ($this->request->getPost("is_user_online") !== "1") {
                log_notification("message_reply_sent", ["actual_message_id" => $save_id, "parent_message_id" => $message_id]);
            }
    
            // Clear delete status if the mail was deleted
            $this->Messages_model->clear_deleted_status($message_id);
    
            if ($is_chat) {
                echo json_encode(['success' => true, 'data' => $this->_load_messages($message_id, $this->request->getPost("last_message_id"), 0, true, $to_user_id)]);
            } else {
                $options = ['id' => $save_id, 'user_id' => $this->login_user->id];
                $view_data['reply_info'] = $this->Messages_model->get_details($options)->getRow();
                echo json_encode(['success' => true, 'message' => lang('message_sent'), 'data' => view("messages/reply_row", $view_data)]);
            }
            return;
        }
    
        echo json_encode(['success' => false, 'message' => lang('error_occurred')]);
    }
    

    //load messages right panel when clicking load more button
    public function view_messages()
    {
        $this->check_clients_permission();
    
        $validationRules = [
            'message_id' => 'required|numeric',
            'last_message_id' => 'numeric',
            'top_message_id' => 'numeric'
        ];
    
        if (!$this->validate($validationRules)) {
            return;
        }
    
        $message_id = $this->request->getPost("message_id");
    
        $this->_load_more_messages($message_id, $this->request->getPost("last_message_id"), $this->request->getPost("top_message_id"));
    }

private function _load_more_messages($message_id, $last_message_id, $top_message_id, $load_as_data = false) {
    $replies_options = [
        "message_id" => $message_id,
        "last_message_id" => $last_message_id,
        "top_message_id" => $top_message_id,
        "user_id" => $this->login_user->id,
        "limit" => 10
    ];

    $view_data["replies"] = $this->Messages_model->getDetails($replies_options);
    $view_data["message_id"] = $message_id;

    $this->Messages_model->setMessageStatusAsRead($message_id, $this->login_user->id);

    return view("messages/reply_rows", $view_data);
}


    /* prepare notifications */

  // CodeIgniter 4 equivalent
public function getNotifications() {
    $active_message_id = $this->request->getPost("active_message_id");
    $notifications = $this->Messages_model->getNotifications(
        $this->login_user->id,
        $this->login_user->message_checked_at,
        $active_message_id
    );

    $view_data['notifications'] = $notifications;
    return json_encode([
        "success" => true,
        "active_message_id" => $active_message_id,
        'total_notifications' => count($notifications),
        'notification_list' => view("messages/notifications", $view_data)
    ]);
}

public function updateNotificationCheckingStatus() {
    $now = date("Y-m-d H:i:s");
    $user_data = ["message_checked_at" => $now];
    $this->Users_model->save($user_data, $this->login_user->id);
}

public function getGNotifications() {
    $active_message_id = $this->request->getPost("active_message_id");
    $notifications = $this->Messages_model->getGNotifications(
        $this->login_user->id,
        $this->login_user->g_message_checked_at,
        $active_message_id
    );

    $view_data['notifications'] = $notifications;
    return json_encode([
        "success" => true,
        "active_message_id" => $active_message_id,
        'total_notifications' => count($notifications),
        'notification_list' => view("messages/notifications", $view_data)
    ]);
}

public function updateNotificationCheckingStatuss() {
    $now = date("Y-m-d H:i:s");
    $user_data = ["g_message_checked_at" => $now];
    $this->Users_model->save($user_data, $this->login_user->id);
}

    /* upload a file */

public function uploadFile() {
    uploadFileToTemp();
}

public function validateMessageFile() {
    return validatePostFile($this->request->getPost("file_name"));
}

public function downloadMessageFiles($message_id = "") {
    $model_info = $this->Messages_model->getOne($message_id);
    if (!$this->isMyMessage($model_info)) {
        return redirect()->to("forbidden");
    }

    $files = $model_info->files;
    $timeline_file_path = getSetting("timeline_file_path");
    downloadAppFiles($timeline_file_path, $files);
}


public function deleteMyMessages($id = 0) {
    if (!$id) {
        exit();
    }

    // Delete messages for the current user
    $this->Messages_model->deleteMessagesForUser($id, $this->login_user->id);
}

    //prepare chat inbox list
   // CodeIgniter 4 equivalent
public function chatList() {
    $viewData['show_users_list'] = false;
    $viewData['show_clients_list'] = false;

    $clientMessageUsers = getSetting("client_message_users");
    if ($this->login_user->user_type === "staff") {
        // User is a team member
        $clientMessageUsersArray = explode(",", $clientMessageUsers);
        if (in_array($this->login_user->id, $clientMessageUsersArray)) {
            // User can send message to clients
            $viewData['show_clients_list'] = true;
        }

        // User can send message to team members
        $viewData['show_users_list'] = true;
    } else {
        $this->checkClientsPermission();
        // User is a client contact and can send messages
        if ($clientMessageUsers) {
            $viewData['show_users_list'] = true;
        }
    }

    $options = ["login_user_id" => $this->login_user->id];

    $viewData['messages'] = $this->Messages_model->getChatList($options);

    echo view("messages/chat/tabs", $viewData);
}
   // CodeIgniter 4 equivalent
public function usersList($type) {
    $users = [];
    $clientMessageUsers = getSetting("client_message_users");

    if ($this->login_user->user_type === "staff") {
        // User is a team member
        $clientMessageUsersArray = explode(",", $clientMessageUsers);

        if (in_array($this->login_user->id, $clientMessageUsersArray) && $type === "client") {
            // User can send message to clients
            $users = $this->Users_model->getTeamMembersAndClients("client", "", $this->login_user->id);
        } else if ($type === "staff") {
            // User can send message only to team members
            $users = $this->Users_model->getTeamMembersAndClients("staff", "", $this->login_user->id);
        }
    } else if ($this->login_user->user_type === "client" && $type === "staff") {
        // User is a client contact
        if ($clientMessageUsers) {
            $users = $this->Users_model->getTeamMembersAndClients("staff", $clientMessageUsers);
        }
    }

    $viewData["users"] = $users;

    $pageType = ($type === "staff") ? "team-members-tab" : "clients-tab";
    $viewData["page_type"] = $pageType;

    echo view("messages/chat/team_members", $viewData);
}

  // CodeIgniter 4 equivalent
public function viewChat() {
    $this->checkClientsPermission();

    $rules = [
        "message_id" => "required|numeric",
        "last_message_id" => "permit_empty|numeric",
        "top_message_id" => "permit_empty|numeric",
        "another_user_id" => "permit_empty|numeric"
    ];

    if (!$this->validate($rules)) {
        return redirect()->to('forbidden');
    }

    $messageId = $this->request->getPost("message_id");
    $anotherUserId = $this->request->getPost("another_user_id");

    if ($this->request->getPost("is_first_load") == "1") {
        $viewData["first_message"] = $this->Messages_model->getDetails(["id" => $messageId, "user_id" => $this->login_user->id]);
        echo view("messages/chat/message_title", $viewData);
    }

    $this->_loadMessages($messageId, $this->request->getPost("last_message_id"), $this->request->getPost("top_message_id"), false, $anotherUserId);
}

private function _loadMessages($messageId, $lastMessageId, $topMessageId, $loadAsData = false, $anotherUserId = "") {
    $repliesOptions = [
        "message_id" => $messageId,
        "last_message_id" => $lastMessageId,
        "top_message_id" => $topMessageId,
        "user_id" => $this->login_user->id
    ];

    $viewData["replies"] = $this->Messages_model->getDetails($repliesOptions);
    $viewData["message_id"] = $messageId;

    $this->Messages_model->setMessageStatusAsRead($messageId, $this->login_user->id);

    $isOnline = false;
    if ($anotherUserId) {
        $lastOnline = $this->Users_model->getOne($anotherUserId)->last_online;
        if ($lastOnline) {
            $isOnline = isOnlineUser($lastOnline);
        }
    }

    $viewData['is_online'] = $isOnline;

    echo view("messages/chat/message_items", $viewData, $loadAsData);
}
public function get_active_chat()
{
    helper(['form', 'url']);

    $validation = \Config\Services::validation();
    $validation->setRules([
        'message_id' => 'required|numeric'
    ]);

    if (!$validation->withRequest($this->request)->run()) {
        return redirect()->to(site_url('forbidden'));
    }

    $message_id = $this->request->getPost("message_id");

    $options = ['id' => $message_id, 'user_id' => $this->login_user->id];
    $message_info = (new Messages_model())->getDetails($options)->getRow();

    if (!$this->is_my_message($message_info)) {
        return redirect()->to(site_url('forbidden'));
    }

    // Uncomment this line to set message status as read
    // $this->Messages_model->setMessageStatusAsRead($message_info->id, $this->login_user->id);

    $data = [
        'message_id' => $message_id,
        'message_info' => $message_info
    ];

    return view('messages/chat/active_chat', $data);
}


public function get_chatlist_of_user()
{
    $this->check_clients_permission();

    $validation = \Config\Services::validation();
    $validation->setRules([
        'user_id' => 'required|numeric'
    ]);

    if (!$validation->withRequest($this->request)->run()) {
        return redirect()->to(site_url('forbidden'));
    }

    $user_id = $this->request->getPost("user_id");

    $options = ['user_id' => $user_id, 'login_user_id' => $this->login_user->id];
    $messages = (new Messages_model())->getChatList($options)->getResult();

    $user_info = (new Users_model())->getOneWhere(['id' => $user_id, 'status' => 'active', 'deleted' => '0']);
    $user_name = $user_info->first_name . ' ' . $user_info->last_name;

    $data = [
        'messages' => $messages,
        'user_name' => $user_name,
        'user_id' => $user_id,
        'tab_type' => $this->request->getPost("tab_type")
    ];

    return view('messages/chat/get_chatlist_of_user', $data);
}


public function delete($id = 0)
{
    if (!$id) {
        exit();
    }

    $post_info = (new Messages_model())->getOne($id);

    if (!($this->login_user->is_admin || $post_info->from_user_id == $this->login_user->id)) {
        return redirect()->to(site_url('forbidden'));
    }

    if ((new Messages_model())->delete($id) && $post_info->files) {
        // Uncomment the following code to delete associated files
        /*
        $timeline_file_path = get_setting("timeline_file_path");
        $files = unserialize($post_info->files);

        foreach ($files as $file) {
            $source_path = $timeline_file_path . get_array_value($file, "file_name");
            delete_file_from_directory($source_path);
        }
        */
    }
}

public function groups_items($auto_select_index = "")
    {
        $this->check_module_availability("module_message");

        $data = [
            'mode' => 'group_items',
            'auto_select_index' => $auto_select_index
        ];

        return view('messages/groups/index', $data);
    }
    public function group_modal_form()
    {
        helper(['form']);

        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'numeric'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return redirect()->to(site_url('forbidden'));
        }

        $team_members = (new UsersModel())->where(['deleted' => 0, 'user_type' => 'staff', 'status' => 'active'])->findAll();
        $members_dropdown = [];

        foreach ($team_members as $team_member) {
            $members_dropdown[] = [
                'id' => $team_member['id'],
                'text' => $team_member['first_name'] . ' ' . $team_member['last_name']
            ];
        }

        $data = [
            'members_dropdown' => json_encode($members_dropdown),
            'model_info' => (new GroupsModel())->find($this->request->getPost('id'))
        ];

        return view('messages/groups/modal_form', $data);
    }

     /* add/edit a team */

     public function group_save()
     {
         helper(['form']);
 
         $validation = \Config\Services::validation();
         $validation->setRules([
             'id' => 'numeric',
             'title' => 'required',
             'members' => 'required'
         ]);
 
         if (!$validation->withRequest($this->request)->run()) {
             echo json_encode(['success' => false, 'message' => lang('error_occurred')]);
             exit();
         }
 
         $id = $this->request->getPost('id');
         $title = $this->request->getPost('title');
 
         $data = [
             'title' => $title,
             'members' => $this->request->getPost('members')
         ];
 
         $groupsModel = new GroupsModel();
 
         if (!$id) {
             // Check if group title already exists
             if ($groupsModel->isGroupTitleExists($title)) {
                 echo json_encode(['success' => false, 'message' => lang('group_name_already')]);
                 exit();
             }
         } else {
             // Check if group title already exists (excluding current group)
             if ($groupsModel->isGroupTitleExists($title, $id)) {
                 echo json_encode(['success' => false, 'message' => lang('group_name_already')]);
                 exit();
             }
         }
 
         $save_id = $groupsModel->save($data, $id);
         if ($save_id) {
             echo json_encode(['success' => true, 'data' => $this->_group_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved')]);
         } else {
             echo json_encode(['success' => false, 'message' => lang('error_occurred')]);
         }
     }

    /* delete/undo a team */

    public function group_delete()
    {
        helper(['form']);

        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'required|numeric'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            echo json_encode(['success' => false, 'message' => lang('error_occurred')]);
            exit();
        }

        $id = $this->request->getPost('id');
        $undo = (bool)$this->request->getPost('undo');

        $groupsModel = new GroupsModel();

        if ($undo) {
            if ($groupsModel->delete($id, true)) {
                echo json_encode(['success' => true, 'data' => $this->_group_row_data($id), 'message' => lang('record_undone')]);
            } else {
                echo json_encode(['success' => false, 'message' => lang('error_occurred')]);
            }
        } else {
            if ($groupsModel->delete($id)) {
                echo json_encode(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                echo json_encode(['success' => false, 'message' => lang('record_cannot_be_deleted')]);
            }
        }
    }


    /* list of group prepared for datatable */
     public function group_list_data()
    {
        $groupsModel = new GroupsModel();
        $list_data = [];

        if ($this->login_user->user_type === 'staff') {
            if ($this->login_user->is_admin) {
                $list_data = $groupsModel->getDetails()->getResult();
            } else {
                $options = ['user_id' => $this->login_user->id];
                $list_data = $groupsModel->getDetails($options)->getResult();
            }
        }

        $result = array_map([$this, '_group_make_row'], $list_data);
        echo json_encode(['data' => $result]);
    }


    /* reaturn a row of group list table */
    private function _group_row_data($id)
    {
        $groupsModel = new GroupsModel();
        $data = $groupsModel->find($id);
        return $this->_group_make_row($data);
    }
    /* prepare a row of group list table */
    private function _group_make_row($data)
    {
        $total_members = '<span class="label label-light w100"><i class="fa fa-users"></i> ' . count(explode(",", $data->members)) . '</span>';

        $delete_option = '';
        if ($this->login_user->is_admin) {
            $delete_option = modal_anchor(get_uri("messages/group_modal_form"), '<i class="fa fa-pencil"></i>', [
                'class' => 'edit',
                'title' => lang('edit_group'),
                'data-post-id' => $data->id
            ]) . js_anchor('<i class="fa fa-times fa-fw"></i>', [
                'title' => lang('delete_group'),
                'class' => 'delete',
                'data-id' => $data->id,
                'data-action-url' => get_uri("messages/group_delete"),
                'data-action' => 'delete'
            ]);
        }

        $count_unread = $this->GroupsCommentsModel->countGroupUnreadMessage($this->login_user->id, $data->id);
        $count_unread_html = $count_unread ? "&nbsp;<span class='badge badge-secondary' style='background-color: #1672b9;'>$count_unread</span>" : "";

        return [
            '<a href="#" data-id="' . $data->id . '" data-index="' . $data->id . '" class="message-row link">' . $data->title . $count_unread_html . '</a>',
            modal_anchor(get_uri("messages/group_members_list"), $total_members, [
                'title' => lang('team_members'),
                'data-post-members' => $data->members
            ]),
            $delete_option
        ];
    }
    public function group_members_list()
    {
        $team_members = (new UsersModel())->getTeamMembers($this->request->getPost('members'))->getResult();
        $data = ['team_members' => $team_members];
        return view('messages/groups/members_list', $data);
    }


    //get permisissions of a role
    public function group_view($project_id)
    {
        if ($project_id) {
            $options = ['project_id' => $project_id];
            $view_data = [
                'comments' => (new Groups_comments_model())->getDetails($options)->getResult(),
                'project_id' => $project_id
            ];

            // Change message status to read
            (new Groups_comments_model())->setGroupMessageStatusAsRead($project_id, $this->login_user->id);

            return view("messages/groups/comments/index", $view_data);
        }
    }


/* save project comments */

public function save_group_comment()
{
    helper(['form']);

    $data = [
        'created_by' => $this->login_user->id,
        'created_at' => get_current_utc_time(),
        'project_id' => $this->request->getPost('project_id'),
        'task_id' => $this->request->getPost('task_id') ?? 0,
        'file_id' => $this->request->getPost('file_id') ?? 0,
        'customer_feedback_id' => $this->request->getPost('customer_feedback_id') ?? 0,
        'comment_id' => $this->request->getPost('comment_id') ?? 0,
        'description' => $this->request->getPost('description'),
        'group_members' => $this->getFilteredGroupMembers($this->request->getPost('project_id'))
    ];

    $data = clean_data($data);
    $files_data = move_files_from_temp_dir_to_permanent_dir(get_setting('timeline_file_path'), 'group_comment');
    $data['files'] = $files_data;

    $groups_comments_model = new Groups_comments_model();
    $save_id = $groups_comments_model->saveComment($data, $this->request->getPost('id'));

    if ($save_id) {
        $response_data = '';

        if ($this->request->getPost('reload_list')) {
            $options = ['id' => $save_id];
            $view_data = ['comments' => $groups_comments_model->getDetails($options)->getResult()];
            $response_data = view("messages/groups/comments/comment_list", $view_data);
        }

        echo json_encode(['success' => true, 'data' => $response_data, 'message' => lang('comment_submited')]);

        $comment_info = $groups_comments_model->getOne($save_id);

        $notification_options = [
            'group_id' => $comment_info->project_id,
            'group_comment_id' => $save_id
        ];

        if ($comment_info->file_id) {
            $notification_options['project_file_id'] = $comment_info->file_id;
            log_notification('project_file_commented', $notification_options);
        } elseif ($comment_info->task_id) {
            $notification_options['task_id'] = $comment_info->task_id;
            log_notification('project_task_commented', $notification_options);
        } elseif ($comment_info->customer_feedback_id) {
            if ($comment_info->comment_id) {
                log_notification('project_customer_feedback_replied', $notification_options);
            } else {
                log_notification('project_customer_feedback_added', $notification_options);
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => lang('error_occurred')]);
    }
}


    /* show comment reply form */

    public function group_comment_reply_form($comment_id, $type = 'project', $type_id = 0)
    {
        $view_data = ['comment_id' => $comment_id];

        switch ($type) {
            case 'project':
                $view_data['project_id'] = $type_id;
                break;
            case 'task':
                $view_data['task_id'] = $type_id;
                break;
            case 'file':
                $view_data['file_id'] = $type_id;
                break;
            case 'customer_feedback':
                $view_data['project_id'] = $type_id;
                break;
        }

        return view("messages/groups/comments/reply_form", $view_data);
    }


 /* load all replies of a comment */

 public function group_view_comment_replies($comment_id)
 {
     $options = ['comment_id' => $comment_id];
     $view_data = ['reply_list' => (new Groups_comments_model())->getDetails($options)->getResult()];

     return view("messages/groups/comments/reply_list", $view_data);
 }


 public function group_delete_comment($id = 0)
 {
     if (!$id) {
         exit();
     }

     $comment_info = (new Groups_comments_model())->getOne($id);

     // Only admin and creator can delete the comment
     if (!($this->login_user->is_admin || $comment_info->created_by == $this->login_user->id)) {
         return redirect()->to(site_url('forbidden'));
     }

     // Delete the comment and files
     if ((new Groups_comments_model())->delete($id) && $comment_info->files) {
         $file_path = get_setting("timeline_file_path");
         $files = unserialize($comment_info->files);

         foreach ($files as $file) {
             delete_file_from_directory($file_path . $file['file_name']);
         }
     }
 }
/* download files by zip */

     public function group_download_comment_files($id)
     {
         $info = (new Groups_comments_model())->getOne($id);
 
         if ($this->login_user->user_type == 'client' || $this->login_user->user_type == 'user') {
             return redirect()->to(site_url('forbidden'));
         }
 
         download_app_files(get_setting('timeline_file_path'), $info->files);
     }
 }

/* End of file messages.php */
/* Location: ./application/controllers/messages.php */