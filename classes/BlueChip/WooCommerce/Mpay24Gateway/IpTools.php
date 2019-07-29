<?php

namespace BlueChip\WooCommerce\Mpay24Gateway;

/**
 * Tools for IP address handling
 */
abstract class IpTools
{
    /**
     * @link https://www.php.net/manual/en/ref.network.php#74656
     * @param string $ip IP address to check
     * @param string $subnet Subnet to check against in CIDR notation
     * @return bool True if $ip belongs to $subnet, false otherwise.
     */
    public static function isIpInSubnet(string $ip, string $subnet): bool
    {
        [$net, $mask] = explode('/', $subnet);

        $_net = ip2long($net);
        $_mask = ~((1 << (32 - $mask)) - 1);

        $_ip = ip2long($ip);

        $_ip_net = $_ip & $_mask;

        return ($_ip_net === $_net);
    }


    /**
     * @param string $ip IP address to check
     * @param array $subnets List of subnets in CIDR notation
     * @return bool True, if $ip belongs to one of $subnets, false otherwise.
     */
    public static function isIpInOneOfSubnets(string $ip, array $subnets): bool
    {
        foreach ($subnets as $subnet) {
            if (self::isIpInSubnet($ip, $subnet)) {
                return true;
            }
        }

        return false;
    }
}
