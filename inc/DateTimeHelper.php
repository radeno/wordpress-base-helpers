<?php

namespace helper;

class DateTimeHelper
{
    public static function currentDateString()
    {
        return \current_time("c");
    }

    /**
     * Whether $date falls within the range; a null bound leaves that side open.
     */
    public static function isBetween($date, $startDate, $endDate = null)
    {
        return (is_null($startDate) || self::isAfterEqual($date, $startDate))
            && (is_null($endDate) || self::isBeforeEqual($date, $endDate));
    }

    public static function isAfter(string $date, string $dateToCompare)
    {
        return self::parse($date) > self::parse($dateToCompare);
    }

    public static function isAfterEqual(string $date, string $dateToCompare)
    {
        return self::parse($date) >= self::parse($dateToCompare);
    }

    public static function isBefore(string $date, string $dateToCompare)
    {
        return self::parse($date) < self::parse($dateToCompare);
    }

    public static function isBeforeEqual(string $date, string $dateToCompare)
    {
        return self::parse($date) <= self::parse($dateToCompare);
    }

    /**
     * A string without an offset is site time, like currentDateString() and the dates WordPress stores.
     */
    private static function parse(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date, \wp_timezone());
    }
}
