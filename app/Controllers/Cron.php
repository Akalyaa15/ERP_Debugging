<?php

namespace App\Controllers;

use App\Models\SettingsModel;
use App\Libraries\CronJob;
use CodeIgniter\Controller;

class Cron extends Controller
{
    protected $settingsmodel;

    public function __construct()
    {
        $this->settingsmodel = new SettingsModel();
        $this->cron_job = new CronJob();

        parent::__construct();
    }

    public function index()
    {
        ini_set('max_execution_time', 300); // execute maximum 300 seconds

        // wait at least 10 seconds before starting a new cron job
        $last_cron_job_time = get_setting('last_cron_job_time');

        $current_time = strtotime(get_current_utc_time());

        if ($last_cron_job_time == "" || ($current_time > ($last_cron_job_time * 1 + 10))) {
            $this->cron_job->run();
            $this->settingsmodel->save_setting("last_cron_job_time", $current_time);
        }
    }
}

