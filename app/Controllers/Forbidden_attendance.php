<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Forbidden_attendance extends Controller
{
    public function __construct()
    {
        // Call parent constructor
        parent::__construct();
    }

    public function index()
    {
        $viewData = [
            'heading' => 'Permission Restricted !',
            'message' => 'You don\'t have permission to access this module because you are an indoor user and trying to access from a different IP address.'
        ];

        if ($this->request->isAJAX()) {
            $viewData['no_css'] = true;
        }

        echo view('errors/html/error_general', $viewData);
    }
}
