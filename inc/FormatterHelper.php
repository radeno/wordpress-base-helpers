<?php

namespace helper;

class FormatterHelper
{
    public static function toLocalPhoneNumber($internationalNumber)
    {
        try {
            $phoneUtil    = \libphonenumber\PhoneNumberUtil::getInstance();
            $parsedNumber = $phoneUtil->parse($internationalNumber);

            return $phoneUtil->format($parsedNumber, \libphonenumber\PhoneNumberFormat::NATIONAL);
        } catch (\libphonenumber\NumberParseException $e) {
            return $internationalNumber;
        }
    }

    public static function titleInitials($title)
    {
        $titleParts = explode(" ", $title);

        $titleInitials = array_map(function ($titlePart) {
            return mb_substr($titlePart, 0, 1);
        }, $titleParts);

        return join('', $titleInitials);
    }

    public static function date($isoDate, $format = null)
    {
        return \date_i18n(empty($format) ? \get_option('date_format') : $format, strtotime($isoDate));
    }

    public static function time($isoTime, $format = null)
    {
        return \date_i18n(empty($format) ? \get_option('time_format') : $format, strtotime($isoTime));
    }

    public static function number($numner, $decimals = 0)
    {
        return \number_format_i18n($numner, $decimals);
    }
}
