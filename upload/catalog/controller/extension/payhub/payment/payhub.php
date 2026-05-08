<?php
namespace Opencart\Catalog\Controller\Extension\Payhub\Payment;

require_once DIR_SYSTEM . 'library/payhub/payhub_client.php';

use Opencart\System\Library\Payhub\PayhubClient;

class Payhub extends \Opencart\System\Engine\Controller {
    public function index(): string {
        $this->load->language('extension/payhub/payment/payhub');
        $this->load->model('checkout/order');
        $order_id = (int) $this->session->data['order_id'];
        $order = $this->model_checkout_order->getOrder($order_id);
        if (!$order) {
            return '';
        }

        $data['action']   = $this->url->link('extension/payhub/payment/payhub|confirm', '', true);
        $data['psp']      = $this->config->get('payment_payhub_default_psp') ?: 'moamalat';
        $data['order_id'] = $order_id;
        return $this->load->view('extension/payhub/payment/payhub', $data);
    }

    public function confirm(): void {
        $this->load->language('extension/payhub/payment/payhub');
        $this->load->model('checkout/order');
        $order_id = (int) ($this->session->data['order_id'] ?? 0);
        $order = $this->model_checkout_order->getOrder($order_id);
        if (!$order) {
            $this->response->redirect($this->url->link('checkout/cart', '', true));
            return;
        }

        $apiKey = (string) $this->config->get('payment_payhub_api_key');
        if ($apiKey === '' || !str_starts_with($apiKey, 'phk_')) {
            $this->session->data['error'] = 'PayHub is not configured.';
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return;
        }
        $client = new PayhubClient($apiKey, $this->baseUrl());

        $msisdn = preg_replace('/\D/', '', (string) ($this->request->post['payhub_msisdn'] ?? ''));
        if ($msisdn !== '' && !str_starts_with($msisdn, '218')) {
            $msisdn = '218' . ltrim($msisdn, '0');
        }
        $birth_year = (int) ($this->request->post['payhub_birth_year'] ?? 0);

        $customer = [];
        if ($msisdn !== '')      $customer['msisdn'] = $msisdn;
        if ($birth_year > 1900)  $customer['birth_year'] = $birth_year;
        if (!empty($order['email'])) $customer['email'] = $order['email'];
        $name = trim(($order['firstname'] ?? '') . ' ' . ($order['lastname'] ?? ''));
        if ($name !== '') $customer['name'] = $name;

        $request = [
            'psp'                => (string) $this->config->get('payment_payhub_default_psp'),
            'amount_minor'       => $this->amountToMinor((float) $order['total'], (string) $order['currency_code']),
            'currency'           => $order['currency_code'],
            'merchant_order_ref' => (string) $order_id,
            'customer'           => $customer,
            'return_urls' => [
                'success_url' => $this->url->link('checkout/success', '', true),
                'cancel_url'  => $this->url->link('checkout/cart', '', true),
            ],
            'metadata' => ['oc_order_id' => (string) $order_id],
        ];

        try {
            $payment = $client->createPayment($request, 'oc-' . $order_id);
        } catch (\Throwable $e) {
            $this->session->data['error'] = 'Payment could not be initiated: ' . $e->getMessage();
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return;
        }

        $this->load->model('extension/payhub/payment/payhub');
        $this->model_extension_payhub_payment_payhub->savePaymentMapping($order_id, (string) $payment['id'], (string) ($payment['psp'] ?? ''));

        $this->session->data['payhub_next_action'] = $payment['next_action'] ?? null;
        $next = $payment['next_action'] ?? null;
        if (is_array($next) && ($next['type'] ?? '') === 'redirect' && (strtoupper((string)($next['method'] ?? 'GET')) === 'GET') && empty($next['fields'])) {
            $this->response->redirect((string) $next['url']);
            return;
        }
        $this->response->redirect($this->url->link('extension/payhub/payment/payhub|flow', 'order_id=' . $order_id, true));
    }

    public function flow(): void {
        $this->load->language('extension/payhub/payment/payhub');
        $this->load->model('checkout/order');
        $order_id = (int) ($this->request->get['order_id'] ?? 0);
        $order = $this->model_checkout_order->getOrder($order_id);
        if (!$order) {
            $this->response->redirect($this->url->link('checkout/cart', '', true));
            return;
        }
        $next = $this->session->data['payhub_next_action'] ?? null;
        if (!$next) {
            $this->response->redirect($this->url->link('checkout/cart', '', true));
            return;
        }

        $data['kind'] = (string)($next['type'] ?? '');
        $data['next'] = $next;
        $data['order_id'] = $order_id;
        $data['cancel_url'] = $this->url->link('checkout/cart', '', true);
        $data['poll_url']   = $this->url->link('extension/payhub/payment/payhub|status', '&order_id=' . $order_id, true);
        $data['otp_url']    = $this->url->link('extension/payhub/payment/payhub|submitOtp', '', true);

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $data['content_bottom'] = '';

        $this->response->setOutput($this->load->view('extension/payhub/payment/payhub_flow', $data));
    }

