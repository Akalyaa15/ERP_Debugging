<?php

namespace App\Models;

use CodeIgniter\Model;

class EventsModel extends Model
{
    protected $table = 'events';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useSoftDeletes = false; 

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getDetails($options = [])
    {
        $events_table = $this->table;
        $users_table = 'users';
        $clients_table = 'clients';

        $where = [];
        $id = $options['id'] ?? null;
        if ($id) {
            $where[] = "$events_table.id = $id";
        }

        $start_date = $options['start_date'] ?? null;
        if ($start_date) {
            $start_date = $this->db->escape($start_date);
            $where[] = "DATE($events_table.start_date) >= '$start_date'";
        }

        $end_date = $options['end_date'] ?? null;
        if ($end_date) {
            $end_date = $this->db->escape($end_date);
            $where[] = "DATE($events_table.end_date) <= '$end_date'";
        }

        $include_recurring = $options['include_recurring'] ?? null;
        if ($include_recurring) {
            $where[] = "((DATE($events_table.start_date) >= '$start_date' AND DATE($events_table.end_date) <= '$end_date') OR $events_table.recurring = 1)";
        } else if ($start_date && $end_date) {
            $where[] = "DATE($events_table.start_date) >= '$start_date' AND DATE($events_table.end_date) <= '$end_date'";
        }

        $future_from = $options['future_from'] ?? null;
        if ($future_from) {
            $where[] = "(DATE($events_table.start_date) >= '$future_from' OR DATE($events_table.last_start_date) >= '$future_from')";
        }

        $user_id = $options['user_id'] ?? null;
        if ($user_id) {
            $team_ids = $options['team_ids'] ?? null;

            if ($team_ids) {
                $teams_array = explode(",", $team_ids);
                $team_search_sql = "";
                foreach ($teams_array as $team_id) {
                    $team_search_sql .= " OR (FIND_IN_SET('team:$team_id', $events_table.share_with)) ";
                }
            }

            $is_client = $options['is_client'] ?? null;
            if ($is_client) {
                $where[] = "($events_table.created_by = $user_id OR FIND_IN_SET('contact:$user_id', $events_table.share_with))";
            } else {
                $where[] = "($events_table.created_by = $user_id OR $events_table.share_with = 'all' OR FIND_IN_SET('member:$user_id', $events_table.share_with) $team_search_sql)";
            }
        }

        $client_id = $options['client_id'] ?? null;
        if ($client_id) {
            $where[] = "$events_table.client_id = $client_id";
        }

        $limit = $options['limit'] ?? 20000;
        $offset = $options['offset'] ?? 0;

        $this->select("$events_table.*, CONCAT($users_table.first_name, ' ', $users_table.last_name) AS created_by_name, $users_table.image AS created_by_avatar, $clients_table.company_name")
            ->join($users_table, "$users_table.id = $events_table.created_by", 'left')
            ->join($clients_table, "$clients_table.id = $events_table.client_id", 'left')
            ->where('deleted', 0);

        foreach ($where as $condition) {
            $this->where($condition);
        }

        $this->orderBy("$events_table.start_date", 'ASC');
        $this->limit($limit, $offset);

        return $this->findAll();
    }

    public function countEventsToday($options = [])
    {
        $events_table = $this->table;
        $now = date("Y-m-d");

        $where = [];
        $user_id = $options['user_id'] ?? null;
        if ($user_id) {
            $team_ids = $options['team_ids'] ?? null;

            if ($team_ids) {
                $teams_array = explode(",", $team_ids);
                $team_search_sql = "";
                foreach ($teams_array as $team_id) {
                    $team_search_sql .= " OR (FIND_IN_SET('team:$team_id', $events_table.share_with)) ";
                }
            }

            $is_client = $options['is_client'] ?? null;
            if ($is_client) {
                $where[] = "($events_table.created_by = $user_id OR FIND_IN_SET('contact:$user_id', $events_table.share_with))";
            } else {
                $where[] = "($events_table.created_by = $user_id OR $events_table.share_with = 'all' OR FIND_IN_SET('member:$user_id', $events_table.share_with) $team_search_sql)";
            }
        }

        $this->selectCount('id')
            ->where('deleted', 0)
            ->where("($events_table.start_date <= '$now' AND $events_table.end_date >= '$now') OR FIND_IN_SET('$now', $events_table.recurring_dates)");

        foreach ($where as $condition) {
            $this->where($condition);
        }

        $query = $this->get();
        return $query->getRow()->id;
    }

