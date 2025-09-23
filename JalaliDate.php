<?php

class JalaliDate {

    /**
     * Converts a Gregorian DateTime object or a date string to a formatted Jalali date string.
     * @param DateTime|string $date The date to convert.
     * @return string The formatted Jalali date string in 'YYYY/MM/DD' format.
     */
    public static function toJalali($date): string {
        if (is_string($date)) {
            try {
                $date = new DateTime($date);
            } catch (Exception $e) {
                return '';
            }
        }
        if (!($date instanceof DateTime)) {
            return '';
        }

        $formatter = new IntlDateFormatter(
            'fa_IR@calendar=persian',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            'Asia/Tehran',
            IntlDateFormatter::TRADITIONAL,
            'yyyy/MM/dd'
        );
        return self::convertNumbersToLatin($formatter->format($date));
    }

    /**
     * Converts a Jalali date string (e.g., "1404/05/21") to a Gregorian DateTime object.
     * @param string $jalaliDateString The Jalali date string.
     * @return DateTime|false A DateTime object on success, or false on failure.
     */
    public static function fromJalaliToDateTime(string $jalaliDateString) {
        $normalizedDate = self::convertNumbersToLatin($jalaliDateString);

        $formatter = new IntlDateFormatter(
            'fa_IR@calendar=persian',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'Asia/Tehran',
            IntlDateFormatter::TRADITIONAL,
            'yyyy/M/d'
        );

        $timestamp = $formatter->parse($normalizedDate);

        if ($timestamp === false) {
            $formatter->setPattern('yyyy/MM/dd');
            $timestamp = $formatter->parse($normalizedDate);
            if ($timestamp === false) {
                return false;
            }
        }

        $date = new DateTime();
        $date->setTimestamp($timestamp);
        $date->setTimezone(new DateTimeZone('Asia/Tehran'));

        return $date;
    }

    private static function convertNumbersToLatin(string $string): string {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($persian, $latin, $string);
    }

    /**
     * Checks if a given Jalali year is a leap year.
     * @param int $year The Jalali year.
     * @return bool True if it's a leap year, false otherwise.
     */
    public static function isLeapJalali(int $year): bool {
        // The 33-year cycle is the most common and accurate method.
        // The cycle starts from year 1. We check if the year falls into specific remainder patterns.
        $rem = ($year - 1) % 33;
        return in_array($rem, [0, 4, 8, 12, 16, 20, 24, 28]);
    }

    /**
     * Returns the number of days in a given Jalali month.
     * @param int $year The Jalali year.
     * @param int $month The Jalali month (1-12).
     * @return int The number of days in the month.
     */
    public static function daysInMonth(int $year, int $month): int {
        if ($month < 1 || $month > 12) {
            return 0;
        }
        if ($month <= 6) {
            return 31;
        }
        if ($month <= 11) {
            return 30;
        }
        // Last month (Esfand)
        return self::isLeapJalali($year) ? 30 : 29;
    }
}
