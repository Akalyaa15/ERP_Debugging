<?php

namespace App\Models;

use CodeIgniter\Model;

class InvoiceItemsModel extends Model
{
    protected $table = 'invoice_items';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'invoice_id',
        'title',
        'description',
        'quantity',
        'rate',
        'total',
        'tax',
        'sort',
    ];

    public function getDetails($options = [])
    {
        $invoiceItemsTable = $this->table;
        $invoicesTable = $this->db->prefixTable('invoices');
        $clientsTable = $this->db->prefixTable('clients');
        $where = [];
        $id = $options['id'] ?? null;
        if ($id) {
            $where[] = "$invoiceItemsTable.id = $id";
        }
        $invoiceId = $options['invoice_id'] ?? null;
        if ($invoiceId) {
            $where[] = "$invoiceItemsTable.invoice_id = $invoiceId";
        }

        $whereClause = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT $invoiceItemsTable.*, 
                       (SELECT $clientsTable.currency_symbol 
                        FROM $clientsTable 
                        WHERE $clientsTable.id = $invoicesTable.client_id 
                        LIMIT 1) AS currency_symbol
                FROM $invoiceItemsTable
                LEFT JOIN $invoicesTable ON $invoicesTable.id = $invoiceItemsTable.invoice_id
                $whereClause
                ORDER BY $invoiceItemsTable.sort ASC";

        return $this->db->query($sql)->getResult();
    }

    public function getItemSuggestion($keyword = "")
    {
        $itemsTable = $this->db->prefixTable('items');

        $sql = "SELECT title
                FROM $itemsTable
                WHERE deleted = 0 AND title LIKE '%$keyword%'
                LIMIT 10";

        return $this->db->query($sql)->getResult();
    }

    public function getItemInfoSuggestion($itemName = "")
    {
        $itemsTable = $this->db->prefixTable('items');

        $sql = "SELECT *
                FROM $itemsTable
                WHERE deleted = 0 AND title LIKE '%$itemName%'
                ORDER BY id DESC
                LIMIT 1";

        $result = $this->db->query($sql);

        return $result->getRow();
    }
}
