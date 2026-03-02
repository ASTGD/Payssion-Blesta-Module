# Payssion-Blesta-Module
Unofficial Payssion payment gateway for Blesta

A Non-Merchant Payssion payment gateway module for Blesta.

This module allows Blesta to accept payments through Payssion Hosted Checkout, supporting multiple payment methods (pm_id) configured in a single gateway instance.
## 🚀 Features

- Hosted (Non-Merchant) integration
- Secure server-side signature generation
- Payssion IPN (notify_url) handling
- Automatic invoice payment application
- Debug logging support
- Multiple payment method support (comma-separated `pm_id`)
- Each payment method displayed separately to the client
- Clean and simple configuration

---

## 📦 Requirements

- Blesta 4.x or 5.x
- PHP 7.2+
- Active Payssion merchant account
- Payssion API Key
- Payssion Secret Key

---

## 📥 Installation

1. Download or clone this repository.
2. Upload the `payssion` folder to:
   /components/gateways/nonmerchant/
   Final structure should be:
   /components/gateways/nonmerchant/payssion/
3. Log in to Blesta Admin.
4. Navigate to: Settings → Company → Payment Gateways → Available
5. Find **Payssion** and click **Install**.
6. Click **Manage** to configure the gateway.

---

## ⚙️ Configuration

Fill in the following fields:

- **API Key**
- **Secret Key**
- **Payment Method ID(s) (pm_id)**

### Multiple Payment Methods

You can define multiple payment methods separated by comma:
bitcoin, alipay_cn, wechatpay_cn

Each method will appear as a separate payment option in the client area.

When the client selects one, only that specific `pm_id` is sent to Payssion.

⚠️ Make sure all listed payment methods are enabled in your Payssion account.

---

## 🔄 Payment Flow

1. Client selects Payssion as payment method.
2. If multiple `pm_id` values are configured, client chooses one.
3. Client is redirected to Payssion hosted checkout.
4. Payssion processes payment.
5. Payssion sends server-to-server callback (`notify_url`).
6. Blesta validates signature and applies payment to invoice.

---

## 🔐 Signature Validation

This module verifies Payssion callback signatures using:
md5(api_key|pm_id|amount|currency|track_id|sub_track_id|state|secret_key)

Payments are applied only if signature validation succeeds.

---

## 🧪 Testing

### Enable Debug Logging

In gateway settings, enable debug logging.

Then check: Tools → Logs → Gateway

You should see:

- Outgoing payment request data
- Incoming Payssion callback payload
- Signature validation result

---

## ❗ Common Issues

### “pm_id not found”

This happens when:

- The payment method is not enabled in Payssion.
- The `pm_id` is misspelled.
- The payment method is not approved for your merchant account.

Correct format:
bitcoin,alipay_cn
---

## 📚 Payssion Documentation

Official documentation:
https://payssion.com/en/docs/

---

## 📄 License

MIT License

---

## 🤝 Contributing

Pull requests are welcome.

If you find bugs or compatibility issues, please open an issue.

---
## ⚠️ Disclaimer

This module is not officially affiliated with Payssion or Blesta.

Use at your own risk. Always test in staging before deploying to production.
