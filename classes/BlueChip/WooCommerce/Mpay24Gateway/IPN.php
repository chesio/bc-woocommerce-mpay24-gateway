<?php

namespace BlueChip\WooCommerce\Mpay24Gateway;

/**
 * Integrates mPAY24 IPN (Instant Payment Notifications)
 * @link https://en.wikipedia.org/wiki/Instant_payment_notification
 *
 * @internal There are essentially three different order/transaction ids provided by three different parties
 * to an order/transaction:
 * 1) Order ID (integer) - provided by WooCommerce
 * 2) Transaction ID or TID (string) - provided by this plugin as part of request to mPAY24 gateway
 * 3) mPAY24 Transaction ID or MPAYTID (string) - provided by mPAY24 gateway as part of response
 */
abstract class IPN
{
    /**
     * @var string
     */
    const TRANSACTION_SECRET_META = 'mpay24_transaction_secret';

    /**
     * @link https://docs.mpay24.com/docs/test-and-productive-system
     * @var array List of IP ranges from which mPAY24 IPNs are pushed.
     */
    const MPAY24_SUBNETS = [
        '213.164.25.224/27', // production system
        '217.175.200.16/28', // production system
        '213.208.153.58/32', // test system
    ];


    /**
     * @link https://docs.woocommerce.com/document/wc_api-the-woocommerce-api-callback/
     *
     * @param \WC_Order $order
     * @return string URL of WooCommerce API endpoint used to confirm the order.
     */
    public static function getConfirmationUrl(\WC_Order $order): string
    {
        // Note: there is no class BlueChip_WooCommerce_mPAY24_Gateway class, the name is just a namespaced action name.
        $endpoint_url = add_query_arg('wc-api', 'BlueChip_WooCommerce_mPAY24_Gateway', home_url('/'));

        // Get transaction secret.
        $transaction_secret = self::generateTransactionSecret($order);
        // Save it to order meta.
        $order->add_meta_data(self::TRANSACTION_SECRET_META, $transaction_secret, true);
        $order->save_meta_data();

        // Enhance confirmation URL with order id, order key and hash used to prove the confirmation authenticity.
        $order_endpoint_url = add_query_arg(
            [
                'wc_order_id'       => $order->get_id(),
                'wc_order_key'      => $order->get_order_key(),
                'wc_order_secret'   => $transaction_secret,
            ],
            $endpoint_url
        );

        return $order_endpoint_url;
    }


    /**
     * @internal TID must be a string with 1-32 characters.
     * @param \WC_Order $order
     * @return string Transaction ID for given $order in format: <order id>-<customer last name>-<customer first name>
     */
    public static function generateTid(\WC_Order $order): string
    {
        // Although it's tempting to use md5() to generate 32 characters long identifier,
        // it makes sense to issue TIDs in human readable format, as TID is the main link
        // between transaction records in mPAY24 and orders in WooCommerce.
        $tid = sprintf(
            '%d-%s-%s',
            $order->get_id(),
            \sanitize_title($order->get_billing_last_name()),
            \sanitize_title($order->get_billing_first_name())
        );

        return substr($tid, 0, 32);
    }


    /**
     * Generate transaction secret for given $order.
     *
     * Note that method returns unique secret even if called multiple times with the same $order value!
     *
     * @link https://docs.mpay24.com/docs/payment-notification#section-securing
     * @param \WC_Order $order
     * @return string
     */
    protected static function generateTransactionSecret(\WC_Order $order): string
    {
        return sha1(
            sprintf(
                '%d-%f-%s-%d-%d',
                $order->get_id(), // int
                $order->get_total(), // float
                $order->get_currency(), // string
                $order->get_date_created()->getTimestamp(), // int
                random_int(PHP_INT_MIN, PHP_INT_MAX) // int
            )
        );
    }


    /**
     * Process confirmation request (payment notification) from mPAY24.
     *
     * @link https://docs.mpay24.com/docs/payment-notification#section-push-method
     * @link https://docs.woocommerce.com/document/payment-gateway-api/#section-7
     */
    public static function processConfirmationRequest()
    {
        $request_data = wp_unslash($_GET);

        $status = $request_data['STATUS'] ?? '';
        $tid = $request_data['TID'] ?? '';
        $mpay_tid = $request_data['MPAYTID'] ?? '';

        if (($status === '') || ($tid === '') || ($mpay_tid === '')) {
            // Required meta data are missing, sorry.
            wp_die('ERROR', 'mPAY24 IPN', 500);
        }

        if (($order = self::validateConfirmationRequest($request_data)) === null) {
            // Confirmation request does not validate.
            wp_die('ERROR', 'mPAY24 IPN', 500);
        }

        if (self::updateOrder($order, $status, $tid, $mpay_tid)) {
            // Order updated.
            wp_die('OK', 'mPAY24 IPN', 200);
        } else {
            // Order update failed.
            wp_die('ERROR', 'mPAY24 IPN', 500);
        }
    }


