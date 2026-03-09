<?php
/**
 * Payssion language
 */

$lang['Payssion.name'] = 'Payssion';
$lang['Payssion.description'] = 'Accept payments through Payssion (hosted payment page).';

// Meta fields
$lang['Payssion.meta.api_key'] = 'API Key';
$lang['Payssion.meta.secret_key'] = 'Secret Key';
$lang['Payssion.meta.pm_id'] = 'Payment Method ID(s) (pm_id)';
$lang['Payssion.meta.display_name'] = 'Gateway Display Name (optional)';
$lang['Payssion.meta.display_name_help'] = 'Shown to clients on invoices/checkout. Leave blank to auto-generate from selected payment methods.';
$lang['Payssion.meta.debug'] = 'Enable Debug Logging';

// Process
$lang['Payssion.process.submit'] = 'Pay with Payssion';
$lang['Payssion.process.notice'] = 'Continue to Payssion to complete your payment.';
$lang['Payssion.process.choose_method'] = 'Choose a payment method to continue.';
$lang['Payssion.process.pay_with'] = 'Pay with %1$s';

// Errors
$lang['Payssion.!error.api_key.empty'] = 'Please enter your Payssion API key.';
$lang['Payssion.!error.secret_key.empty'] = 'Please enter your Payssion secret key.';
$lang['Payssion.!error.pm_id.empty'] = 'Please enter at least one Payssion payment method ID (pm_id).';
$lang['Payssion.!error.debug.valid'] = 'Debug must be enabled or disabled.';
