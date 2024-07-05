<?php

namespace App\Controllers;
use App\Models\ChequeStatusModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;
class ChequeStatus extends Controller
{
    use ResponseTrait;

    protected $chequeStatusModel;

    public function __construct()
    {
        $this->chequeStatusModel = new ChequeStatusModel();
        $this->accessOnlyAdmin(); // Assuming accessOnlyAdmin() is a method defined in BaseController or similar.
    }

    public function index()
    {
        return view('cheque_status/index'); // Using view() helper to render views in CI4.
    }

    public function modal_form()
    {
        $id = $this->request->getPost('id');
        $viewData['model_info'] = $this->chequeStatusModel->getOne($id);
        return view('cheque_status/modal_form', $viewData);
    }
    public function save()
    {
        $id = $this->request->getPost('id');
        $data = [
            "title" => $this->request->getPost('title'),
            "color" => $this->request->getPost('color')
        ];

        if (!$id) {
            $maxSortValue = $this->chequeStatusModel->getMaxSortValue();
            $data["sort"] = $maxSortValue * 1 + 1;
        }

        $saveId = $this->chequeStatusModel->save($data, $id);
        if ($saveId) {
            return $this->respond([
                "success" => true,
                "data" => $this->_rowData($saveId),
                'id' => $saveId,
                'message' => lang('record_saved')
            ]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

    public function update_field_sort_values($id = 0)
    {
        $sortValues = $this->request->getPost("sort_values");
        if ($sortValues) {
            $sortArray = explode(",", $sortValues);

            foreach ($sortArray as $value) {
                $sortItem = explode("-", $value);
                $id = get_array_value($sortItem, 0);
                $sort = get_array_value($sortItem, 1);

                $data = ["sort" => $sort];
                $this->chequeStatusModel->save($data, $id);
            }
        }
    }

    public function delete()
    {
        $id = $this->request->getPost('id');
        if ($this->request->getPost('undo')) {
            if ($this->chequeStatusModel->delete($id, true)) {
                return $this->respond([
                    "success" => true,
                    "data" => $this->_rowData($id),
                    "message" => lang('record_undone')
                ]);
            } else {
                return $this->fail(lang('error_occurred'));
            }
        } else {
            if ($this->chequeStatusModel->delete($id)) {
                return $this->respond(["success" => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->fail(lang('record_cannot_be_deleted'));
            }
        }
    }

    public function list_data()
    {
        $listData = $this->chequeStatusModel->getDetails()->getResult();
        $result = [];
        foreach ($listData as $data) {
            $result[] = $this->_makeRow($data);
        }
        return $this->respond(["data" => $result]);
    }

    private function _rowData($id)
    {
        $options = ["id" => $id];
        $data = $this->chequeStatusModel->getDetails($options)->getRow();
        return $this->_makeRow($data);
    }

    private function _makeRow($data)
    {
        $edit = $delete = '';

        if (!$data->key_name) {
            $edit = view('cheque_status/modal_form', ['id' => $data->id]);
            $delete = view('cheque_status/delete', ['id' => $data->id]);
        }

        return [
            $data->sort,
            "<div class='pt10 pb10 field-row' data-id='$data->id'><i class='fa fa-bars pull-left move-icon'></i> <span style='background-color:$data->color' class='color-tag pull-left'></span>" . ($data->key_name ? lang($data->key_name) : $data->title) . '</div>',
            $edit . $delete
        ];
    }
}
