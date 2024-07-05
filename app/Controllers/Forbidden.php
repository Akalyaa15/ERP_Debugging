<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Forbidden extends Controller
{
    public function __construct()
    {
        // Call parent constructor
        parent::__construct();
    }

    public function index()
    {
        $viewData = [
            'heading' => '403 Forbidden',
            'message' => 'You don\'t have permission to access this module.'
        ];

        if ($this->request->isAJAX()) {
            $viewData['no_css'] = true;
        }

        echo view('errors/html/error_general', $viewData);
    }
}
