# Payssion Gateway for Blesta (Non-merchant)

This is a **non-merchant** payment gateway for Blesta that redirects clients to Payssion's hosted payment page.

## Install

1. Upload the `payssion/` directory to:

   `YOUR_BLESTA_INSTALL/components/gateways/nonmerchant/payssion/`

2. In Blesta Admin:

   `Settings > Company > Payment Gateways > Available`

3. Install **Payssion**.

4. Click **Manage** and configure:

   - API Key
   - Secret Key
   - Payment Method ID(s) (`pm_id`) - you may enter a comma-separated list (e.g. `alipay_cn, wechatpay_cn`)

5. Select the currencies you want to accept.

## Notes

- Payssion will POST transaction notifications to the `notify_url` that Blesta provides.
- Enable **Debug Logging** if you need to troubleshoot signature validation or callback issues.

