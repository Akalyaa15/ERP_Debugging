<?php

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

class VendorsModel extends Model
{
    protected $table = 'vendors';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    public function getImportDetailss(array $options = [])
{
    $builder = $this->db->table('vendors');
    $id = $options['id'] ?? null;
    if ($id) {
        $builder->where('id', $id);
    }
    $companyName = $options['company_name'] ?? null;
    if ($companyName) {
        $builder->where('company_name', $companyName);
    }
    $city = $options['city'] ?? null;
    if ($city) {
        $builder->where('city', $city);
    }
    $state = $options['state'] ?? null;
    if ($state) {
        $builder->where('state', $state);
    }
    $country = $options['country'] ?? null;
    if ($country) {
        $builder->where('country', $country);
    }
    $website = $options['website'] ?? null;
    if ($website) {
        $builder->where('website', $website);
    }
    $zip = $options['zip'] ?? null;
    if ($zip) {
        $builder->where('zip', $zip);
    }
    $phone = $options['phone'] ?? null;
    if ($phone) {
        $builder->where('phone', $phone);
    }
    $gstNumber = $options['gst_number'] ?? null;
    if ($gstNumber) {
        $builder->where('gst_number', $gstNumber);
    }
    $currency = $options['currency'] ?? null;
    if ($currency) {
        $builder->where('currency', $currency);
    }
    $currencySymbol = $options['currency_symbol'] ?? null;
    if ($currencySymbol) {
        $builder->where('currency_symbol', $currencySymbol);
    }
    $gstinNumberFirstTwoDigits = $options['gstin_number_first_two_digits'] ?? null;
    if ($gstinNumberFirstTwoDigits) {
        $builder->where('gstin_number_first_two_digits', $gstinNumberFirstTwoDigits);
    }

    $builder->where('deleted', 0);
    return $builder->get();
}
   
public function get_details(array $options = []): \CodeIgniter\Database\ResultInterface
{
    $db = $this->db;
    $vendorsTable = $db->prefixTable('vendors');
    $usersTable = $db->prefixTable('users');
    $purchaseOrdersTable = $db->prefixTable('purchase_orders');
    $purchaseOrderPaymentsTable = $db->prefixTable('purchase_order_payments');
    $purchaseOrderItemsTable = $db->prefixTable('purchase_order_items');
    $workOrdersTable = $db->prefixTable('work_orders');
    $workOrderPaymentsTable = $db->prefixTable('work_order_payments');
    $workOrderItemsTable = $db->prefixTable('work_order_items');
    $vendorGroupsTable = $db->prefixTable('vendor_groups');

    $builder = $db->table($vendorsTable);

    $id = $options['id'] ?? null;
    if ($id) {
        $builder->where("$vendorsTable.id", $id);
    }

    $groupId = $options['group_id'] ?? null;
    if ($groupId) {
        $builder->where("FIND_IN_SET('$groupId', $vendorsTable.group_ids)");
    }

    // Prepare custom field binding query
    $customFields = $options['custom_fields'] ?? [];
    $customFieldQueryInfo = $this->prepare_custom_field_query_string("clients", $customFields, $vendorsTable);
    $selectCustomFields = $customFieldQueryInfo['select_string'];
    $joinCustomFields = $customFieldQueryInfo['join_string'];

    $freightAmount = "IFNULL($purchaseOrdersTable.freight_amount, 0)";

    $purchaseOrderValueCalculationQuery = "ROUND(
        SUM(IFNULL($purchaseOrderItemsTable.purchase_order_value, 0) + $freightAmount)
    )";

    $workOrderFreightAmount = "IFNULL($workOrdersTable.freight_amount, 0)";

