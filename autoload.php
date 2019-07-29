<?php
/**
 * Register autoloader for classes shipped with the plugin.
 *
 * @package BC_WooCommerce_mPAY24_Gateway
 */

// Register autoload function
spl_autoload_register(function (string $class) {
    // Only autoload classes shipped with the plugin.
    if (strpos($class, 'BlueChip\\WooCommerce\\Mpay24Gateway') !== 0) {
        return;
    }

    // Get absolute name of class file
    $file = __DIR__ . '/classes/' . str_replace('\\', '/', $class) . '.php';

    // If the class file is readable, load it!
    if (is_readable($file)) {
        require_once $file;
    }
});
