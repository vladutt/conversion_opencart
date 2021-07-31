<?php

class ControllerExtensionModuleConversion extends Controller
{
    private $error = [];

    public function index() {

        // get translates
        $this->load->language('extension/module/conversion');

        $this->load->model('setting/setting');
        $this->load->model('setting/event');
        $this->load->model('localisation/language');
        $this->load->model('design/layout');

        $this->document->setTitle($this->language->get('heading_title'));

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

            $this->model_setting_setting->editSetting('module_conversion', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));

        }

        /*
         * Populate the errors array
         */
        $data['error_warning'] = $this->error['warning'] ?? '';

        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/conversion', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['action'] = $this->url->link('extension/module/conversion', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['user_token'] = $this->session->data['user_token'];

        /*
         * Populate custom variables
         */
        $data['module_conversion_status'] = $this->config->get('module_conversion_status');
        $data['module_conversion_advertiser_code'] = $this->config->get('module_conversion_advertiser_code');
        $data['module_conversion_advertiser_key'] = $this->config->get('module_conversion_advertiser_key');
        $data['module_conversion_api_token'] = $this->config->get('module_conversion_api_token');
        $data['module_conversion_telephone'] = $this->config->get('module_conversion_telephone');

        // get the controller for sections.
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/conversion', $data));
    }

    /**
     * Validation method
     * @return bool
     */
    public function validate()
    {

        if (!$this->user->hasPermission('modify', 'extension/module/conversion')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (empty($this->request->post['module_conversion_advertiser_code'])) {
            $this->error['warning'] = $this->language->get('error_advertiser_code');
        }
        if (empty($this->request->post['module_conversion_advertiser_key'])) {
            $this->error['warning'] = $this->language->get('error_advertiser_key');
        }

        if (empty($this->request->post['module_conversion_api_token'])) {
            $this->error['warning'] = $this->language->get('error_api_token');
        }

        return !$this->error;
    }

    /**
     * Install module
     * @throws Exception
     */
    public function install()
    {
        $this->load->model('setting/setting');
        $this->load->model('design/layout');

        foreach ($this->model_design_layout->getLayouts() as $layout) {
            $this->db->query("
                INSERT INTO " . DB_PREFIX . "layout_module SET
                    layout_id = '{$layout['layout_id']}',
                    code = 'conversion',
                    position = 'content_bottom',
                    sort_order = '99'
                ");
        }

        $this->model_setting_setting->editSetting('module_conversion', [
            'module_conversion_status' => 0
        ]);

        $this->model_setting_event->addEvent(
            'conversion_add_order',
            'catalog/model/checkout/order/addOrderHistory/after',
            'extension/module/conversion/eventAddOrderHistory'
        );

    }

    /**
     * Uninstall module
     * @throws Exception
     */
    public function uninstall()
    {
        $this->load->model('setting/setting');
        $this->load->model('design/layout');

        $this->db->query("DELETE FROM " . DB_PREFIX . "layout_module WHERE code = 'conversion'");

        $this->model_setting_setting->deleteSetting('module_conversion');

        $this->model_setting_event->deleteEventByCode('conversion_add_order');
    }

}