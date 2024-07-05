<?php

namespace App\Models;

use CodeIgniter\Model;

class Estimate_items_model extends Model
{
    protected $table = 'estimate_items';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;

    public function __construct()
    {
        parent::__construct();
    }

    public function get_details($options = [])
    {
        $builder = $this->db->table('estimate_items');
        $builder->select('estimate_items.*, clients.currency_symbol AS currency_symbol');
        $builder->join('estimates', 'estimates.id = estimate_items.estimate_id', 'left');
        $builder->join('clients', 'clients.id = estimates.client_id', 'left');
        $builder->where('estimate_items.deleted', 0);

        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where('estimate_items.id', $id);
        }

        $estimate_id = $options['estimate_id'] ?? null;
        if ($estimate_id) {
            $builder->where('estimate_items.estimate_id', $estimate_id);
        }

        return $builder->get();
    }

    public function get_item_suggestionss($s = "")
    {
        $builder = $this->db->table('clients');
        $builder->select('clients.currency, clients.country');
        $builder->join('estimates', 'estimates.client_id = clients.id', 'left');
        $builder->where('clients.deleted', 0);
        $builder->where('estimates.id', $s);
        $builder->limit(1);

        return $builder->get()->getRow();
    }

    public function get_item_suggestionsss($client_type = "")
    {
        $builder = $this->db->table('clients');
        $builder->select('buyer_types.profit_margin, buyer_types.buyer_type');
        $builder->join('estimates', 'estimates.client_id = clients.id', 'left');
        $builder->join('buyer_types', 'buyer_types.id = clients.buyer_type', 'left');
        $builder->where('clients.deleted', 0);
        $builder->where('estimates.id', $client_type);
        $builder->limit(1);

        return $builder->get()->getRow();
    }

    public function get_item_suggestions($keyword = "", $d_item = "", $category = "")
    {
        $builder = $this->db->table('items');
        $builder->select('title');
        $builder->where('deleted', 0);
        $builder->like('title', $keyword);
        $builder->whereNotIn('title', $d_item);
        $builder->where('category', $category);
        $builder->limit(30);

        return $builder->get()->getResult();
    }

    public function get_service_item_suggestions($keyword = "", $d_item = "", $category = "")
    {
        $builder = $this->db->table('service_id_generation');
        $builder->select('title');
        $builder->where('deleted', 0);
        $builder->like('title', $keyword);
        $builder->whereNotIn('title', $d_item);
        $builder->where('category', $category);
        $builder->limit(30);

        return $builder->get()->getResult();
    }

    public function get_item_suggestion($keyword = "")
    {
        $builder = $this->db->table('items');
        $builder->select('title');
        $builder->where('deleted', 0);
        $builder->like('title', $keyword);
        $builder->limit(30);

        return $builder->get()->getResult();
    }

    public function get_item_info_suggestion($item_name = "")
    {
        $builder = $this->db->table('items');
        $builder->where('deleted', 0);
        $builder->like('title', $item_name);
        $builder->orderBy('id', 'desc');
        $builder->limit(1);

        $result = $builder->get();
        return $result->getRow();
    }

    public function get_service_item_info_suggestion($item_name = "")
    {
        $builder = $this->db->table('service_id_generation');
        $builder->where('deleted', 0);
        $builder->like('title', $item_name);
        $builder->orderBy('id', 'desc');
        $builder->limit(1);

        $result = $builder->get();
        return $result->getRow();
    }

    public function is_estimate_product_exists($title, $estimate_id, $id = 0)
    {
        $builder = $this->db->table('estimate_items');
        $builder->where('title', $title);
        $builder->where('estimate_id', $estimate_id);
        $builder->where('deleted', 0);
        $builder->where('id !=', $id);

        $result = $builder->get();
        return $result->getRow();
    }
}
