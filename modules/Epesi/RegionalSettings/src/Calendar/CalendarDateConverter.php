<?php

namespace Epesi\Modules\RegionalSettings\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use InvalidArgumentException;

final class CalendarDateConverter
{
    public const SYSTEMS = ['gregorian', 'jalali', 'hijri'];

    public const HIJRI_VARIANTS = ['umalqura', 'civil'];

    public const UMM_AL_QURA_MIN_YEAR = 1300;

    public const UMM_AL_QURA_MAX_YEAR = 1600;

    private const MIN_GREGORIAN_STORAGE_DATE = '1000-01-01';

    private const MAX_GREGORIAN_STORAGE_DATE = '9999-12-31';

    public static function format(string $gregorianDate, string $system, string $hijriVariant = 'umalqura'): string
    {
        self::assertSystem($system);
        $date = self::parseGregorian($gregorianDate);

        if ($system === 'gregorian') {
            return $gregorianDate;
        }

        self::assertGregorianStorageDate($gregorianDate);
        $calendarDate = self::formatter($system, $hijriVariant)->format($date->getTimestamp());

        if ($system === 'hijri' && $hijriVariant === 'umalqura') {
            self::assertUmmAlQuraYear((int) substr($calendarDate, 0, 4));
        }

        return $calendarDate;
    }

    public static function parse(string $calendarDate, string $system, string $hijriVariant = 'umalqura'): string
    {
        self::assertSystem($system);

        if ($system === 'gregorian') {
            self::parseGregorian($calendarDate);

            return $calendarDate;
        }

        $calendarDate = self::normalizeInputDigits($calendarDate);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $calendarDate)) {
            throw new InvalidArgumentException('Calendar dates must use YYYY-MM-DD.');
        }

        $formatter = self::formatter($system, $hijriVariant);
        if ($system === 'hijri' && $hijriVariant === 'umalqura') {
            self::assertUmmAlQuraYear((int) substr($calendarDate, 0, 4));
        }

        $position = 0;
        $timestamp = $formatter->parse($calendarDate, $position);

        if ($timestamp === false || $position !== strlen($calendarDate) || $formatter->format($timestamp) !== $calendarDate) {
            throw new InvalidArgumentException('The calendar date is invalid or outside the supported range.');
        }

        $gregorianDate = gmdate('Y-m-d', (int) $timestamp);
        self::assertGregorianStorageDate($gregorianDate);

        return $gregorianDate;
    }

    public static function normalizeInputDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    private static function parseGregorian(string $date): DateTimeImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('Gregorian dates must use YYYY-MM-DD.');
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('The Gregorian date is invalid.');
        }

        return $parsed;
    }

    private static function formatter(string $system, string $hijriVariant): IntlDateFormatter
    {
        $calendar = match ($system) {
            'jalali' => 'persian',
            'hijri' => match ($hijriVariant) {
                'umalqura' => 'islamic-umalqura',
                'civil' => 'islamic-civil',
                default => throw new InvalidArgumentException('Unsupported Hijri calendar variant.'),
            },
            default => throw new InvalidArgumentException('Gregorian dates do not need a calendar formatter.'),
        };

        $formatter = new IntlDateFormatter(
            'en_US@calendar='.$calendar,
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'UTC',
            IntlDateFormatter::TRADITIONAL,
            'yyyy-MM-dd',
        );
        $formatter->setLenient(false);

        return $formatter;
    }

    private static function assertUmmAlQuraYear(int $year): void
    {
        if ($year < self::UMM_AL_QURA_MIN_YEAR || $year > self::UMM_AL_QURA_MAX_YEAR) {
            throw new InvalidArgumentException('Umm al-Qura supports Hijri years 1300 through 1600.');
        }
    }

    private static function assertGregorianStorageDate(string $date): void
    {
        if ($date < self::MIN_GREGORIAN_STORAGE_DATE || $date > self::MAX_GREGORIAN_STORAGE_DATE) {
            throw new InvalidArgumentException('Alternate calendars support Gregorian storage dates from 1000-01-01 through 9999-12-31.');
        }
    }

    private static function assertSystem(string $system): void
    {
        if (! in_array($system, self::SYSTEMS, true)) {
            throw new InvalidArgumentException('Unsupported calendar system.');
        }
    }
}
