<?php

namespace helper;

class DateTimeHelper
{
    public static function currentDateString()
    {
        return \current_time("c");
    }

    public static function isBetween($date, $startDate, $endDate = null)
    {
        if (is_null($startDate) && is_null($endDate)) {
            return true;
        }

        if (is_null($startDate)) {
            return self::isBeforeEqual($date, $endDate);
        }

        if (is_null($endDate)) {
            return self::isAfterEqual($date, $startDate);
        }

        return self::isAfterEqual($date, $startDate) && self::isBeforeEqual($date, $endDate);
    }

    public static function isAfter(string $date, string $dateToCompare)
    {
        return self::isCompareWith($date, $dateToCompare, 'gt');
    }

    public static function isAfterEqual(string $date, string $dateToCompare)
    {
        return self::isCompareWith($date, $dateToCompare, 'gte');
    }

    public static function isBefore(string $date, string $dateToCompare)
    {
        return self::isCompareWith($date, $dateToCompare, 'lt');
    }

    public static function isBeforeEqual(string $date, string $dateToCompare)
    {
        return self::isCompareWith($date, $dateToCompare, 'lte');
    }

    private static function isCompareWith(string $date, string $dateToCompare, string $comparator)
    {
        // A string without an offset is site time, like currentDateString() and the dates WordPress stores.
        $dateObject          = new \DateTimeImmutable($date, \wp_timezone());
        $dateToCompareObject = new \DateTimeImmutable($dateToCompare, \wp_timezone());

        switch ($comparator) {
            case 'eq':
                return $dateToCompareObject == $dateObject;
            case 'gt':
                return $dateToCompareObject < $dateObject;
            case 'gte':
                return $dateToCompareObject <= $dateObject;
            case 'lt':
                return $dateToCompareObject > $dateObject;
            case 'lte':
                return $dateToCompareObject >= $dateObject;
        }
    }
}
