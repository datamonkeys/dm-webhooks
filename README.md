# DM Webhooks for WooCommerce

Send WooCommerce order events to any webhook — Zapier, Make, n8n, HubSpot, GoHighLevel, ActiveCampaign or your own ERP — with signed payloads, automatic retries and a delivery log.

## Features

- **Events:** `order.created`, `order.paid`, `order.status_changed`, `order.refunded`
- **Multiple endpoints**, each subscribed to its own events (WooCommerce → DM Webhooks)
- **HMAC-SHA256 signature** in `X-DMWH-Signature` (`sha256=<hex>`)
- **Async delivery** via WP-Cron, so checkout is never slowed down by a third party
- **Exponential backoff retries** (1, 2, 4, 8… minutes, configurable)
- **Delivery log** in a custom table, shown on the settings page
- **REST test endpoint** `POST /wp-json/dm-webhooks/v1/test` to wire integrations before real orders exist
- CRM-friendly payload: customer, line items (SKU, qty, totals) and **UTM attribution** from WooCommerce order attribution
- **HPOS compatible**, filter `dmwh_payload` and action `dmwh_delivered` for extensions

## Payload

```json
{
  "id": "6f1c…",
  "event": "order.paid",
  "created_at": "2026-10-09T14:03:00+00:00",
  "site": "https://shop.example.com",
  "data": {
    "order_id": 1234, "number": "1234", "status": "processing",
    "currency": "BRL", "total": 249.9, "payment_method": "pix",
    "customer": { "email": "ana@example.com", "first_name": "Ana", "phone": "…" },
    "items": [{ "sku": "TSHIRT-M", "name": "T-shirt", "quantity": 2, "total": 199.8 }],
    "utm": { "source": "google", "medium": "cpc", "campaign": "black-friday" }
  }
}
```

## Verifying the signature (Node.js)

```js
const crypto = require('crypto');
const expected = 'sha256=' + crypto.createHmac('sha256', SECRET).update(rawBody).digest('hex');
const valid = crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(req.headers['x-dmwh-signature']));
```

## Install

1. Copy the folder to `wp-content/plugins/dm-webhooks` (or upload the zip).
2. Activate **DM Webhooks for WooCommerce**.
3. Go to **WooCommerce → DM Webhooks**, add your endpoint URLs and a signing secret.

Requires WordPress 6.0+, PHP 7.4+, WooCommerce 7.0+.

## License

GPL-2.0-or-later. Built by [Louis Perner](https://louisperner.dev) / [Datamonkeys](https://datamonkeys.ai).
