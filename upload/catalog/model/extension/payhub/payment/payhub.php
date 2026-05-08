<?php
namespace Opencart\Catalog\Model\Extension\Payhub\Payment;

class Payhub extends \Opencart\System\Engine\Model {
    public function getMethod(array $address): array {
        $this->load->language('extension/payhub/payment/payhub');
        if (!$this->config->get('payment_payhub_status')) {
            return [];
        }
        return [
            'code'       => 'payhub',
            'title'      => 'PayHub — Sadad / Moamalat / Mobicash / T-Lync',
            'sort_order' => $this->config->get('payment_payhub_sort_order') ?? 50,
            'terms'      => '',
        ];
    }

    public function savePaymentMapping(int $orderId, string $paymentId, string $psp): void {
        $this->ensureTable();
        $this->db->query("REPLACE INTO `" . DB_PREFIX . "payhub_payment` (order_id, payment_id, psp, created_at) VALUES (" . (int)$orderId . ", '" . $this->db->escape($paymentId) . "', '" . $this->db->escape($psp) . "', NOW())");
    }

    public function getPaymentMapping(int $orderId): ?array {
        $this->ensureTable();
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "payhub_payment` WHERE order_id = " . (int)$orderId . " LIMIT 1");
        return $q->num_rows ? $q->row : null;
    }

    public function findByPaymentId(string $paymentId): ?array {
        $this->ensureTable();
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "payhub_payment` WHERE payment_id = '" . $this->db->escape($paymentId) . "' LIMIT 1");
        return $q->num_rows ? $q->row : null;
    }

    private function ensureTable(): void {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "payhub_payment` (" .
            "order_id INT(11) NOT NULL," .
            "payment_id VARCHAR(64) NOT NULL," .
            "psp VARCHAR(32) NOT NULL," .
            "created_at DATETIME NOT NULL," .
            "PRIMARY KEY (order_id)," .
            "KEY (payment_id)" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
