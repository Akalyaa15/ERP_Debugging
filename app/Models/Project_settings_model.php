<?php

namespace App\Models;

use CodeIgniter\Model;

class ProjectSettingsModel extends Model
{
    protected $table = 'project_settings';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    public function getSetting($projectId, $settingName)
    {
        return $this->where('project_id', $projectId)
                    ->where('setting_name', $settingName)
                    ->where('deleted', 0)
                    ->first();
    }

    public function saveSetting($projectId, $settingName, $settingValue)
    {
        $data = [
            'project_id' => $projectId,
            'setting_name' => $settingName,
            'setting_value' => $settingValue
        ];

        $existingSetting = $this->getSetting($projectId, $settingName);

        if (!$existingSetting) {
            return $this->insert($data);
        } else {
            return $this->update(['id' => $existingSetting->id], $data);
        }
    }
}
