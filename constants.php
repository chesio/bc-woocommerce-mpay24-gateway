<?php
/**
 * Declare constants that are necessary when calling plugins_url() and similar WordPress functions.
 */

namespace BlueChip\WooCommerce\Mpay24Gateway;

/**
 * @var string Path to plugin directory
 */
const PLUGIN_DIR = __DIR__;

/**
 * @var string Path to plugin main file
 */
const PLUGIN_FILE = __DIR__ . '/bc-woocommerce-mpay24-gateway.php';

/**
 * @var array Context for log messages
 */
const LOG_CONTEXT = ['source' => 'bc-woocommerce-mpay24-gateway'];
