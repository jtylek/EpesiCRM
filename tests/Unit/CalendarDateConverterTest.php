<?php

namespace Tests\Unit;

use Epesi\Modules\RegionalSettings\Calendar\CalendarDateConverter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CalendarDateConverterTest extends TestCase
{
    public function test_jalali_nowruz_conversion_round_trips(): void
    {
        $this->assertSame('1403-01-01', CalendarDateConverter::format('2024-03-20', 'jalali'));
        $this->assertSame('2024-03-20', CalendarDateConverter::parse('1403-01-01', 'jalali'));
    }

    #[DataProvider('calendarMonthBoundaries')]
    public function test_calendar_month_boundaries_match_reference_dates(string $gregorian, string $system, string $variant, string $expected): void
    {
        $this->assertSame($expected, CalendarDateConverter::format($gregorian, $system, $variant));
        $this->assertSame($gregorian, CalendarDateConverter::parse($expected, $system, $variant));
    }

    public static function calendarMonthBoundaries(): array
    {
        return [
            'Jalali leap day' => ['2021-03-20', 'jalali', 'umalqura', '1399-12-30'],
            'Jalali new year' => ['2021-03-21', 'jalali', 'umalqura', '1400-01-01'],
            'Umm al-Qura Ramadan starts' => ['2024-03-11', 'hijri', 'umalqura', '1445-09-01'],
            'Umm al-Qura Ramadan ends' => ['2024-04-09', 'hijri', 'umalqura', '1445-09-30'],
            'Umm al-Qura Shawwal starts' => ['2024-04-10', 'hijri', 'umalqura', '1445-10-01'],
            'Umm al-Qura Dhu al-Hijjah starts' => ['2024-06-07', 'hijri', 'umalqura', '1445-12-01'],
            'civil Hijri Dhu al-Hijjah starts' => ['2024-06-07', 'hijri', 'civil', '1445-11-30'],
        ];
    }

    #[DataProvider('invalidCalendarDates')]
    public function test_invalid_dates_are_rejected_for_each_calendar(string $calendarDate, string $system, string $variant): void
    {
        $this->expectException(InvalidArgumentException::class);

        CalendarDateConverter::parse($calendarDate, $system, $variant);
    }

    public static function invalidCalendarDates(): array
    {
        return [
            'Gregorian non-leap day' => ['2023-02-29', 'gregorian', 'umalqura'],
            'non-leap Jalali day' => ['1400-12-30', 'jalali', 'umalqura'],
            'Umm al-Qura thirty-first day' => ['1445-09-31', 'hijri', 'umalqura'],
            'civil Hijri thirty-first day' => ['1445-09-31', 'hijri', 'civil'],
        ];
    }

    #[DataProvider('algorithmicCalendarDateEndpoints')]
    public function test_algorithmic_calendars_round_trip_mysql_date_endpoints(string $gregorian, string $system, string $variant, string $expected): void
    {
        $this->assertSame($expected, CalendarDateConverter::format($gregorian, $system, $variant));
        $this->assertSame($gregorian, CalendarDateConverter::parse($expected, $system, $variant));
    }

    public static function algorithmicCalendarDateEndpoints(): array
    {
        return [
            'earliest Jalali date' => ['1000-01-01', 'jalali', 'umalqura', '0378-10-11'],
            'latest Jalali date' => ['9999-12-31', 'jalali', 'umalqura', '9378-10-10'],
            'earliest civil Hijri date' => ['1000-01-01', 'hijri', 'civil', '0390-01-15'],
            'latest civil Hijri date' => ['9999-12-31', 'hijri', 'civil', '9666-04-02'],
        ];
    }

    public function test_algorithmic_calendars_reject_dates_outside_mysql_date_range(): void
    {
        foreach (['0999-12-31', '10000-01-01'] as $gregorianDate) {
            try {
                CalendarDateConverter::format($gregorianDate, 'jalali');
                $this->fail('A Gregorian date outside MySQL DATE range must be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    #[DataProvider('localizedJalaliDates')]
    public function test_jalali_input_accepts_persian_and_arabic_indic_digits(string $date): void
    {
        $this->assertSame('2024-03-20', CalendarDateConverter::parse($date, 'jalali'));
    }

    public static function localizedJalaliDates(): array
    {
        return [
            'Persian digits' => ['۱۴۰۳-۰۱-۰۱'],
            'Arabic-Indic digits' => ['١٤٠٣-٠١-٠١'],
        ];
    }

    #[DataProvider('hijriCalendarDates')]
    public function test_hijri_variants_round_trip(string $variant, string $gregorianDate): void
    {
        $calendarDate = CalendarDateConverter::format($gregorianDate, 'hijri', $variant);

        $this->assertSame($gregorianDate, CalendarDateConverter::parse($calendarDate, 'hijri', $variant));
    }

    public function test_umm_al_qura_accepts_the_first_and_last_tabulated_dates(): void
    {
        $this->assertSame('1882-11-12', CalendarDateConverter::parse('1300-01-01', 'hijri', 'umalqura'));
        $this->assertSame('2174-11-25', CalendarDateConverter::parse('1600-12-30', 'hijri', 'umalqura'));
    }

    #[DataProvider('gregorianDatesOutsideUmmAlQuraTable')]
    public function test_umm_al_qura_rejects_gregorian_dates_outside_its_tabulated_range(string $gregorianDate): void
    {
        $this->expectException(InvalidArgumentException::class);

        CalendarDateConverter::format($gregorianDate, 'hijri', 'umalqura');
    }

    public static function gregorianDatesOutsideUmmAlQuraTable(): array
    {
        return [
            'before AH 1300' => ['1882-11-11'],
            'after AH 1600' => ['2174-12-01'],
        ];
    }

    #[DataProvider('hijriDatesOutsideUmmAlQuraTable')]
    public function test_umm_al_qura_rejects_hijri_years_outside_its_tabulated_range(string $calendarDate): void
    {
        $this->expectException(InvalidArgumentException::class);

        CalendarDateConverter::parse($calendarDate, 'hijri', 'umalqura');
    }

    public static function hijriDatesOutsideUmmAlQuraTable(): array
    {
        return [
            'before AH 1300' => ['1299-12-30'],
            'after AH 1600' => ['1601-01-01'],
        ];
    }

    public static function hijriCalendarDates(): array
    {
        return [
            'Umm al-Qura' => ['umalqura', '2024-03-11'],
            'civil' => ['civil', '2024-03-11'],
        ];
    }

    public function test_invalid_calendar_dates_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CalendarDateConverter::parse('1402-13-01', 'jalali');
    }

    public function test_unknown_system_or_variant_is_rejected(): void
    {
        try {
            CalendarDateConverter::format('2024-03-20', 'unknown');
            $this->fail('An unknown calendar system should be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        CalendarDateConverter::format('2024-03-20', 'hijri', 'unknown');
    }
}
