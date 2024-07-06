<?php
namespace App\Models;
use CodeIgniter\Model;
class HelpCategoriesModel extends Model {
    protected $table = 'help_categories';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['type', 'status', 'sort', 'deleted'];
    protected $returnType = 'array';
    public function getDetails($options = []) {
        $helpArticlesTable = $this->db->prefixTable('help_articles');
        $builder = $this->builder($this->table);
        $builder->select("{$this->table}.*,
                          (SELECT COUNT($helpArticlesTable.id) 
                           FROM $helpArticlesTable 
                           WHERE $helpArticlesTable.category_id={$this->table}.id 
                           AND $helpArticlesTable.deleted=0 d
                           AND $helpArticlesTable.status='active') AS total_articles");
        $builder->where("{$this->table}.deleted", 0);

        if (isset($options['id'])) {
            $builder->where("{$this->table}.id", $options['id']);
        }

        if (isset($options['type'])) {
            $builder->where("{$this->table}.type", $options['type']);
        }

        if (isset($options['only_active_categories'])) {
            $builder->where("{$this->table}.status", 'active');
        }

        $builder->orderBy("{$this->table}.sort");

        return $builder->get()->getResultArray();
    }
}