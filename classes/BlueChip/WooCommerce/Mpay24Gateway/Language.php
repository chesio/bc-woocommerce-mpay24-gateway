<?php

namespace BlueChip\WooCommerce\Mpay24Gateway;

abstract class Language
{
    /**
     * @link https://make.wordpress.org/polyglots/teams/ List of WP locales
     * @link https://docs.mpay24.com/docs/redirect-integration#section-supported-languages List of languages supported by mPAY24
     *
     * @var array Mapping from WordPress locales to mPAY24 language codes
     */
    const WP_LOCALE_TO_MPAY24_LANG = [
        'bg_BG' => 'BG',
        'cs_CZ' => 'CS',
        'da_DK' => 'DA',
        'de_DE' => 'DE',
        'de_CH' => 'DE',
        'en_AU' => 'EN',
        'en_CA' => 'EN',
        'en_GB' => 'EN',
        'en_NZ' => 'EN',
        'en_ZA' => 'EN',
        'el'    => 'EL',
        'es_AR' => 'ES',
        'es_CL' => 'ES',
        'es_CO' => 'ES',
        'es_GT' => 'ES',
        'es_MX' => 'ES',
        'es_PE' => 'ES',
        'es_PR' => 'ES',
        'es_ES' => 'ES',
        'es_VE' => 'ES',
        'fi'    => 'FI',
        'fr_BE' => 'FR',
        'fr_CA' => 'FR',
        'fr_FR' => 'FR',
        'hr'    => 'HR',
        'hu_HU' => 'HU',
        'it_IT' => 'IT',
        'ja'    => 'JA',
        'nl_BE' => 'NL',
        'nl_NL' => 'NL',
        'nb_NO' => 'NO',
        'nn_NO' => 'NO',
        'pl_PL' => 'PL',
        'pt_BR' => 'PT',
        'pt_PT' => 'PT',
        'ro_RO' => 'RO',
        'ru_RU' => 'RU',
        'sk_SK' => 'SK',
        'sl_SI' => 'SL',
        'sr_RS' => 'SR',
        'sv_SE' => 'SV',
        'tr_TR' => 'TR',
        'uk'    => 'UK',
        'zh_CN' => 'ZH',
        'zh_HK' => 'ZH',
        'zh_TW' => 'ZH',
    ];


    /**
     * Translate current WordPress locale into mPAY24 language code.
     *
     * @param string $default
     * @return string
     */
    public static function get(string $default = ''): string
    {
        // Attempt to translate WP locale to language known to mPAY24 with a fall back to $default value.
        return self::WP_LOCALE_TO_MPAY24_LANG[\get_locale()] ?? $default;
    }
}
