<?php

namespace App\Models;

use CodeIgniter\Model;

class PostsModel extends Model
{
    protected $table = 'posts';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    public function __construct()
    {
        parent::__construct();
    }

    public function getDetails($options = [])
    {
        $postsTable = $this->table;
        $usersTable = 'users'; // Assuming 'users' table name
        $builder = $this->db->table($postsTable);

        $limit = $options['limit'] ?? 20;
        $offset = $options['offset'] ?? 0;

        $builder->select("$postsTable.*, $postsTable.id AS parent_post_id, CONCAT($usersTable.first_name, ' ', $usersTable.last_name) AS created_by_user, $usersTable.image as created_by_avatar")
                ->join($usersTable, "$usersTable.id = $postsTable.created_by", 'left')
                ->where('deleted', 0);

        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where("$postsTable.id", $id);
        }

        $post_id = $options['post_id'] ?? null;
        if ($post_id) {
            $builder->where("$postsTable.post_id", $post_id);
            $builder->orderBy("$postsTable.created_at", 'ASC');
        } else {
            $builder->where("$postsTable.post_id", 0);
            $builder->orderBy("$postsTable.created_at", 'DESC');
        }

        $user_id = $options['user_id'] ?? null;
        if ($user_id) {
            $builder->where("$postsTable.created_by", $user_id);
        }

        $builder->orderBy("$postsTable.created_at", 'DESC')
                ->limit($limit, $offset);

        $result = new \stdClass();
        $result->result = $builder->get()->getResult();
        
        // Calculate total found rows
        $builder->select('FOUND_ROWS() as found_rows', false);
        $totalRows = $builder->get()->getRow();
        $result->found_rows = $totalRows->found_rows;

        return $result;
    }

    public function countNewPosts()
    {
        $now = date('Y-m-d');
        return $this->where('deleted', 0)
                    ->where('post_id', 0)
                    ->where("DATE(created_at)", $now)
                    ->countAllResults();
    }
}