    /**
     * @param array $data Request data.
     * @return null|WC_Order Order instance if confirmation request is valid, null otherwise.
     */
    protected static function validateConfirmationRequest(array $data): ?\WC_Order
    {
        $logger = \wc_get_logger();

        // Check mandatory items.
        if (!isset($data['wc_order_id']) || !isset($data['wc_order_key']) || !isset($data['wc_order_secret'])) {
            return null;
        }

        if (($order_id = intval(wc_get_order_id_by_order_key($data['wc_order_key']))) === 0) {
            // Invalid order key, sorry.
            return null;
        }

        if ($order_id !== intval($data['wc_order_id'])) {
            // Order ID do not match, sorry.
            return null;
        }

        if (($order = wc_get_order($order_id)) === false) {
            // Not a valid order ID, sorry.
            return null;
        }

        if ($data['wc_order_secret'] !== $order->get_meta(self::TRANSACTION_SECRET_META)) {
            // Transaction secret does not match stored value, sorry.
            return null;
        }

        // mPAY24 sends requests from particular IP ranges, see:
        // https://docs.mpay24.com/docs/test-and-productive-system

        // Retrieving remote address on generic webhost might be unreliable,
        // therefore allow the value to be filtered.
        // Passing empty string effectively disables the check.
        $remote_address = apply_filters(
            'bc-woocommerce-mpay24-gateway/filter:remote-address',
            $_SERVER['REMOTE_ADDR'] ?? ''
        );

        // mPAY24 documentation seems to not be always up to date and
        // production/test system IP addresses can change anytime,
        // therefore allow the value to be filtered.
        $mpay24_subnets = apply_filters(
            'bc-woocommerce-mpay24-gateway/filter:mpay24-subnets',
            self::MPAY24_SUBNETS
        );

        if (($remote_address !== '') && !IpTools::isIpInOneOfSubnets($remote_address, $mpay24_subnets)) {
            $logger->warning(
                sprintf(__('Confirmation request blocked due to an unrecognized remote address %s.', 'bc-woocommerce-mpay24-gateway'), $remote_address),
                LOG_CONTEXT
            );

            return null;
        }

        return $order;
    }


    /**
     * Change order status according to $request_data received via IPN from mPAY24.
     *
     * @link https://docs.mpay24.com/docs/transaction-states All transaction states
     * @link https://docs.mpay24.com/docs/payment-notification#section-notification-values Request data items
     *
     * @param \WC_Order $order_id
     * @param string $status
     * @param string $tid
     * @param string $mpay_tid
     * @return bool True if order has been updated successfully, false if there was an error.
     */
    protected static function updateOrder(\WC_Order $order, string $status, string $tid, string $mpay_tid): bool
    {
        $logger = \wc_get_logger();

        switch (strtolower($status)) {
            case 'billed':
                // The amount was settled/billed. The transaction was successful.
                if ($order->get_status() === 'completed') {
                    // Order has been completed already, nothing to be done here.
                    return true;
                } else {
                    // Mark order as paid.
                    if ($order->payment_complete($mpay_tid)) {
                        $order->add_order_note(
                            sprintf(__('mPAY24 transaction %s has been completed.', 'bc-woocommerce-mpay24-gateway'), $tid)
                        );
                        return true;
                    } else {
                        return false;
                    }
                }
            case 'error':
                // The transaction failed upon the last request. (e.g. wrong/invalid data, financial reasons, ...)
                return $order->update_status(
                    'failed',
                    sprintf(__('mPAY24 transaction %s has failed.', 'bc-woocommerce-mpay24-gateway'), $tid)
                );
            case 'credited':
                // The amount will be refunded. The transaction was credited.
                return $order->update_status(
                    'refunded',
                    sprintf(__('mPAY24 transaction %s has been refunded.', 'bc-woocommerce-mpay24-gateway'), $tid)
                );
            case 'reserved':
                // The amount was reserved but not settled/billed yet. The transaction was successful.
                return $order->update_status(
                    'on-hold',
                    sprintf(__('mPAY24 transaction %s has been reserved.', 'bc-woocommerce-mpay24-gateway'), $tid)
                );
            case 'reversed':
                // The reserved amount was released. The transaction was canceled.
                return $order->update_status(
                    'cancel',
                    sprintf(__('mPAY24 transaction %s has been canceled.', 'bc-woocommerce-mpay24-gateway'), $tid)
                );
            default:
                // No action
                $logger->error(
                    sprintf(__('Unexpected mPAY24 transaction status %s relayed by gateway for transaction %s.', 'bc-woocommerce-mpay24-gateway'), $status, $tid),
                    LOG_CONTEXT
                );
                return false;
        }
    }
}
