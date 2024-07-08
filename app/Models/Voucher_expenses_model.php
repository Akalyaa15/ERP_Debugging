<?php

namespace App\Models;

use CodeIgniter\Model;
class VoucherExpensesModel extends CrudModel
{
    protected $table = 'voucher_expenses';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'expense_date', 'amount', 'category_id', 'project_id', 'user_id', 'r_user_id',
        'i_represent', 'r_represent', 'estimate_id', 'custom_fields', 'deleted'
    ];
    protected $returnType = 'object';
    public function getDetails($options = [])
    {
        $expensesTable = $this->db->prefixTable('voucher_expenses');
        $expenseCategoriesTable = $this->db->prefixTable('expense_categories');
        $projectsTable = $this->db->prefixTable('projects');
        $usersTable = $this->db->prefixTable('users');
        $clientsTable = $this->db->prefixTable('clients');
        $vendorsTable = $this->db->prefixTable('vendors');
        $where = "";
        $id = get_array_value($options, "id");
        if ($id) {
            $where .= " AND $expensesTable.id = " . $this->db->escape($id);
        }
        $startDate = get_array_value($options, "start_date");
        $endDate = get_array_value($options, "end_date");
        if ($startDate && $endDate) {
            $where .= " AND ($expensesTable.expense_date BETWEEN " . $this->db->escape($startDate) . " AND " . $this->db->escape($endDate) . ") ";
        }
        $categoryId = get_array_value($options, "category_id");
        if ($categoryId) {
            $where .= " AND $expensesTable.category_id = " . $this->db->escape($categoryId);
        }
        $projectId = get_array_value($options, "project_id");
        if ($projectId) {
            $where .= " AND $expensesTable.project_id = " . $this->db->escape($projectId);
        }
        $userId = get_array_value($options, "user_id");
        if ($userId) {
            $where .= " AND $expensesTable.user_id = " . $this->db->escape($userId);
        }
        $estimateId = get_array_value($options, "estimate_id");
        if ($estimateId) {
            $where .= " AND $expensesTable.estimate_id = " . $this->db->escape($estimateId);
        }

        $sql = "SELECT $expensesTable.*, 
                CONCAT(receiver.first_name, ' ', receiver.last_name) AS receiver_name,
                CONCAT(i_rep.first_name, ' ', i_rep.last_name) AS i_rep,
                CONCAT(r_rep.first_name, ' ', r_rep.last_name) AS r_rep,
                client.company_name AS client_name,
                client.address AS client_address,
                CONCAT(client.city, '- ', client.zip) AS client_pincode,
                receiver_client.company_name AS receiver_client_name,
                receiver_client.address AS receiver_client_address,
                CONCAT(receiver_client.city, '- ', receiver_client.zip) AS receiver_client_pincode,
                vendor.company_name AS vendor_name,
                vendor.address AS vendor_address,
                CONCAT(vendor.city, '- ', vendor.zip) AS vendor_pincode,
                receiver_vendor.company_name AS receiver_vendor_name,
                receiver_vendor.address AS receiver_vendor_address,
                CONCAT(receiver_vendor.city, '- ', receiver_vendor.zip) AS receiver_vendor_pincode,
                $expenseCategoriesTable.title AS category_title,
                CONCAT($usersTable.first_name, ' ', $usersTable.last_name) AS linked_user_name,
                CONCAT($usersTable.employee_id) AS employee_id,
                CONCAT($usersTable.job_title) AS job_title,
                CONCAT(receiver.first_name, ' ', receiver.last_name) AS r_linked_user_name,
                CONCAT(receiver.employee_id) AS r_employee_id,
                CONCAT(receiver.job_title) AS r_job_title,
                $projectsTable.title AS project_title
                FROM $expensesTable
                LEFT JOIN $expenseCategoriesTable ON $expenseCategoriesTable.id = $expensesTable.category_id
                LEFT JOIN $projectsTable ON $projectsTable.id = $expensesTable.project_id
                LEFT JOIN $usersTable ON $usersTable.id = $expensesTable.user_id
                LEFT JOIN $usersTable AS receiver ON receiver.id = $expensesTable.r_user_id
                LEFT JOIN $usersTable AS i_rep ON i_rep.id = $expensesTable.i_represent
                LEFT JOIN $usersTable AS r_rep ON r_rep.id = $expensesTable.r_represent
                LEFT JOIN $clientsTable AS client ON client.id = $expensesTable.user_id
                LEFT JOIN $clientsTable AS receiver_client ON receiver_client.id = $expensesTable.r_user_id
                LEFT JOIN $vendorsTable AS vendor ON vendor.id = $expensesTable.user_id
                LEFT JOIN $vendorsTable AS receiver_vendor ON receiver_vendor.id = $expensesTable.r_user_id
                WHERE $expensesTable.deleted = 0 $where";

        return $this->db->query($sql)->getResult();
    }

    public function getIncomeExpensesInfo()
    {
        $expensesTable = $this->db->prefixTable('expenses');
        $invoicePaymentsTable = $this->db->prefixTable('invoice_payments');
        $taxesTable = $this->db->prefixTable('taxes');

        $info = new \stdClass();

        $sql1 = "SELECT SUM($invoicePaymentsTable.amount) AS total_income
                FROM $invoicePaymentsTable
                WHERE $invoicePaymentsTable.deleted = 0";
        $income = $this->db->query($sql1)->getRow();

        $sql2 = "SELECT SUM($expensesTable.amount 
                        + IFNULL(tax_table.percentage,0)/100 * IFNULL($expensesTable.amount,0) 
                        + IFNULL(tax_table2.percentage,0)/100 * IFNULL($expensesTable.amount,0)) AS total_expenses
                FROM $expensesTable
                LEFT JOIN (SELECT $taxesTable.id, $taxesTable.percentage FROM $taxesTable) AS tax_table 
                        ON tax_table.id = $expensesTable.tax_id
                LEFT JOIN (SELECT $taxesTable.id, $taxesTable.percentage FROM $taxesTable) AS tax_table2 
                        ON tax_table2.id = $expensesTable.tax_id2
                WHERE $expensesTable.deleted = 0";
        $expenses = $this->db->query($sql2)->getRow();

        $info->income = $income->total_income ?? 0;
        $info->expenses = $expenses->total_expenses ?? 0;

        return $info;
    }

    public function getYearlyExpensesChart($year)
    {
        $expensesTable = $this->db->prefixTable('expenses');
        $taxesTable = $this->db->prefixTable('taxes');

        $sql = "SELECT SUM($expensesTable.amount 
                        + IFNULL(tax_table.percentage,0)/100 * IFNULL($expensesTable.amount,0) 
                        + IFNULL(tax_table2.percentage,0)/100 * IFNULL($expensesTable.amount,0)) AS total, 
                MONTH($expensesTable.expense_date) AS month
                FROM $expensesTable
                LEFT JOIN (SELECT $taxesTable.id, $taxesTable.percentage FROM $taxesTable) AS tax_table 
                        ON tax_table.id = $expensesTable.tax_id
                LEFT JOIN (SELECT $taxesTable.id, $taxesTable.percentage FROM $taxesTable) AS tax_table2 
                        ON tax_table2.id = $expensesTable.tax_id2
                WHERE $expensesTable.deleted = 0 AND YEAR($expensesTable.expense_date) = $year
                GROUP BY MONTH($expensesTable.expense_date)";

        return $this->db->query($sql)->getResult();
    }

    public function getVoucherExpenseDetails($itemName = "")
    {
        $itemsTable = $this->db->prefixTable('voucher_expenses');

        $sql = "SELECT $itemsTable.*
                FROM $itemsTable
                WHERE $itemsTable.deleted = 0 AND $itemsTable.estimate_id = " . $this->db->escape($itemName) . "
                ORDER BY id DESC
                LIMIT 1";

        return $this->db->query($sql)->getRow();
    }

    public function getVoucherId($itemName = "")
    {
        $itemsTable = $this->db->prefixTable('voucher_expenses');

        $sql = "SELECT $itemsTable.*
                FROM $itemsTable
                WHERE $itemsTable.deleted = 0 AND $itemsTable.user_id = " . $this->db->escape($itemName) . "
                ORDER BY id";

        return $this->db->query($sql)->getResult();
    }

    public function getVoucherIdForOthers($itemName = "")
    {
        $itemsTable = $this->db->prefixTable('voucher_expenses');

        $sql = "SELECT $itemsTable.*
                FROM $itemsTable
                WHERE $itemsTable.deleted = 0 AND $itemsTable.phone = " . $this->db->escape($itemName) . "
                ORDER BY id";

        return $this->db->query($sql)->getResult();
    }
}
