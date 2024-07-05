<?php

namespace App\Controllers;

use App\Models\TermsConditionsTemplatesModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;
use CodeIgniter\Validation\Validation;


class Terms_conditions_templates extends BaseController
{
    use ResponseTrait;

    protected $termsConditionsTemplatesModel;

    public function __construct()
    {
        $this->termsConditionsTemplatesModel = new TermsConditionsTemplatesModel();
    }

    public function index()
    {
        return view('terms_conditions_templates/index');
    }

    public function modal_form()
    {
        helper(['form', 'url']);

        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'numeric'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->fail($validation->getErrors());
        }

        $viewData['model_info'] = $this->termsConditionsTemplatesModel->find($this->request->getPost('id'));
        return view('terms_conditions_templates/modal_form', $viewData);
    }

    public function save()
    {
        helper('form');

        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'required|numeric',
            'template_name' => 'required',
            'custom_message' => 'required'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->fail($validation->getErrors());
        }

        $id = $this->request->getPost('id');
        $data = [
            'template_name' => $this->request->getPost('template_name'),
            'custom_message' => decode_ajax_post_data($this->request->getPost('custom_message'))
        ];

        $saveId = $this->termsConditionsTemplatesModel->save($data, $id);
        if ($saveId) {
            return $this->respondCreated(['success' => true, 'id' => $saveId, 'message' => lang('record_saved')]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

    public function save_title()
    {
        $id = $this->request->getPost('id');
        $data = [
            'template_name' => $this->request->getPost('template_name')
        ];

        $saveId = $this->termsConditionsTemplatesModel->save($data, $id);
        if ($saveId) {
            return $this->respond(['success' => true, 'id' => $saveId, 'message' => lang('record_saved')]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

    public function delete()
    {
        helper('form');

        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'required|numeric'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->fail($validation->getErrors());
        }

        $id = $this->request->getPost('id');
        $undo = $this->request->getPost('undo');

        if ($undo) {
            if ($this->termsConditionsTemplatesModel->delete($id, true)) {
                return $this->respond(['success' => true, 'data' => $this->_row_data($id), 'message' => lang('record_undone')]);
            } else {
                return $this->fail(lang('error_occurred'));
            }
        } else {
            if ($this->termsConditionsTemplatesModel->delete($id)) {
                return $this->respond(['success' => true, 'message' => lang('record_deleted')]);
            } else {
                return $this->fail(lang('record_cannot_be_deleted'));
            }
        }
    }

    public function restore_to_default()
    {
        helper('form');

        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'required|numeric'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->fail($validation->getErrors());
        }

        $templateId = $this->request->getPost('id');

        $data = [
            'custom_message' => ''
        ];

        $saveId = $this->termsConditionsTemplatesModel->save($data, $templateId);
        if ($saveId) {
            $defaultMessage = $this->termsConditionsTemplatesModel->find($saveId)['default_message'];
            return $this->respond(['success' => true, 'data' => $defaultMessage, 'message' => lang('template_restored')]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

    public function to_default()
    {
        helper('form');

        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => 'required|numeric'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->fail($validation->getErrors());
        }

        $setIdZero = $this->termsConditionsTemplatesModel->setZero();

        $templateId = $this->request->getPost('id');

        $data = [
            'is_default' => 1
        ];

        $saveId = $this->termsConditionsTemplatesModel->save($data, $templateId);
        if ($saveId) {
            $defaultMessage = $this->termsConditionsTemplatesModel->find($saveId)['default_message'];
            return $this->respond(['success' => true, 'data' => $defaultMessage, 'message' => lang('template_restored')]);
        } else {
            return $this->fail(lang('error_occurred'));
        }
    }

    public function list_data()
    {
        $list = [];
        $templateNames = $this->termsConditionsTemplatesModel->findAll();

        foreach ($templateNames as $templateName) {
            $default = $templateName['is_default'] ? '<span class="label label-success large">Default</span>' : '';
            $list[] = ['<span class="template-row" data-name="' . $templateName['id'] . '">' . $templateName['template_name'] . '</span> ' . $default];
        }

        return $this->respond(['data' => $list]);
    }

    public function form($templateName = '')
    {
        $viewData['model_info'] = $this->termsConditionsTemplatesModel->where('id', $templateName)->first();
        return view('terms_conditions_templates/form', $viewData);
    }

    private function _row_data($id)
    {
        $options = ['id' => $id];
        $data = $this->termsConditionsTemplatesModel->where($options)->first();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $default = $data['is_default'] ? '<span class="label label-success large">Default</span>' : '';
        return ['<span class="template-row" data-name="' . $data['id'] . '">' . $data['template_name'] . '</span> ' . $default];
    }

}

