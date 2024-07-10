<?php

namespace App\Models;

use CodeIgniter\Model;
use DateTime;
use DateTimeZone;

class AttendanceModel extends Model
{
    protected $table = 'attendance';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'user_id', 'in_time', 'out_time', 'status', 'note', 'clockin_location', 'clockout_location', 'deleted'
    ];
    public function __construct()
    {
        parent::__construct();
    }

    public function currentClockInRecord($user_id)
    {
        return $this->where('deleted', 0)
                    ->where('user_id', $user_id)
                    ->where('status', 'incomplete')
                    ->first();
    }

    public function logTime($user_id, $note = "", $result = "")
    {
        $currentClockRecord = $this->currentClockInRecord($user_id);
        
        $userTimezone = model('UsersModel')->find($user_id)['user_timezone'];
        $d = new DateTime('now', new DateTimeZone($userTimezone));
        $now = $d->format('Y-m-d H:i:s');

        if ($currentClockRecord) {
            $data = [
                'out_time' => $now,
                'status' => 'pending',
                'note' => $note,
                'clockout_location' => $result
            ];
            return $this->update($currentClockRecord['id'], $data);
        } else {
            $data = [
                'in_time' => $now,
                'status' => 'incomplete',
                'user_id' => $user_id
            ];
            return $this->insert($data);
        }
    }

    public function getDetails($options = [])
    {
        $builder = $this->db->table($this->table)
                            ->select("{$this->table}.*, CONCAT(users.first_name, ' ', users.last_name) AS created_by_user, users.image AS created_by_avatar, users.id AS user_id, users.job_title AS user_job_title, users.user_type AS user_user_type")
                            ->join('users', 'users.id = attendance.user_id')
                            ->where("{$this->table}.deleted", 0);

        if (!empty($options['id'])) {
            $builder->where("{$this->table}.id", $options['id']);
        }

        $offset = convert_seconds_to_time_format();

        if (!empty($options['start_date'])) {
            $builder->where("DATE(ADDTIME({$this->table}.in_time,'$offset')) >=", $options['start_date']);
        }

        if (!empty($options['end_date'])) {
            $builder->where("DATE(ADDTIME({$this->table}.in_time,'$offset')) <=", $options['end_date']);
        }

        if (!empty($options['user_id'])) {
            $builder->where("{$this->table}.user_id", $options['user_id']);
        }

        if (empty($options['id']) && $options['access_type'] !== 'all') {
            $allowedMembers = !empty($options['allowed_members']) ? join(',', $options['allowed_members']) : '0';
            if (!empty($options['login_user_id'])) {
                $allowedMembers .= ',' . $options['login_user_id'];
            }
            $builder->where("{$this->table}.user_id IN ($allowedMembers)");
        }

        if (!empty($options['only_clocked_in_members'])) {
            $builder->where("{$this->table}.status", 'incomplete');
        }

        $builder->orderBy("{$this->table}.in_time", 'DESC');

        return $builder->get()->getResultArray();
    }

    public function getSummaryDetails($options = [])
    {
        $offset = convert_seconds_to_time_format();

        $builder = $this->db->table($this->table)
                            ->select("user_id, SUM(TIMESTAMPDIFF(SECOND, {$this->table}.in_time, {$this->table}.out_time)) AS total_duration, {$this->table}.clockin_location AS clock_in, {$this->table}.clockout_location AS clock_out, CONCAT(users.first_name, ' ', users.last_name) AS created_by_user, users.image AS created_by_avatar")
                            ->join('users', 'users.id = attendance.user_id')
                            ->where("{$this->table}.deleted", 0);

        if (!empty($options['start_date'])) {
            $builder->where("DATE(ADDTIME({$this->table}.in_time,'$offset')) >=", $options['start_date']);
        }

        if (!empty($options['end_date'])) {
            $builder->where("DATE(ADDTIME({$this->table}.in_time,'$offset')) <=", $options['end_date']);
        }

        if (!empty($options['user_id'])) {
            $builder->where("{$this->table}.user_id", $options['user_id']);
        }

        if ($options['access_type'] !== 'all') {
            $allowedMembers = !empty($options['allowed_members']) ? join(',', $options['allowed_members']) : '0';
            if (!empty($options['login_user_id'])) {
                $allowedMembers .= ',' . $options['login_user_id'];
            }
            $builder->where("{$this->table}.user_id IN ($allowedMembers)");
        }

        if (!empty($options['summary_details'])) {
            $builder->select("MAX(DATE(ADDTIME({$this->table}.in_time,'$offset'))) AS start_date")
                    ->groupBy(["{$this->table}.user_id", "DATE(ADDTIME({$this->table}.in_time,'$offset'))"])
                    ->orderBy('user_id, start_date', 'ASC');
        } else {
            $builder->groupBy("{$this->table}.user_id");
        }

        return $builder->get()->getResultArray();
    }

    public function countClockStatus()
    {
        $clockedIn = $this->db->table($this->table)
                              ->select('user_id')
                              ->where('deleted', 0)
                              ->where('status', 'incomplete')
                              ->groupBy('user_id')
                              ->get()
                              ->getResultArray();

        $totalMembers = $this->db->table('users')
                                 ->select('COUNT(id) AS total_members')
                                 ->where('deleted', 0)
                                 ->where('user_type', 'staff')
                                 ->where('status', 'active')
                                 ->get()
                                 ->getRow()
                                 ->total_members;

        $info = new \stdClass();
        $info->members_clocked_in = count($clockedIn);
        $info->total_members = $totalMembers;
        $info->members_clocked_out = $totalMembers - $info->members_clocked_in;

        return $info;
    }

    public function getTimecardStatistics($options = [])
    {
        $offset = convert_seconds_to_time_format();

        $builder = $this->db->table($this->table)
                            ->select("DATE_FORMAT(in_time,'%d') AS day, SUM(TIME_TO_SEC(TIMEDIFF(out_time,in_time))) AS total_sec")
                            ->where('deleted', 0)
                            ->where('status !=', 'incomplete');

        if (!empty($options['start_date'])) {
            $builder->where("DATE(ADDTIME(in_time,'$offset')) >=", $options['start_date']);
        }

        if (!empty($options['end_date'])) {
            $builder->where("DATE(ADDTIME(in_time,'$offset')) <=", $options['end_date']);
        }

        if (!empty($options['user_id'])) {
            $builder->where('user_id', $options['user_id']);
        }

        $builder->groupBy("DATE(in_time)");

        return $builder->get()->getResultArray();
    }

    public function countTotalTime($options = [])
    {
        $attendanceWhere = "";
        $timesheetWhere = "";

        if (!empty($options['user_id'])) {
            $attendanceWhere .= " AND user_id = " . $options['user_id'];
            $timesheetWhere .= " AND user_id = " . $options['user_id'];
        }

        $attendanceSql = "SELECT SUM(TIME_TO_SEC(TIMEDIFF(out_time, in_time))) AS total_sec FROM {$this->db->prefixTable('attendance')} WHERE deleted = 0 AND status != 'incomplete' $attendanceWhere";
        $timesheetSql = "SELECT SUM(TIME_TO_SEC(TIMEDIFF(end_time, start_time))) AS total_sec FROM {$this->db->prefixTable('project_time')} WHERE deleted = 0 AND status = 'logged' $timesheetWhere";

        $info = new \stdClass();

        $info->total_clocked_in = $this->db->query($attendanceSql)->getRow()->total_sec;
        $info->total_clocked_in = convert_seconds_to_time_format($info->total_clocked_in);

        $info->total_time = $this->db->query($timesheetSql)->getRow()->total_sec;
        $info->total_time = convert_seconds_to_time_format($info->total_time);

        return $info;
    }
    public function getClockedOutMembers($options = [])
    {
        $builder = $this->db->table('users')
                            ->select("CONCAT(first_name, ' ', last_name) AS member_name, last_online, image, id, job_title")
                            ->where('deleted', 0)
                            ->where('status', 'active')
                            ->where('user_type', 'staff')
                            ->where("id NOT IN (SELECT user_id FROM {$this->table} WHERE deleted = 0 AND status = 'incomplete')");

        if (!empty($options['access_type']) && $options['access_type'] !== 'all') {
            $allowedMembers = !empty($options['allowed_members']) ? join(',', $options['allowed_members']) : '0';
            if (!empty($options['login_user_id'])) {
                $allowedMembers .= ',' . $options['login_user_id'];
            }
            $builder->whereIn('id', $allowedMembers);
        }

        $builder->orderBy('first_name', 'DESC');

        return $builder->get()->getResultArray();
    }

    public function getCoDetails($options = [])
    {
        $builder = $this->db->table($this->table)
                            ->select("{$this->table}.*, CONCAT(users.first_name, ' ', users.last_name) AS created_by_user, users.image AS created_by_avatar, users.id AS user_id, users.job_title AS user_job_title, users.user_type AS user_user_type, users.user_timezone, users.email AS user_emails, users.phone AS user_phone")
                            ->join('users', 'users.id = attendance.user_id')
                            ->where("{$this->table}.deleted", 0)
                            ->where("{$this->table}.status", 'incomplete')
                            ->orderBy("{$this->table}.in_time", 'ASC');

        return $builder->get()->getResultArray();
    }

    public function updateClockOut($id)
    {
        $note = "Your did not Clock out";
        $now = date("Y-m-d H:i:s");

        $data = [
            'note' => $note,
            'out_time' => $now,
            'in_time' => $now,
            'status' => 'pending'
        ];

        $this->where('id', $id)->update($data);
    }
}