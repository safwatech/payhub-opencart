# PayHub for OpenCart

Native OpenCart 4 payment extension for [PayHub](https://payhub.ly).

> **v0.1.0** is the scaffolding release — extension manifest only.
> The full native gateway with all four `next_action` flows lands in
> **v0.2.0** (Phase 5 of the [plugin plan](../README.md#status)).

## Install (when v0.2.0 ships)

1. Download `payhub.ocmod.zip` from
   [GitHub Releases](https://github.com/safwatech/payhub-opencart/releases).
2. **Admin → Extensions → Installer** → upload the zip.
3. **Admin → Extensions → Extensions → Payments** → install **PayHub** →
   **Edit** to configure `phk_…` API key, webhook secret, default PSP.

OpenCart 3 fallback: install via vQmod or direct file copy from `upload/`.

## Compatibility

- OpenCart 4.0+
- PHP 8.1+

## Roadmap

- v0.2.0 — Tier A native gateway (OTP / redirect / QR / lightbox),
  admin refunds, Arabic + RTL.
- v0.3.0 — OpenCart 3 backport.
- v0.4.0 — OpenCart Extension Store submission.
