<?php
/**
 * Plugin Name: BC WooCommerce mPAY24 Gateway
 * Plugin URI: https://github.com/chesio/bc-woocommerce-mpay24-gateway
 * Description: Integrate mPAY24 payment gateway into WooCommerce
 * Version: develop
 * Author: Česlav Przywara <cp@bluechip.at>
 * Author URI: https://www.chesio.com
 * Requires PHP: 7.1
 * Requires WP: 4.9
 * Tested up to: 6.2
 * Text Domain: bc-woocommerce-mpay24-gateway
 * GitHub Plugin URI: https://github.com/chesio/bc-woocommerce-mpay24-gateway
 */

if (version_compare(PHP_VERSION, '7.1', '<')) {
    // Warn user that his/her PHP version is too low for this plugin to function.
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>';
        echo esc_html(
            sprintf(
                __('WooCommerce mPAY24 Gateway plugin requires PHP 7.1 to function properly, but you have version %s installed. The plugin has been auto-deactivated.', 'bc-woocommerce-mpay24-gateway'),
                PHP_VERSION
            )
        );
        echo '</p></div>';
        // https://make.wordpress.org/plugins/2015/06/05/policy-on-php-versions/
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }, 10, 0);

    // Self deactivate.
    add_action('admin_init', function () {
        deactivate_plugins(plugin_basename(__FILE__));
    }, 10, 0);

    // Bail.
    return;
}

// Load constants.
require_once __DIR__ . '/constants.php';

// Register autoloader for this plugin.
require_once __DIR__ . '/autoload.php';

// Bootstrap mPAY24 PHP SDK (= effectively register autoloader for the SDK).
require_once __DIR__ . '/includes/mpay24-php/bootstrap.php';

add_action('plugins_loaded', function () {
    // Construct plugin instance.
    $bc_woocommerce_mpay24_gateway = new \BlueChip\WooCommerce\Mpay24Gateway\Plugin();
    // Load the plugin.
    $bc_woocommerce_mpay24_gateway->load();
}, 10, 0);
