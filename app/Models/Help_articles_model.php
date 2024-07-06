<?php
namespace App\Models;
use CodeIgniter\Model;
class HelpArticlesModel extends Model {
    protected $table = 'help_articles';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['category_id', 'title', 'content', 'status', 'total_views', 'deleted'];
    protected $returnType = 'array';
    public function getDetails($options = []) {
        $helpCategoriesTable = $this->db->prefixTable('help_categories');
        $builder = $this->builder($this->table);
        $builder->select("{$this->table}.*, {$helpCategoriesTable}.title AS category_title, {$helpCategoriesTable}.type");
        $builder->join($helpCategoriesTable, "{$helpCategoriesTable}.id = {$this->table}.category_id", 'left');
        $builder->where("{$this->table}.deleted", 0);
        $builder->where("{$helpCategoriesTable}.deleted", 0);

        if (isset($options['id'])) {
            $builder->where("{$this->table}.id", $options['id']);
        }

        if (isset($options['type'])) {
            $builder->where("{$helpCategoriesTable}.type", $options['type']);
        }

        $builder->orderBy("{$this->table}.title");

        return $builder->get()->getResultArray();
    }

    public function getArticlesOfACategory($category_id) {
        $builder = $this->builder($this->table);
        $builder->select('id, title');
        $builder->where('deleted', 0);
        $builder->where('status', 'active');
        $builder->where('category_id', $category_id);
        $builder->orderBy('total_views DESC, title ASC');

        return $builder->get()->getResultArray();
    }

    public function increasePageView($id) {
        $builder = $this->builder($this->table);
        $builder->where('id', $id);
        $builder->set('total_views', 'total_views+1', false);

        return $builder->update();
    }

    public function getSuggestions($type, $search) {
        $helpCategoriesTable = $this->db->prefixTable('help_categories');
        $builder = $this->builder($this->table);
        $builder->select("{$this->table}.id, {$this->table}.title");
        $builder->join($helpCategoriesTable, "{$helpCategoriesTable}.id = {$this->table}.category_id", 'left');
        $builder->where("{$this->table}.deleted", 0);
        $builder->where("{$this->table}.status", 'active');
        $builder->where("{$helpCategoriesTable}.deleted", 0);
        $builder->where("{$helpCategoriesTable}.status", 'active');
        $builder->where("{$helpCategoriesTable}.type", $type);
        $builder->like("{$this->table}.title", $search);
        $builder->orderBy("{$this->table}.title ASC");
        $builder->limit(10);

        $results = $builder->get()->getResult();

        $resultArray = [];
        foreach ($results as $result) {
            $resultArray[] = [
                'value' => $result->id,
                'label' => $result->title
            ];
        }

        return $resultArray;
    }
}
