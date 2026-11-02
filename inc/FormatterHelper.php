<?php

namespace helper;

class FormatterHelper
{
    public static function toLocalPhoneNumber($internationalNumber)
    {
        // libphonenumber 9 types parse() as string, so null would be a TypeError.
        if (is_null($internationalNumber)) {
            return null;
        }

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
        return \wp_date(empty($format) ? \get_option('date_format') : $format, self::timestamp($isoDate));
    }

    public static function time($isoTime, $format = null)
    {
        return \wp_date(empty($format) ? \get_option('time_format') : $format, self::timestamp($isoTime));
    }

    /**
     * A string without an offset is site time, like the dates WordPress stores; one
     * with an offset, such as get_post_time('c'), keeps it. An unparsable string
     * gives null, which wp_date() formats as the current time, as date_i18n() did.
     */
    private static function timestamp($dateString): ?int
    {
        $date = \date_create_immutable((string) $dateString, \wp_timezone());

        return $date ? $date->getTimestamp() : null;
    }

    public static function number($number, $decimals = 0)
    {
        return \number_format_i18n($number, $decimals);
    }
}
