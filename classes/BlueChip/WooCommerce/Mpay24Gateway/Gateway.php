<?php

namespace BlueChip\WooCommerce\Mpay24Gateway;

/**
 * @link https://docs.woocommerce.com/document/payment-gateway-api/
 */
class Gateway extends \WC_Payment_Gateway
{
    /**
     * @var string Name of test mode.
     */
    const TEST_MODE = 'test';

    /**
     * @var string Name of production mode.
     */
    const PRODUCTION_MODE = 'production';


    /**
     * @var \BlueChip\WooCommerce\Mpay24Gateway\Mpay24
     */
    private $mpay24;


    public function __construct()
    {
        // Set fields required by WooCommerce Gateway API.
        $this->id = 'bc-woocommerce-mpay24-gateway';
        $this->icon = apply_filters('bc-woocommerce-mpay24-gateway/filter:icon', plugins_url('assets/images/mpay24.svg', PLUGIN_FILE));
        $this->has_fields = false;
        $this->method_title = __('mPAY24 Gateway', 'bc-woocommerce-mpay24-gateway');
        $this->method_description = __('Online payment via mPAY24 payment page', 'bc-woocommerce-mpay24-gateway');

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Load WooCommerce settings.
        $this->description = $this->get_option('description');
        $this->title = $this->get_option('title');

        // Load mPAY24 settings.
        $mpay24_test_mode = $this->get_option('mpay24_mode') !== self::PRODUCTION_MODE;
        $mpay24_user = $mpay24_test_mode
            ? $this->get_option('mpay24_test_user', '')
            : $this->get_option('mpay24_production_user', '')
        ;
        $mpay24_password = $mpay24_test_mode
            ? $this->get_option('mpay24_test_password', '')
            : $this->get_option('mpay24_production_password', '')
        ;
        $mpay24_debug = $this->get_option('mpay24_debug', 'no') === 'yes';

        // Initialize mPAY24 gateway integration.
        $this->mpay24 = new Mpay24($mpay24_user, $mpay24_password, $mpay24_test_mode, $mpay24_debug);

        // Set hook that saves gateway settings on change.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }


    /**
     * @return bool True if the gateway still needs to be set up, false otherwise.
     */
    public function needs_setup() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return !$this->mpay24->isSetupOk();
    }


    /**
     * Initialize form fields.
     */
    public function init_form_fields() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $this->form_fields = [
            'woocommerce_settings' => [
                'title'       => __('Woocommerce Settings', 'bc-woocommerce-mpay24-gateway'),
                'type'        => 'title',
                'description' => '',
            ],
            'enabled' => [
                'title'       => __('Enable/Disable', 'bc-woocommerce-mpay24-gateway'),
                'label'       => __('Enable mPAY24', 'bc-woocommerce-mpay24-gateway'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no',
            ],
            'title' => [
                'title'       => __('Title', 'bc-woocommerce-mpay24-gateway'),
                'type'        => 'text',
                'description' => __('The title which user sees during checkout.', 'bc-woocommerce-mpay24-gateway'),
                'desc_tip'    => true,
                'default'     => __('Credit card', 'bc-woocommerce-mpay24-gateway'),
            ],
            'description' => [
                'title'       => __('Description', 'bc-woocommerce-mpay24-gateway'),
                'type'        => 'textarea',
                'description' => __('The description which user sees during checkout.', 'bc-woocommerce-mpay24-gateway'),
                'desc_tip'    => true,
                'default'     => __('Pay with your credit card via mPAY24.', 'bc-woocommerce-mpay24-gateway'),
            ],
            'mpay24_settings' => [
                'title'       => __('mPAY24 Settings', 'bc-woocommerce-mpay24-gateway'),
                'type'        => 'title',
                'description' => '',
            ],
            'mpay24_mode' => [
                'title'       => __('Mode', 'bc-woocommerce-mpay24-gateway'),
                'type'        => 'select',
                'description' => __('Mode determines against which mPAY24 system (test or productive) payments are made.', 'bc-woocommerce-mpay24-gateway'),
                'default'     => self::TEST_MODE,
                'desc_tip'    => true,
                'options'     => [
                    self::TEST_MODE         => __('Test', 'bc-woocommerce-mpay24-gateway'),
                    self::PRODUCTION_MODE   => __('Production', 'bc-woocommerce-mpay24-gateway'),
                ],
            ],
            'mpay24_test_user' => [
                'title'       => __('Test username', 'bc-woocommerce-mpay24-gateway'),
                'type'        => 'text',
                'description' => __('SOAP API username (without leading u) for test mode.', 'bc-woocommerce-mpay24-gateway'),
                'desc_tip'    => true,
                'default'     => '',
            ],
            'mpay24_test_password' => [
                'title'       => __('Test password', 'bc-woocommerce-mpay24-gateway'),
                'type'        => 'password',
                'description' => __('SOAP API password for test mode.', 'bc-woocommerce-mpay24-gateway'),
                'desc_tip'    => true,
                'default'     => '',
            ],
            'mpay24_production_user' => [
                'title'       => __('Production username', 'bc-woocommerce-mpay24-gateway'),
                'type'        => 'text',
                'description' => __('SOAP API username (without leading u) for production mode.', 'bc-woocommerce-mpay24-gateway'),
                'desc_tip'    => true,
                'default'     => '',
            ],
            'mpay24_production_password' => [
                'title'       => __('Production password', 'bc-woocommerce-mpay24-gateway'),
                'type'        => 'password',
                'description' => __('SOAP API password for production mode.', 'bc-woocommerce-mpay24-gateway'),
                'desc_tip'    => true,
                'default'     => '',
            ],
            'mpay24_debug' => [
                'title'       => __('Debugging', 'bc-woocommerce-mpay24-gateway'),
                'label'       => __('Enable debug logging', 'bc-woocommerce-mpay24-gateway'),
                'type'        => 'checkbox',
                'description' => sprintf(__('Log mPAY24 events inside WC_LOG_DIR: <code>%s</code>', 'bc-woocommerce-mpay24-gateway'), WC_LOG_DIR),
                'default'     => 'no',
            ],
        ];
    }


    /**
     * Show extra notice before gateway description when test mode is enabled.
     */
    public function payment_fields() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ($this->mpay24->isTestModeEnabled()) {
            echo '<p>' . \esc_html__('TEST MODE ENABLED', 'bc-woocommerce-mpay24-gateway') . '</p>';
        }
        parent::payment_fields();
    }


    /**
     * Tell WooCommerce where to redirect the user when he/she decides to use the gateway.
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $order = \wc_get_order($order_id);

        if (!($order instanceof \WC_Order)) {
            return [];
        }

        $payment_page_location = $this->mpay24->getPaymentPageLocation($order, $this->get_return_url($order));

        return [
            'result' => 'success',
            'redirect' => $payment_page_location,
        ];
    }
}
