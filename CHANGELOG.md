# Changelog

## 0.2.0 (2026-05-08)

First functional release. Native OpenCart 4 payment extension wired
to PayHub.

### Features

- Admin extension at **Extensions → Payments → PayHub** with
  configuration: enable/disable, environment, custom base URL, API
  key, webhook secret, default PSP, debug log, sort order.
- Catalog payment method renderer collecting MSISDN (and birth-year
  for Sadad).
- After "Continue to PayHub", the order is created on PayHub via
  `POST /v1/payments`, mapping persisted in
  `oc_payhub_payment` (auto-created on first run).
- Custom flow page at `extension/payhub/payment/payhub|flow` renders
  the correct UI for each `next_action.type`:
  - **redirect** — auto-submitting GET/POST form.
  - **otp_required** — code input + AJAX submit.
  - **qr** — embedded QR + 3-second status poll.
  - **lightbox** — embeds the vendor lightbox script + status poll.
- Webhook receiver at `extension/payhub/payment/payhub|webhook`
  verifies `Hub-Signature` and updates order history with the
  appropriate OC order status.
- Standalone PHP HTTP client at
  `system/library/payhub/payhub_client.php` so the extension installs
  via the OC Extension Installer (zip upload) without requiring
  Composer on the merchant's server.
- Bilingual i18n stubs (`en-gb`, `ar-eg`).

### Known limitations

- **OpenCart 3 fallback** — the file layout follows OC4 conventions
  (`extension/payhub/...` under `upload/`); OC3 backport is on the
  v0.3 roadmap.
- **Refunds** — admin-side refund button is not yet wired (the API
  call is implemented but not exposed in the OC order admin); v0.3.
- **Order status mapping** — uses OpenCart's default status IDs
  (5=processing, 10=failed, 11=refunded, 14=expired). Merchant can
  remap by editing
  `upload/catalog/controller/extension/payhub/payment/payhub.php::orderStatusId`.

## 0.1.0 (2026-05-08)

Initial scaffolding. Extension manifest only.
