<?php

namespace App\Controllers;

use CodeIgniter\I18n\Time; // Import Time class for date handling
use PhpOffice\PhpSpreadsheet\IOFactory; // Import IOFactory for spreadsheet handling

class Personal_bank_statement extends BaseController
{
    protected $personalBankStatementModel;

    public function __construct()
    {
        parent::__construct();
        $this->personalBankStatementModel = new \App\Models\Personal_bank_statement_model(); // Adjust namespace as per your model
        $this->load = service('load'); // Load service helper
    }

    public function yearly()
    {
        return view("personal_bank_statement/yearly_bank_statement"); // Adjust view() for CI4 usage
    }

    // Load custom expenses list
    public function custom()
    {
        return view("personal_bank_statement/custom_bank_statement"); // Adjust view() for CI4 usage
    }

    public function modal_form()
    {
        $this->load->validate_submitted_data(array(
            "id" => "numeric"
        ));

        $view_data['model_info'] = $this->personalBankStatementModel->getOne($this->request->getPost('id'));
        return view('personal_bank_statement/modal_form', $view_data); // Adjust view() for CI4 usage
    }

    public function save()
    {
        $this->load->validate_submitted_data(array(
            "id" => "numeric"
        ));

        $id = $this->request->getPost('id');
        $data = array(
            "remark" => $this->request->getPost('remark')
        );

        $save_id = $this->personalBankStatementModel->save($data, $id);
        if ($save_id) {
            return json_encode(array("success" => true, "data" => $this->_row_data($save_id), 'id' => $save_id, 'message' => lang('record_saved'))); // Adjust for CI4 return response
        } else {
            return json_encode(array("success" => false, 'message' => lang('error_occurred'))); // Adjust for CI4 return response
        }
    }

    public function list_data()
    {
        $user_id = $this->request->getPost("user_id");
        if ($user_id) {
            $options = array(
                "start_date" => $this->request->getPost("start_date"),
                "end_date" => $this->request->getPost("end_date"),
                "user_id" => $user_id
            );
        } else {
            $options = array(
                "start_date" => $this->request->getPost("start_date"),
                "end_date" => $this->request->getPost("end_date")
            );
        }

        $list_data = $this->personalBankStatementModel->getDetails($options)->getResult();
        $result = array();
        foreach ($list_data as $data) {
            $result[] = $this->_make_item_row($data);
        }

        return json_encode(array("data" => $result)); // Adjust for CI4 return response
    }

    private function _row_data($id)
    {
        $options = array("id" => $id);
        $data = $this->personalBankStatementModel->getDetails($options)->getRow();
        return $this->_make_item_row($data);
    }

    private function _make_item_row($data)
    {
        return array(
            $data->BankName,
            $data->ValueName,
            $data->PostDate,
            nl2br($data->RemitterBranch),
            nl2br($data->Description),
            $data->ChequeNo,
            $data->TransactionId,
            $data->DebitAmount,
            $data->CreditAmount,
            $data->Balance,
            $data->remark,
            modal_anchor(get_uri("personal_bank_statement/modal_form"), "<i class='fa fa-pencil'></i>", array("class" => "edit", "title" => lang('edit_remark'), "data-post-id" => $data->id))
                . js_anchor("<i class='fa fa-times fa-fw'></i>", array('title' => lang('delete'), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("personal_bank_statement/delete"), "data-action" => "delete"))
        );
    }

    public function delete()
    {
        $this->load->validate_submitted_data(array(
            "id" => "numeric|required"
        ));

        $id = $this->request->getPost('id');
        if ($this->request->getPost('undo')) {
            if ($this->personalBankStatementModel->delete($id, true)) {
                return json_encode(array("success" => true, "data" => $this->_row_data($id), "message" => lang('record_undone'))); // Adjust for CI4 return response
            } else {
                return json_encode(array("success" => false, lang('error_occurred'))); // Adjust for CI4 return response
            }
        } else {
            if ($this->personalBankStatementModel->delete($id)) {
                return json_encode(array("success" => true, 'message' => lang('record_deleted'))); // Adjust for CI4 return response
            } else {
                return json_encode(array("success" => false, 'message' => lang('record_cannot_be_deleted'))); // Adjust for CI4 return response
            }
        }
    }