    public function getLabelSuggestions()
    {
        $events_table = $this->table;
        $sql = "SELECT GROUP_CONCAT(labels) AS label_groups
                FROM $events_table
                WHERE deleted = 0";

        $query = $this->db->query($sql);
        return $query->getRow()->label_groups;
    }

    public function getNoOfCycles($repeat_type, $no_of_cycles = 0)
    {
        switch ($repeat_type) {
            case 'days':
                if (!$no_of_cycles || $no_of_cycles > 365) {
                    $no_of_cycles = 365;
                }
                break;
            case 'weeks':
                if (!$no_of_cycles || $no_of_cycles > 520) {
                    $no_of_cycles = 520;
                }
                break;
            case 'months':
                if (!$no_of_cycles || $no_of_cycles > 120) {
                    $no_of_cycles = 120;
                }
                break;
            case 'years':
                if (!$no_of_cycles || $no_of_cycles > 10) {
                    $no_of_cycles = 10;
                }
                break;
        }

        return $no_of_cycles;
    }

    private function sortByStartDate($a, $b)
    {
        return strtotime($a->start_date) - strtotime($b->start_date);
    }

    public function getUpcomingEvents($options = [])
    {
        $today = date("Y-m-d", strtotime(convert_date_local_to_utc(date("Y-m-d H:i:s"))) + get_timezone_offset());
        $options["future_from"] = $today;

        $result = $this->getDetails($options);
        $final_result = [];
        $has_recurring = false;

        foreach ($result as $data) {
            $data->cycle = 0;

            if ($data->recurring) {
                $has_recurring = true;

                if ($data->start_date >= $today) {
                    $final_result[] = clone $data;
                }

                $no_of_cycles = $this->getNoOfCycles($data->repeat_type, $data->no_of_cycles);

                for ($i = 1; $i <= $no_of_cycles; $i++) {
                    $data->start_date = add_period_to_date($data->start_date, $data->repeat_every, $data->repeat_type);
                    $data->end_date = add_period_to_date($data->end_date, $data->repeat_every, $data->repeat_type);
                    $data->cycle = $i;

                    if ($data->start_date >= $today) {
                        $final_result[] = clone $data;
                    }
                }
            } else {
                $final_result[] = $data;
            }
        }

        if ($has_recurring) {
            usort($final_result, array($this, "sortByStartDate"));
            $final_result = array_slice($final_result, 0, 10);
        }

        return $final_result;
    }

    public function getResponseByUsers($user_ids_array = [])
    {
        $users_table = 'users';
        $user_ids = implode(",", $user_ids_array);

        if ($user_ids) {
            $sql = "SELECT id, user_type, image, CONCAT(first_name, ' ', last_name) AS member_name 
                    FROM $users_table 
                    WHERE FIND_IN_SET(id, '$user_ids') AND deleted = 0";

            return $this->db->query($sql)->getResult();
        } else {
            return false;
        }
    }

    public function saveEventStatus($id, $user_id, $status)
    {
        $events_table = $this->table;

        $new_status = "";
        $old_status = "";

        if ($status == "confirmed") {
            $new_status .= "confirmed_by";
            $old_status .= "rejected_by";
        } else if ($status == "rejected") {
            $new_status .= "rejected_by";
            $old_status .= "confirmed_by";
        }

        $sql = "UPDATE $events_table 
                SET $new_status = CONCAT($new_status, ',', $user_id), 
                    $old_status = REPLACE($old_status, ',$user_id', '')
                WHERE id = $id 
                AND FIND_IN_SET($user_id, $new_status) = 0";

        return $this->db->query($sql);
    }
}
