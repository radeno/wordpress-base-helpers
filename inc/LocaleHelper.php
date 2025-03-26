<?php

namespace helper;

class LocaleHelper
{
    public static function getLanguageCode(): string
    {
        return substr(\get_locale(), 0, 2);
    }

    public static function getCountryCode(): string
    {
        return substr(\get_locale(), 3, 2);
    }

    public static function getPluginsLanguageCode(): string|null
    {
        // Check if WPML is active
        if (function_exists('apply_filters')) {
            $languageCode = \apply_filters('wpml_current_language', null);
            if ($languageCode) {
                return $languageCode;
            }
        }

        // Check if Polylang is active
        if (function_exists('pll_current_language')) {
            $languageCode = \pll_current_language();
            if ($languageCode) {
                return $languageCode;
            }
        }

        return null;
    }

    public static function getPostMetaLanguageCode($postId): string|null
    {
        $metaKeys = ['_icl_lang', '_pll_post_language'];
        $metaData = \get_metadata('post', $postId);
        foreach ($metaKeys as $metaKey) {
            if (isset($metaData[$metaKey][0])) {
                return $metaData[$metaKey][0];
            }
        }

        return null;
    }
}
