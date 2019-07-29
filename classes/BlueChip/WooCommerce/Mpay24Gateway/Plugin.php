<?php

namespace BlueChip\WooCommerce\Mpay24Gateway;

/**
 * Main plugin class
 */
class Plugin
{
    /**
     * Perform initialization tasks.
     * Method should be run in `plugins_loaded` hook.
     *
     * @action https://developer.wordpress.org/reference/hooks/plugins_loaded/
     */
    public function load()
    {
        if (class_exists('WC_Payment_Gateway')) {
            add_filter('woocommerce_payment_gateways', [$this, 'registerPaymentMethod'], 10, 1);
        }

        // Register payment gateway callback:
        // https://docs.woocommerce.com/document/payment-gateway-api/#section-7
        add_action('woocommerce_api_bluechip_woocommerce_mpay24_gateway', [IPN::class, 'processConfirmationRequest']);
    }


    /**
     * Register mPAY24 gateway as an available payment method in WooCommerce.
     *
     * @param array $methods
     * @return array
     */
    public function registerPaymentMethod(array $methods): array
    {
        $methods[] = Gateway::class;
        return $methods;
    }
}
