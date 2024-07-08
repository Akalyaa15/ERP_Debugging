<?php

namespace App\Models;

use CodeIgniter\Model;
class SettingsModel extends Model
{
    protected $table = 'settings';
    protected $primaryKey = 'id';
    protected $returnType = 'object'; 
    public function getSetting($setting_name)
    {
        $query = $this->where('setting_name', $setting_name)
                      ->where('deleted', 0)
                      ->first();
  if ($query) {
            return $query->setting_value;
        }
    }public function saveSetting($setting_name, $setting_value, $type = 'app')
    {
        $exists = $this->getSetting($setting_name);

        $data = [
            'setting_name' => $setting_name,
            'setting_value' => $setting_value,
            'type' => $type
        ];

        if ($exists === null) {
            return $this->insert($data);
        } else {
            $this->where('setting_name', $setting_name)
                 ->update($data);
            return true;
        }
    }
    public function getAllRequiredSettings($user_id = 0)
    {
        $query = $this->select('setting_name, setting_value')
                      ->where('deleted', 0)
                      ->where(function($builder) use ($user_id) {
                          $builder->groupStart()
                                  ->where('type', 'app')
                                  ->orWhere(function($builder) use ($user_id) {
                                      $builder->where('type', 'user')
                                              ->like('setting_name', 'user_' . $user_id . '_');
                                  })
                                ->groupEnd();
                      })
                      ->findAll();

        return $query;
    }}