    public function submitOtp(): void {
        $this->load->model('checkout/order');
        $this->load->model('extension/payhub/payment/payhub');
        $body = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $order_id = (int) ($body['order_id'] ?? 0);
        $code     = (string) ($body['code'] ?? '');
        $row = $this->model_extension_payhub_payment_payhub->getPaymentMapping($order_id);
        if (!$row || !preg_match('/^\d{4,8}$/', $code)) {
            $this->json(['error' => 'invalid_input']);
            return;
        }
        $client = new PayhubClient((string) $this->config->get('payment_payhub_api_key'), $this->baseUrl());
        try {
            $payment = $client->confirmOtp($row['payment_id'], $code, 'oc-otp-' . $order_id . '-' . time());
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()]);
            return;
        }
        if (($payment['status'] ?? '') === 'succeeded') {
            $this->model_checkout_order->addHistory($order_id, $this->orderStatusId('processing'), 'PayHub: payment ' . $row['payment_id'] . ' succeeded.', true);
            $this->json(['redirect' => $this->url->link('checkout/success', '', true)]);
            return;
        }
        $this->json(['status' => $payment['status'] ?? 'pending']);
    }

    public function status(): void {
        $this->load->model('extension/payhub/payment/payhub');
        $order_id = (int) ($this->request->get['order_id'] ?? 0);
        $row = $this->model_extension_payhub_payment_payhub->getPaymentMapping($order_id);
        if (!$row) { $this->json(['error' => 'no_payment']); return; }
        $client = new PayhubClient((string) $this->config->get('payment_payhub_api_key'), $this->baseUrl());
        try {
            $payment = $client->retrieve($row['payment_id']);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()]);
            return;
        }
        $payload = ['status' => $payment['status'] ?? 'pending'];
        if (($payment['status'] ?? '') === 'succeeded') {
            $payload['redirect'] = $this->url->link('checkout/success', '', true);
        }
        $this->json($payload);
    }

    public function webhook(): void {
        $this->load->model('checkout/order');
        $this->load->model('extension/payhub/payment/payhub');
        $body = (string) file_get_contents('php://input');
        $sig  = (string) ($_SERVER['HTTP_HUB_SIGNATURE'] ?? '');
        $secretHex = (string) $this->config->get('payment_payhub_webhook_secret_hex');
        $secret = $secretHex !== '' ? @hex2bin($secretHex) : '';
        if (!$secret) { $this->jsonStatus(503, ['error' => 'webhook_secret_not_configured']); return; }

        try {
            $event = PayhubClient::verifyWebhook($secret, $body, $sig);
        } catch (\Throwable $e) {
            $this->jsonStatus(401, ['error' => 'invalid_signature']);
            return;
        }
        $row = $this->model_extension_payhub_payment_payhub->findByPaymentId((string) ($event['payment_id'] ?? ''));
        if (!$row) {
            $this->jsonStatus(200, ['received' => true]);
            return;
        }
        $orderId = (int) $row['order_id'];
        switch ((string) ($event['type'] ?? '')) {
            case 'payment.succeeded':
                $this->model_checkout_order->addHistory($orderId, $this->orderStatusId('processing'), 'PayHub: payment succeeded.', true);
                break;
            case 'payment.failed':
                $this->model_checkout_order->addHistory($orderId, $this->orderStatusId('failed'), 'PayHub: payment failed.', false);
                break;
            case 'payment.expired':
                $this->model_checkout_order->addHistory($orderId, $this->orderStatusId('expired'), 'PayHub: payment expired.', false);
                break;
            case 'payment.refunded':
                $this->model_checkout_order->addHistory($orderId, $this->orderStatusId('refunded'), 'PayHub: payment refunded.', false);
                break;
        }
        $this->jsonStatus(200, ['received' => true]);
    }

    private function baseUrl(): string {
        $env = (string) ($this->config->get('payment_payhub_environment') ?: 'production');
        if ($env === 'custom') {
            $u = trim((string) $this->config->get('payment_payhub_base_url'));
            return $u !== '' ? rtrim($u, '/') : 'https://app.payhub.ly';
        }
        return $env === 'sandbox' ? 'https://demo.payhub.ly' : 'https://app.payhub.ly';
    }

    private function amountToMinor(float $amount, string $currency): int {
        $code = strtoupper($currency);
        $mul = match (true) {
            in_array($code, ['LYD', 'BHD', 'KWD', 'OMR', 'TND', 'IQD', 'JOD'], true) => 1000,
            in_array($code, ['JPY', 'KRW', 'VND'], true)                              => 1,
            default                                                                    => 100,
        };
        return (int) round($amount * $mul);
    }

    private function orderStatusId(string $alias): int {
        // OC stores status_id by name; default to processing/canceled with sane fallbacks.
        $defaults = ['processing' => 5, 'failed' => 10, 'expired' => 14, 'refunded' => 11];
        return $defaults[$alias] ?? 1;
    }

    private function json(array $body): void {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput((string) json_encode($body));
    }

    private function jsonStatus(int $status, array $body): void {
        http_response_code($status);
        $this->json($body);
    }
}
