<?php
/**
 * Payssion Non-merchant Gateway
 *
 * Based on the Payssion WHMCS gateway behavior (hosted payment page + notify callback)
 *
 * @package blesta
 * @subpackage blesta.components.gateways.nonmerchant.payssion
 */
class Payssion extends NonmerchantGateway
{
    /**
     * @var array|null Meta data configured for this gateway instance
     */
    private $meta;

    /**
     * @var string|null The current currency for transactions
     */
    private $currency;

    /**
     * Construct a new Payssion gateway
     */
    public function __construct()
    {
        // Load components required by this gateway
        Loader::loadComponents($this, ['Input']);

        // Load language and config
        Language::loadLang('payssion', null, dirname(__FILE__) . DS . 'language' . DS);
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');
    }

    /**
     * Sets the currency to be used for subsequent payments
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * Sets gateway meta data for subsequent payments
     */
    public function setMeta(array $meta = null)
    {
        $this->meta = $meta;
    }

    /**
     * Returns HTML for the gateway settings page
     */
    public function getSettings(array $meta = null)
    {
        $this->view = $this->makeView(
            'settings',
            'default',
            str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS)
        );

        $this->view->set('meta', (array) $meta);

        return $this->view->fetch();
    }

    /**
     * Validates and returns settings to be saved
     */
    public function editSettings(array $meta)
    {
        $rules = [
            'api_key' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Payssion.!error.api_key.empty', true)
                ]
            ],
            'secret_key' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Payssion.!error.secret_key.empty', true)
                ]
            ],
            'pm_id' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Payssion.!error.pm_id.empty', true)
                ]
            ]
        ];

        $this->Input->setRules($rules);
        $this->Input->validates($meta);

        // Normalize debug to "0"/"1"
        $meta['debug'] = (!empty($meta['debug']) && $meta['debug'] !== '0') ? '1' : '0';

        // Normalize pm_id(s) by trimming whitespace (allow comma-separated list)
        if (isset($meta['pm_id'])) {
            $meta['pm_id'] = trim((string) $meta['pm_id']);
        }

        // Ensure at least one valid pm_id exists after parsing/sanitizing
        $pm_ids = $this->parsePmIds($this->ifSet($meta['pm_id']));
        if (empty($pm_ids)) {
            $this->Input->setErrors([
                'pm_id' => [
                    'empty' => Language::_('Payssion.!error.pm_id.empty', true)
                ]
            ]);
        } else {
            // Store the normalized list
            $meta['pm_id'] = implode(',', $pm_ids);
        }

        return $meta;
    }

    /**
     * Fields that should be stored encrypted
     */
    public function encryptableFields()
    {
        return ['secret_key'];
    }

    /**
     * Builds the HTML form used to process a payment (redirects the client to Payssion)
     */
    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null)
    {
        $this->view = $this->makeView(
            'process',
            'default',
            str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS)
        );

        $api_key = $this->ifSet($this->meta['api_key']);
        $secret_key = $this->ifSet($this->meta['secret_key']);
        $pm_id_raw = $this->ifSet($this->meta['pm_id']);
        $pm_ids = $this->parsePmIds($pm_id_raw);

        if (empty($api_key) || empty($secret_key) || empty($pm_ids)) {
            $this->Input->setErrors($this->getCommonError('general'));
            return '';
        }

        $currency = $this->currency;
        if (empty($currency) && is_array($options) && isset($options['currency'])) {
            $currency = $options['currency'];
        }
        if (empty($currency)) {
            // Fallback; Blesta will typically set a currency before calling buildProcess
            $currency = 'USD';
        }

        $track_id = $this->getTrackId($invoice_amounts, $options);

        // Payssion supports an optional order_id which will be returned as sub_track_id
        // We keep it empty for maximum compatibility with the WHMCS module behavior
        $order_id = '';

        $formatted_amount = $this->formatAmount($amount);

        $post_to = $this->getGatewayUrl();

        $notify_url = (is_array($options) ? $this->ifSet($options['notify_url']) : null);
        $return_url = (is_array($options) ? $this->ifSet($options['return_url']) : null);

        // Common fields (pm_id/api_sig are per-method)
        $common_fields = [
            'api_key' => $api_key,
            'amount' => $formatted_amount,
            'currency' => $currency,
            'description' => $this->buildDescription($invoice_amounts, $track_id),
            'track_id' => $track_id,
            'order_id' => $order_id,
            'notify_url' => $notify_url,
            'success_url' => $return_url,
            'redirect_url' => $return_url
        ];

        // Build payment-method-specific forms
        $payment_methods = [];
        foreach ($pm_ids as $pm_id) {
            // Signature format: md5(api_key|pm_id|amount|currency|track_id|order_id|secret_key)
            $api_sig = md5(
                $api_key
                . '|' . $pm_id
                . '|' . $formatted_amount
                . '|' . $currency
                . '|' . $track_id
                . '|' . $order_id
                . '|' . $secret_key
            );

            $fields = $common_fields;
            $fields['pm_id'] = $pm_id;
            $fields['api_sig'] = $api_sig;

            $payment_methods[] = [
                'pm_id' => $pm_id,
                'label' => $this->formatPmLabel($pm_id),
                'fields' => $fields
            ];
        }

        // Log outgoing request for troubleshooting (without secret)
        if (!empty($this->meta['debug'])) {
            $log = [
                'post_to' => $post_to,
                'methods' => []
            ];

            foreach ($payment_methods as $method) {
                $log_fields = $method['fields'];
                $log_fields['secret_key'] = '********';
                $log['methods'][] = [
                    'pm_id' => $method['pm_id'],
                    'fields' => $log_fields
                ];
            }

            $this->log($post_to, serialize($log), 'input', true);
        }

        $this->view->set('post_to', $post_to);
        $this->view->set('payment_methods', $payment_methods);
        $this->view->set('submit_label', Language::_('Payssion.process.submit', true));
        $this->view->set('notice', Language::_('Payssion.process.notice', true));
        $this->view->set('choose_notice', Language::_('Payssion.process.choose_method', true));

        return $this->view->fetch();
    }

    /**
     * Validates the response from Payssion (notify callback)
     */
    public function validate(array $get, array $post)
    {
        // Always log the callback
        $this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($post), 'output', true);

        $api_key = $this->ifSet($this->meta['api_key']);
        $secret_key = $this->ifSet($this->meta['secret_key']);

        $pm_id = $this->ifSet($post['pm_id']);
        $amount = $this->ifSet($post['amount']);
        $currency = $this->ifSet($post['currency']);
        $track_id = $this->ifSet($post['track_id']);
        $sub_track_id = $this->ifSet($post['sub_track_id']);
        $state = $this->ifSet($post['state']);
        $notify_sig = $this->ifSet($post['notify_sig']);
        $transaction_id = $this->ifSet($post['transaction_id']);
        $paid = $this->ifSet($post['paid']);

        // Signature format: md5(api_key|pm_id|amount|currency|track_id|sub_track_id|state|secret_key)
        $expected_sig = md5(
            $api_key
            . '|' . $pm_id
            . '|' . $amount
            . '|' . $currency
            . '|' . $track_id
            . '|' . $sub_track_id
            . '|' . $state
            . '|' . $secret_key
        );

        $paid_states = ['completed', 'paid', 'paid_partial', 'paid_more'];
        $paid_numeric = (is_numeric($paid) ? (float) $paid : 0.0);

        $status = 'declined';
        if (!empty($notify_sig) && strtolower($notify_sig) === strtolower($expected_sig)) {
            if ($paid_numeric > 0 && in_array(strtolower($state), $paid_states, true)) {
                $status = 'approved';
            } elseif (strtolower($state) === 'pending') {
                $status = 'pending';
            }
        } else {
            // Invalid signature
            $this->Input->setErrors($this->getCommonError('invalid'));
        }

        // Invoices are typically included in the callback URL query string by Blesta
        $invoices = $this->ifSet($get['invoices'], []);

        // Fallback for single invoice payments
        if (empty($invoices) && isset($get['invoice_id'])) {
            $invoices = [$get['invoice_id'] => $amount];
        }

        return [
            'client_id' => $this->ifSet($get['client_id']),
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
            'reference_id' => null,
            'transaction_id' => $transaction_id,
            'parent_transaction_id' => null,
            'invoices' => $invoices
        ];
    }

    /**
     * Handles the user returning from Payssion
     */
    public function success(array $get, array $post)
    {
        // Log the return request
        $this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($post), 'output', true);

        // If Payssion returns full payload to success_url, treat it like validate.
        if (isset($post['notify_sig']) || isset($post['transaction_id']) || isset($post['state'])) {
            return $this->validate($get, $post);
        }

        // Otherwise, just return context
        return [
            'client_id' => $this->ifSet($get['client_id']),
            'amount' => $this->ifSet($get['amount']),
            'currency' => $this->ifSet($get['currency'], $this->currency),
            'status' => 'approved',
            'reference_id' => null,
            'transaction_id' => $this->ifSet($get['transaction_id']),
            'parent_transaction_id' => null,
            'invoices' => $this->ifSet($get['invoices'], [])
        ];
    }

    /**
     * Payssion hosted payment endpoint
     */
    private function getGatewayUrl()
    {
        return 'https://www.payssion.com/payment/create.html';
    }

    /**
     * Formats the amount for Payssion
     */
    private function formatAmount($amount)
    {
        if (is_numeric($amount)) {
            return number_format((float) $amount, 2, '.', '');
        }

        $normalized = str_replace(',', '', (string) $amount);
        if (is_numeric($normalized)) {
            return number_format((float) $normalized, 2, '.', '');
        }

        return (string) $amount;
    }

    /**
     * Determines a Payssion track_id
     */
    private function getTrackId($invoice_amounts, $options)
    {
        // If Blesta provides an invoice_id in options, use it
        if (is_array($options) && !empty($options['invoice_id'])) {
            return (string) $options['invoice_id'];
        }

        /**
         * Blesta passes $invoice_amounts like:
         * [
         *   ['id' => 123, 'amount' => '10.00'],
         *   ['id' => 124, 'amount' => '5.00']
         * ]
         * so array_keys() would return [0,1,...] which causes track_id "0".
         */
        if (is_array($invoice_amounts) && !empty($invoice_amounts)) {
            $first = reset($invoice_amounts);

            if (is_array($first) && isset($first['id']) && $first['id'] !== '') {
                return (string) $first['id'];
            }

            // Fallback in case Blesta ever passes associative invoice_id => amount
            if (!empty($invoice_amounts)) {
                $keys = array_keys($invoice_amounts);
                $first_key = reset($keys);
                if ($first_key !== null && $first_key !== '') {
                    return (string) $first_key;
                }
            }
        }

        // Fallback
        return 'blesta-' . date('YmdHis');
    }

    /**
     * Builds a short description for the Payssion payment
     */
    private function buildDescription($invoice_amounts, $track_id)
    {
        // Prefer Blesta's structured invoice list
        if (is_array($invoice_amounts) && !empty($invoice_amounts)) {
            $ids = [];

            foreach ($invoice_amounts as $row) {
                if (is_array($row) && isset($row['id']) && $row['id'] !== '') {
                    $ids[] = (string) $row['id'];
                }
            }

            // If nothing found, fallback to associative keys style
            if (empty($ids)) {
                $keys = array_keys($invoice_amounts);
                foreach ($keys as $k) {
                    if ($k !== null && $k !== '') {
                        $ids[] = (string) $k;
                    }
                }
            }

            $ids = array_values(array_unique(array_filter($ids, function ($v) {
                return $v !== '' && $v !== null;
            })));

            if (!empty($ids)) {
                $label = (count($ids) > 1 ? 'Invoices' : 'Invoice');
                return $label . ': ' . implode(',', $ids);
            }
        }

        return 'Invoice: ' . $track_id;
    }

    /**
     * Parses pm_id meta input into a clean list of Payssion payment method IDs.
     *
     * Accepts a comma and/or newline separated list.
     */
    private function parsePmIds($pm_id_raw)
    {
        $raw = (string) $pm_id_raw;
        $parts = preg_split('/[\s]*[,\n\r]+[\s]*/', $raw);

        $pm_ids = [];
        foreach ((array) $parts as $part) {
            $id = trim((string) $part);
            if ($id === '') {
                continue;
            }

            // Keep it conservative - Payssion pm_id values are typically [a-z0-9_]
            $id = preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
            if ($id === '') {
                continue;
            }

            $pm_ids[] = $id;
        }

        return array_values(array_unique($pm_ids));
    }

    /**
     * Formats a pm_id into a more human-friendly label.
     * Example: "alipay_cn" => "Alipay CN".
     */
    private function formatPmLabel($pm_id)
    {
        $pm_id = (string) $pm_id;
        $parts = preg_split('/[_\-]+/', $pm_id);
        $out = [];

        foreach ((array) $parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }

            // Country/short codes look better uppercase
            if (strlen($p) <= 3) {
                $out[] = strtoupper($p);
            } else {
                $out[] = ucfirst(strtolower($p));
            }
        }

        return !empty($out) ? implode(' ', $out) : $pm_id;
    }
}
