<?php
namespace Opencart\Admin\Controller\Extension\Payhub\Payment;

class Payhub extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $this->load->language('extension/payhub/payment/payhub');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_payhub', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        $data['breadcrumbs'] = [
            ['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])],
            ['text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')],
            ['text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/payhub/payment/payhub', 'user_token=' . $this->session->data['user_token'])],
        ];

        $data['action'] = $this->url->link('extension/payhub/payment/payhub', 'user_token=' . $this->session->data['user_token']);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

        foreach (['payment_payhub_status', 'payment_payhub_environment', 'payment_payhub_base_url',
                  'payment_payhub_api_key', 'payment_payhub_webhook_secret_hex',
                  'payment_payhub_default_psp', 'payment_payhub_debug', 'payment_payhub_sort_order'] as $key) {
            $data[$key] = $this->request->post[$key] ?? $this->config->get($key) ?? '';
        }

        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payhub/payment/payhub', $data));
    }

    public function install(): void {
        // Hook for table/index creation if needed.
    }

    public function uninstall(): void {}

    private function validate(): bool {
        if (!$this->user->hasPermission('modify', 'extension/payhub/payment/payhub')) {
            $this->error['warning'] = $this->language->get('error_permission');
            return false;
        }
        $key = (string)($this->request->post['payment_payhub_api_key'] ?? '');
        if ($key !== '' && !str_starts_with($key, 'phk_')) {
            $this->error['warning'] = $this->language->get('error_api_key');
            return false;
        }
        return empty($this->error);
    }
}
