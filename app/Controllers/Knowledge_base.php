<?php

namespace App\Controllers;

use App\Models\Users_model;
use App\Models\Help_categories_model;
use App\Models\Help_articles_model;

class Knowledge_base extends BaseController
{
    protected $users_model;
    protected $help_categories_model;
    protected $help_articles_model;
    protected $login_user;

    public function __construct()
    {
        $this->users_model = new Users_model();
        $this->help_categories_model = new Help_categories_model();
        $this->help_articles_model = new Help_articles_model();
        $this->login_user = $this->users_model->login_user_id() ? $this->users_model->get_access_info($this->users_model->login_user_id()) : new \stdClass();
    }

    // Show knowledge base page
    public function index()
    {
        if (!get_setting("module_knowledge_base")) {
            return redirect()->to('404');
        }

        $type = "knowledge_base";
        $categories = $this->help_categories_model->where('type', $type)->findAll();
        $view_data = [
            'categories' => $categories,
            'type' => $type
        ];

        if (!isset($this->login_user->id)) {
            $view_data['topbar'] = "includes/public/topbar";
            $view_data['left_menu'] = false;
        }

        return view('help_and_knowledge_base/index', $view_data);
    }

    // Show knowledge base category
    public function category($id)
    {
        if (!$id || !is_numeric($id)) {
            return redirect()->to('404');
        }

        $category_info = $this->help_categories_model->find($id);

        if (!$category_info || $category_info->type != "knowledge_base") {
            return redirect()->to('404');
        }

        $articles = $this->help_articles_model->where('category_id', $id)->findAll();
        $view_data = [
            'page_type' => "articles_list_view",
            'type' => $category_info->type,
            'selected_category_id' => $category_info->id,
            'categories' => $this->help_categories_model->where('type', $category_info->type)->findAll(),
            'articles' => $articles,
            'category_info' => $category_info
        ];

        if (!isset($this->login_user->id)) {
            $view_data['topbar'] = "includes/public/topbar";
            $view_data['left_menu'] = false;
        }

        return view('help_and_knowledge_base/articles/view_page', $view_data);
    }

    // Show article
    public function view($id = 0)
    {
        if (!$id || !is_numeric($id)) {
            return redirect()->to('404');
        }

        $article_info = $this->help_articles_model->find($id);

        if (!$article_info || $article_info->type != "knowledge_base") {
            return redirect()->to('404');
        }

        $this->help_articles_model->increase_page_view($id);

        $view_data = [
            'selected_category_id' => $article_info->category_id,
            'type' => $article_info->type,
            'categories' => $this->help_categories_model->where('type', $article_info->type)->findAll(),
            'page_type' => "article_view",
            'article_info' => $article_info
        ];

        if (!isset($this->login_user->id)) {
            $view_data['topbar'] = "includes/public/topbar";
            $view_data['left_menu'] = false;
        }

        return view('help_and_knowledge_base/articles/view_page', $view_data);
    }

    // Get article suggestion
    public function get_article_suggestion()
    {
        $search = $this->request->getPost("search");
        if ($search) {
            $result = $this->help_articles_model->get_suggestions("knowledge_base", $search);
            return $this->response->setJSON($result);
        }
    }

    // Download files
    public function download_files($id = 0)
    {
        $info = $this->help_articles_model->find($id);
        download_app_files(get_setting("timeline_file_path"), $info->files);
    }
}

/* End of file Knowledge_base.php */
/* Location: ./app/Controllers/Knowledge_base.php */