    $workOrderValueCalculationQuery = "ROUND(
        SUM(IFNULL($workOrderItemsTable.work_order_value, 0) + $workOrderFreightAmount)
    )";

    // Ensure large selects are allowed
    $db->query('SET SQL_BIG_SELECTS=1');

    $builder->select("$vendorsTable.*, CONCAT($usersTable.first_name, ' ', $usersTable.last_name) AS primary_contact, $usersTable.id AS primary_contact_id, $usersTable.image AS contact_avatar");
    $builder->selectCount('COUNT('.$purchaseOrdersTable.'.id) as total_purchase_orders,IFNULL(purchase_order_details.purchase_order_value,0) AS purchase_order_value,IFNULL(purchase_order_details.payment_received,0) AS payment_received,work_orders_count_table.total_work_orders,IFNULL(work_order_details.work_order_value,0) AS work_order_value,IFNULL(work_order_details.work_order_payment_received,0) AS work_order_payment_received');
    $builder->select($selectCustomFields);
    $builder->join($usersTable, "$usersTable.vendor_id = $vendorsTable.id AND $usersTable.deleted = 0 AND $usersTable.is_primary_contact = 1", 'left');
    $builder->join("LEFT JOIN (SELECT vendor_id, COUNT(id) AS total_purchase_orders FROM $purchaseOrdersTable WHERE deleted = 0 GROUP BY vendor_id) AS purchase_orders_count_table ON purchase_orders_count_table.vendor_id = $vendorsTable.id", 'left');
    $builder->join("LEFT JOIN (SELECT vendor_id, COUNT(id) AS total_work_orders FROM $workOrdersTable WHERE deleted = 0 GROUP BY vendor_id) AS work_orders_count_table ON work_orders_count_table.vendor_id = $vendorsTable.id", 'left');
    $builder->join("LEFT JOIN (SELECT vendor_id, SUM(payments_table.payment_received) AS payment_received, $purchaseOrderValueCalculationQuery AS purchase_order_value FROM $purchaseOrdersTable
        LEFT JOIN (SELECT purchase_order_id, SUM(amount) AS payment_received FROM $purchaseOrderPaymentsTable WHERE deleted = 0 GROUP BY purchase_order_id) AS payments_table ON payments_table.purchase_order_id = $purchaseOrdersTable.id AND $purchaseOrdersTable.deleted = 0 AND $purchaseOrdersTable.status = 'not_paid'
         
        LEFT JOIN (SELECT purchase_order_id, SUM(net_total) AS purchase_order_value FROM $purchaseOrderItemsTable WHERE deleted = 0 GROUP BY purchase_order_id) AS items_table ON items_table.purchase_order_id = $purchaseOrdersTable.id AND $purchaseOrdersTable.deleted = 0 AND $purchaseOrdersTable.status = 'not_paid'  
                   GROUP BY $purchaseOrdersTable.vendor_id    
                   ) AS purchase_order_details ON purchase_order_details.vendor_id = $vendorsTable.id", 'left');
    $builder->join("LEFT JOIN (SELECT vendor_id, SUM(work_order_payments_table.work_order_payment_received) AS work_order_payment_received, $workOrderValueCalculationQuery AS work_order_value FROM $workOrdersTable
        LEFT JOIN (SELECT work_order_id, SUM(amount) AS work_order_payment_received FROM $workOrderPaymentsTable WHERE deleted = 0 GROUP BY work_order_id) AS work_order_payments_table ON work_order_payments_table.work_order_id = $workOrdersTable.id AND $workOrdersTable.deleted = 0 AND $workOrdersTable.status = 'not_paid'
         
        LEFT JOIN (SELECT work_order_id, SUM(net_total) AS work_order_value FROM $workOrderItemsTable WHERE deleted = 0 GROUP BY work_order_id) AS works_table ON works_table.work_order_id = $workOrdersTable.id AND $workOrdersTable.deleted = 0 AND 
        $workOrdersTable.status = 'not_paid'
                   GROUP BY $workOrdersTable.vendor_id    
                   ) AS work_order_details ON work_order_details.vendor_id = $vendorsTable.id", 'left');

    $builder->join($joinCustomFields);

    $builder->where("$vendorsTable.deleted", 0);
    $builder->where($where);

    return $builder->get();
}

public function get_primary_contact(int $vendorId = 0, bool $info = false)
{
    $usersTable = $this->db->table('users');

    $builder = $usersTable->select('id, first_name, last_name')
                          ->where('deleted', 0)
                          ->where('vendor_id', $vendorId)
                          ->where('is_primary_contact', 1)
                          ->get();

    if ($builder->getNumRows() > 0) {
        if ($info) {
            return $builder->getRow();
        } else {
            return $builder->getRow()->id;
        }
    }
}
public function add_remove_star(int $projectId, int $userId, string $type = "add")
{
    $table = $this->db->table('vendors');

    $action = " CONCAT(starred_by, ',', ':$userId:') ";
    $where = " FIND_IN_SET(':$userId:', starred_by) = 0 "; // Don't add duplicate

    if ($type !== "add") {
        $action = " REPLACE(starred_by, ',:$userId:', '') ";
        $where = "";
    }

    $sql = "UPDATE {$table->getName()} SET starred_by = $action WHERE id = $projectId AND $where";
    return $this->db->query($sql);
}

public function get_starred_vendors(int $userId)
{
    $table = $this->db->table('vendors');

    $builder = $table->select('id, company_name')
                     ->where('deleted', 0)
                     ->where("FIND_IN_SET(':$userId:', starred_by)")
                     ->orderBy('company_name', 'ASC')
                     ->get();

    return $builder;
}
public function delete_vendor_and_sub_items(int $vendorId): bool
{
    $vendorsTable = 'vendors';
    $generalFilesTable = 'general_files';
    $usersTable = 'users';
    $clientFiles = $this->db->table($generalFilesTable)
                            ->where('deleted', 0)
                            ->where('vendor_id', $vendorId)
                            ->get()
                            ->getResult();

    $this->db->table($vendorsTable)
             ->where('id', $vendorId)
             ->update(['deleted' => 1]);

    $this->db->table($usersTable)
             ->where('vendor_id', $vendorId)
             ->update(['deleted' => 1]);

   
    $file_path = get_general_file_path("vendor", $vendorId);
    foreach ($clientFiles as $file) {
        delete_file_from_directory($file_path . "/" . $file->file_name);
    }

    return true;
}
public function is_duplicate_company_name(string $companyName, int $id = 0)
{
    $query = $this->db->table('vendors')
                      ->where('company_name', $companyName)
                      ->where('deleted', 0)
                      ->get();

    if ($query->getNumRows() > 0 && $query->getRow()->id != $id) {
        return $query->getRow();
    } else {
        return false;
    }
}

public function get_vendor_country_info_suggestion(string $itemName = "")
{
    $query = $this->db->table('vendors')
                      ->where('deleted', 0)
                      ->where('id', $itemName)
                      ->orderBy('id', 'desc')
                      ->limit(1)
                      ->get();

    if ($query->getNumRows() > 0) {
        return $query->getRow();
    }
}

public function insert(array $data)
{
    $builder = $this->db->table('vendors');
    $builder->insertBatch($data);
}
public function get_search_suggestion(string $search = "", array $options = [])
{
    $clients_table = 'vendors'; // Assuming 'vendors' is the table name
    
    $builder = $this->db->table($clients_table);

    $search = $this->db->escapeLikeString($search); 
    $builder->select('id, company_name AS title')
            ->where('deleted', 0)
            ->like('company_name', $search)
            ->orderBy('company_name', 'ASC')
            ->limit(10);

    return $builder->get();
}
}
