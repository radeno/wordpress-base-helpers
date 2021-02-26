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
        return substr(\get_locale(), 2, 2);
    }
}