    public function import()
    {
        if (isset($_FILES["file"]["name"])) {
            $path = $_FILES["file"]["tmp_name"];
            $spreadsheet = IOFactory::load($path); // Adjust for CI4 usage

            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $highestRow = $worksheet->getHighestRow();
                for ($row = 21; $row <= ($highestRow - 4); $row++) {
                    $customer_name = $worksheet->getCellByColumnAndRow(0, $row)->getValue();
                    $address = $worksheet->getCellByColumnAndRow(1, $row)->getValue();
                    $city = $worksheet->getCellByColumnAndRow(2, $row)->getValue();
                    $postal_code = $worksheet->getCellByColumnAndRow(3, $row)->getValue();
                    $country = $worksheet->getCellByColumnAndRow(4, $row)->getValue();
                    $countrya = $worksheet->getCellByColumnAndRow(5, $row)->getValue();
                    $countrys = $worksheet->getCellByColumnAndRow(6, $row)->getValue();
                    $countryd = $worksheet->getCellByColumnAndRow(7, $row)->getValue();

                    $origDate = $customer_name;
                    $date = str_replace('/', '-', $origDate);
                    $newDate = Time::createFromFormat('Y-m-d', date("Y-m-d", strtotime($date))); // Adjust for CI4 Time class

                    $data[] = array(
                        'BankName' => 'Indian Bank',
                        'ValueName' => $newDate,
                        'PostDate' => $address,
                        'RemitterBranch' => $city,
                        'Description' => $postal_code,
                        'ChequeNo' => $country,
                        'DebitAmount' => $countrya,
                        'CreditAmount' => $countrys,
                        'Balance' => $countryd,
                        'user_id' => $this->request->getPost("user_id")
                    );
                }
                $bn = $worksheet->getCellByColumnAndRow(3, 1)->getValue();
            }

            if ($bn == 'INDIAN BANK') {
                $this->personalBankStatementModel->insertBatch($data); // Adjust for CI4 insertBatch()
                return json_encode(array("success" => true));
            }
        }
    }

    public function import_icici()
    {
        if (isset($_FILES["file"]["name"])) {
            $path = $_FILES["file"]["tmp_name"];
            $spreadsheet = IOFactory::load($path); // Adjust for CI4 usage

            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $highestRow = $worksheet->getHighestRow();
                for ($row = 8; $row <= ($highestRow); $row++) {
                    $transaction_id = $worksheet->getCellByColumnAndRow(1, $row)->getValue();
                    $customer_name = $worksheet->getCellByColumnAndRow(2, $row)->getValue();
                    $address = $worksheet->getCellByColumnAndRow(3, $row)->getValue();
                    $city = $worksheet->getCellByColumnAndRow(9, $row)->getValue();
                    $postal_code = $worksheet->getCellByColumnAndRow(5, $row)->getValue();
                    $country = $worksheet->getCellByColumnAndRow(4, $row)->getValue();
                    $countrya = $worksheet->getCellByColumnAndRow(6, $row)->getValue();
                    $countrys = $worksheet->getCellByColumnAndRow(7, $row)->getValue();
                    $countryd = $worksheet->getCellByColumnAndRow(8, $row)->getValue();

                    $origDate = $customer_name;
                    $date = str_replace('/', '-', $origDate);
                    $newDate = Time::createFromFormat('Y-m-d', date("Y-m-d", strtotime($date))); // Adjust for CI4 Time class

                    if ($countrya == 'DR') {
                        $data[] = array(
                            'BankName' => 'ICICI Bank',
                            'TransactionId' => $transaction_id,
                            'ValueName' => $newDate,
                            'PostDate' => $address,
                            'RemitterBranch' => $city,
                            'Description' => $postal_code,
                            'ChequeNo' => $country,
                            'DebitAmount' => $countrys,
                            'CreditAmount' => 0,
                            'Balance' => $countryd,
                            'user_id' => $this->request->getPost("user_id")
                        );
                    } else if ($countrya == 'CR') {
                        $data[] = array(
                            'BankName' => 'ICICI Bank',
                            'TransactionId' => $transaction_id,
                            'ValueName' => $newDate,
                            'PostDate' => $address,
                            'RemitterBranch' => $city,
                            'Description' => $postal_code,
                            'ChequeNo' => $country,
                            'CreditAmount' => $countrys,
                            'DebitAmount' => 0,
                            'Balance' => $countryd,
                            'user_id' => $this->request->getPost("user_id")
                        );
                    }
                }
                $bn = $worksheet->getCellByColumnAndRow(0, 1)->getValue();
            }

            if ($bn == 'DETAILED STATEMENT') {
                $this->personalBankStatementModel->insertBatch($data); // Adjust for CI4 insertBatch()
                return json_encode(array("success" => true));
            }
        }
    }

    public function import_hdfc()
    {
        if (isset($_FILES["file"]["name"])) {
            $path = $_FILES["file"]["tmp_name"];
            $spreadsheet = IOFactory::load($path); // Adjust for CI4 usage

            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $highestRow = $worksheet->getHighestRow();
                for ($row = 23; $row <= ($highestRow - 18); $row++) {
                    $p_date = $worksheet->getCellByColumnAndRow(0, $row)->getValue();
                    $desc = $worksheet->getCellByColumnAndRow(1, $row)->getValue();
                    $cheque = $worksheet->getCellByColumnAndRow(2, $row)->getValue();
                    $value_date = $worksheet->getCellByColumnAndRow(3, $row)->getValue();
                    $debit = $worksheet->getCellByColumnAndRow(4, $row)->getValue();
                    $credit = $worksheet->getCellByColumnAndRow(5, $row)->getValue();
                    $balance = $worksheet->getCellByColumnAndRow(6, $row)->getValue();

                    $origDate = $value_date;
                    $d = explode("/", $origDate);
                    $newDate = Time::createFromFormat('Y-m-d', date('Y-m-d', strtotime($d[2] . "-" . $d[1] . "-" . $d[0]))); // Adjust for CI4 Time class

                    $data[] = array(
                        'BankName' => 'HDFC Bank',
                        'ValueName' => $newDate,
                        'PostDate' => $p_date,
                        'Description' => $desc,
                        'ChequeNo' => $cheque,
                        'DebitAmount' => $debit,
                        'CreditAmount' => $credit,
                        'Balance' => $balance,
                        'user_id' => $this->request->getPost("user_id")
                    );
                }
                $bn = $worksheet->getCellByColumnAndRow(1, 21)->getValue();
            }

            if ($bn == 'Narration') {
                $this->personalBankStatementModel->insertBatch($data); // Adjust for CI4 insertBatch()
                return json_encode(array("success" => true));
            }
        }
    }
}
