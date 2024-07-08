<?php

namespace App\Models;

use CodeIgniter\Model;

class Paypal_ipn_model extends Model
{
    protected $table = 'paypal_ipn';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    public function __construct()
    {
        parent::__construct();
    }
}
