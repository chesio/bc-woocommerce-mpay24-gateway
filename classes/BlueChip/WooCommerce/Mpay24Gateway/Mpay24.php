<?php

namespace BlueChip\WooCommerce\Mpay24Gateway;

class Mpay24
{
    /**
     * @var string mPAY24 user (must be consistent with mode)
     */
    private $user;

    /**
     * @var string mPAY24 password (must be consistent with mode)
     */
    private $password;

    /**
     * @var bool True, if mPAY24 integration is set to test mode, false otherwise.
     */
    private $test_mode = true;

    /**
     * @var bool True, if debugging is active, false otherwise.
     */
    private $debug = false;


    /**
     * @param string $user
     * @param string $password
     * @param bool $test_mode
     * @param bool $debug
     */
    public function __construct(string $user, string $password, bool $test_mode, bool $debug)
    {
        $this->user = $user;
        $this->password = $password;
        $this->test_mode = $test_mode;
        $this->debug = $debug;
    }


    /**
     * @return bool True, if setup seems to be ok (complete), false otherwise.
     */
    public function isSetupOk(): bool
    {
        return ($this->user !== '') && ($this->password !== '');
    }


    /**
     * @return bool True, if test mode is enabled, false otherwise.
     */
    public function isTestModeEnabled(): bool
    {
        return $this->test_mode;
    }


    /**
     * @link https://github.com/mpay24/mpay24-php#create-a-payment-page
     *
     * @param \WC_Order $order
     * @param string $return_url
     * @return string URL of payment page
     */
    public function getPaymentPageLocation(\WC_Order $order, string $return_url): string
    {
        // This can throw:
        $sdk = new \Mpay24\Mpay24($this->getConfig());

        return $sdk->paymentPage($this->buildOrder($order, $return_url))->getLocation();
    }


    /**
     * @return \Mpay24\Mpay24Config
     */
    protected function getConfig(): \Mpay24\Mpay24Config
    {
        $config = new \Mpay24\Mpay24Config($this->user, $this->password, $this->test_mode, $this->debug);
        // Change log path to directory where WooCommerce stores its logs.
        $config->setLogPath(\untrailingslashit(WC_LOG_DIR));
        // Change log filename to something more random than default.
        $config->setLogFile(\WC_Log_Handler_File::get_log_file_name('mpay24'));

        return $config;
    }


    /**
     * @param \WC_Order $order
     * @param string $return_url
     * @return \Mpay24\Mpay24Order
     */
    protected function buildOrder(\WC_Order $order, string $return_url): \Mpay24\Mpay24Order
    {
        $mdxi = new \Mpay24\Mpay24Order();
        $mdxi->Order->Tid = IPN::generateTID($order);

        $mdxi->Order->TemplateSet->setLanguage(Language::get());
        $mdxi->Order->TemplateSet->setCSSName('MODERN');

        // Pre-select credit card payment.
        $mdxi->Order->PaymentTypes->setEnable('true');
        $mdxi->Order->PaymentTypes->Payment(1)->setType("CC");

        // Set mandatory items.
        $mdxi->Order->Price = $order->get_total();
        $mdxi->Order->Currency = $order->get_currency();

        // Add customer data.
        $mdxi->Order->Customer = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

        // Note: Redirect to "thank you" page even in case of error, because the order-received endpoint is not a mere
        // "Thank you" page. If order status is "failed", it will display proper message and offer an option to repeat
        // the payment - I believe this is much better UX than canceling the order right away.
        $mdxi->Order->URL->Success      = $return_url;
        $mdxi->Order->URL->Error        = $return_url;
        $mdxi->Order->URL->Confirmation = IPN::getConfirmationUrl($order);
        $mdxi->Order->URL->Cancel       = $order->get_cancel_order_url_raw();

        return $mdxi;
    }
}
