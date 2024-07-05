<?php

namespace App\Models;
use CodeIgniter\Model;
class ExpensesModel extends Model
{
    protected $table = 'expenses';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'category_id', 'expense_date', 'project_id', 'user_id', 'company', 'vendor_company', 
        'total', 'description', 'created_at', 'deleted'
    ];
    protected $returnType = 'array';
    public function getDetails($options = [])
    {
        $builder = $this->builder($this->table);
        $builder->select("$this->table.*, expense_categories.title as category_title, 
                         CONCAT(users.first_name, ' ', users.last_name) AS linked_user_name,
                         projects.title AS project_title, clients.company_name AS client_company, vendors.company_name AS vendor_company");
        $builder->join('expense_categories', "expense_categories.id = $this->table.category_id", 'left');
        $builder->join('clients', "clients.id = $this->table.company", 'left');
        $builder->join('vendors', "vendors.id = $this->table.vendor_company", 'left');
        $builder->join('projects', "projects.id = $this->table.project_id", 'left');
        $builder->join('users', "users.id = $this->table.user_id", 'left');
        
        $where = ['deleted' => 0];
        
        $id = $options['id'] ?? null;
        if ($id) {
            $builder->where("$this->table.id", $id);
        }

        $startDate = $options['start_date'] ?? null;
        $endDate = $options['end_date'] ?? null;
        if ($startDate && $endDate) {
            $builder->where("($this->table.expense_date BETWEEN '$startDate' AND '$endDate')");
        }

        $categoryId = $options['category_id'] ?? null;
        if ($categoryId) {
            $builder->where("$this->table.category_id", $categoryId);
        }

        $projectId = $options['project_id'] ?? null;
        if ($projectId) {
            $builder->where("$this->table.project_id", $projectId);
        }

        $userId = $options['user_id'] ?? null;
        if ($userId) {
            $builder->where("$this->table.user_id", $userId);
        }

        $clientId = $options['client_id'] ?? null;
        if ($clientId) {
            $builder->where("$this->table.company", $clientId);
        }

        $vendorId = $options['vendor_id'] ?? null;
        if ($vendorId) {
            $builder->where("$this->table.vendor_company", $vendorId);
        }

        // Add custom field query
        $customFields = $options['custom_fields'] ?? [];
        $this->prepareCustomFieldQuery($builder, 'expenses', $customFields);

        $query = $builder->get();
        return $query->getResultArray();
    }

    public function getIncomeExpensesInfo()
    {
        $invoicePaymentsTable = 'invoice_payments';
        $expensesTable = $this->table;

        $sql1 = "SELECT SUM($invoicePaymentsTable.amount) as total_income
                 FROM $invoicePaymentsTable
                 LEFT JOIN invoices ON invoices.id = $invoicePaymentsTable.invoice_id
                 WHERE $invoicePaymentsTable.deleted = 0 AND invoices.deleted = 0";
        $income = $this->db->query($sql1)->getRow();

        $sql2 = "SELECT SUM(total) AS total_expenses FROM $expensesTable WHERE deleted = 0";
        $expenses = $this->db->query($sql2)->getRow();

        $sql3 = "SELECT SUM($expensesTable.total) AS total_purchase_payments
                 FROM vendors_invoice_payments_list
                 LEFT JOIN vendors_invoice_list ON vendors_invoice_list.id = vendors_invoice_payments_list.task_id
                 WHERE vendors_invoice_payments_list.deleted = 0 AND vendors_invoice_list.deleted = 0";
        $purchaseOrderPayments = $this->db->query($sql3)->getRow();

        $sql4 = "SELECT SUM(work_order_payments.amount) AS total_work_order_payments
                 FROM work_order_payments
                 LEFT JOIN work_orders ON work_orders.id = work_order_payments.work_order_id
                 WHERE work_order_payments.deleted = 0 AND work_orders.deleted = 0";
        $workOrderPayments = $this->db->query($sql4)->getRow();

        $info = new \stdClass();
        $info->income = $income ? $income->total_income : 0;
        $info->expenses = $expenses ? $expenses->total_expenses : 0;
        $info->purchase_order_payments = $purchaseOrderPayments ? $purchaseOrderPayments->total_purchase_payments : 0;
        $info->work_order_payments = $workOrderPayments ? $workOrderPayments->total_work_order_payments : 0;
        $info->total_expenses = $info->expenses + $info->purchase_order_payments + $info->work_order_payments;

        return $info;
    }

    public function getYearlyExpensesChart($year)
    {
        $expensesTable = $this->table;
        $sql = "SELECT SUM(total) AS total, MONTH(expense_date) AS month
                FROM $expensesTable
                WHERE deleted = 0 AND YEAR(expense_date) = $year
                GROUP BY MONTH(expense_date)";

        $query = $this->db->query($sql);
        return $query->getResult();
    }

    public function getVoucherExpenseDetails($itemName)
    {
        $itemsTable = 'voucher_expenses';

        $sql = "SELECT *
                FROM $itemsTable
                WHERE deleted = 0 AND estimate_id = '$itemName'
                ORDER BY id DESC
                LIMIT 1";

        $query = $this->db->query($sql);
        return $query->getRow();
    }

    public function getVoucherId($itemName, $incomeVoucherNo)
    {
        $itemsTable = 'voucher_expenses';
        $voucherTable = 'voucher';
        $voucherTypesTable = 'voucher_types';

        $sql = "SELECT *
                FROM $itemsTable
                LEFT JOIN $voucherTable ON $voucherTable.id = $itemsTable.estimate_id
                LEFT JOIN $voucherTypesTable ON $voucherTypesTable.id = $voucherTable.voucher_type_id
                WHERE $itemsTable.deleted = 0 AND $itemsTable.user_id = '$itemName'
                      AND $itemsTable.estimate_id NOT IN $incomeVoucherNo
                      AND ($voucherTypesTable.title LIKE '%EXPENSE%')
                      AND $voucherTable.status = 'approved_by_accounts'
                ORDER BY id";

        $query = $this->db->query($sql);
        return $query->getResult();
    }

    public function getClientVoucherId($itemName, $incomeVoucherNo)
    {
        $itemsTable = 'voucher_expenses';
        $voucherTable = 'voucher';
        $voucherTypesTable = 'voucher_types';

        $sql = "SELECT *
                FROM $itemsTable
                LEFT JOIN $voucherTable ON $voucherTable.id = $itemsTable.estimate_id
                LEFT JOIN $voucherTypesTable ON $voucherTypesTable.id = $voucherTable.voucher_type_id
                WHERE $itemsTable.deleted = 0 AND $itemsTable.i_represent = '$itemName'
                      AND $itemsTable.estimate_id NOT IN $incomeVoucherNo
                      AND ($voucherTypesTable.title LIKE '%EXPENSE%')
                      AND $voucherTable.status = 'approved_by_accounts'
                ORDER BY id";

        $query = $this->db->query($sql);
        return $query->getResult();
    }

    public function getVoucherIdForOthers($itemName, $incomeVoucherNo)
    {
        $itemsTable = 'voucher_expenses';
        $voucherTable = 'voucher';
        $voucherTypesTable = 'voucher_types';

        $sql = "SELECT *
                FROM $itemsTable
                LEFT JOIN $voucherTable ON $voucherTable.id = $itemsTable.estimate_id
                LEFT JOIN $voucherTypesTable ON $voucherTypesTable.id = $voucherTable.voucher_type_id
                WHERE $itemsTable.deleted = 0 AND $itemsTable.phone = '$itemName'
                      AND $voucherTypesTable.title LIKE '%EXPENSE%'
                      AND $itemsTable.estimate_id NOT IN $incomeVoucherNo
                      AND $voucherTable.status = 'approved_by_accounts'
                ORDER BY id";

        $query = $this->db->query($sql);
        return $query->getResult();
    }
}
