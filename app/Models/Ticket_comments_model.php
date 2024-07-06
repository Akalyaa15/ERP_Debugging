<?php

namespace App\Models;

use CodeIgniter\Model;

class Ticket_comments_model extends Model
{
    protected $table = 'ticket_comments';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'id',
        'ticket_id',
        'comment',
        'created_by',
    ];

    protected $useSoftDeletes = true;
    protected $returnType = 'object';

    public function getDetails($options = [])
    {
        $usersTable = 'users'; // Assuming 'users' table is in the same database without prefix
        
        $builder = $this->select("$this->table.*, CONCAT($usersTable.first_name, ' ', $usersTable.last_name) AS created_by_user, $usersTable.image AS created_by_avatar, $usersTable.user_type")
                        ->join($usersTable, "$usersTable.id = $this->table.created_by", 'left')
                        ->where('deleted_at', null); // Assuming soft deletes

        if (!empty($options['id'])) {
            $builder->where("$this->table.id", $options['id']);
        }

        if (!empty($options['ticket_id'])) {
            $builder->where("$this->table.ticket_id", $options['ticket_id']);
        }

        if (!empty($options['sort_as_descending'])) {
            $builder->orderBy("$this->table.created_at", 'DESC');
        } else {
            $builder->orderBy("$this->table.created_at", 'ASC');
        }

        return $builder->findAll();
    }
}
