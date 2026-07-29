<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Throwable;

final class ThaiDateFormatter
{
    /** @var array<int, string> */
    private const MONTHS = [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม',
    ];

    public function date(string $value, string $timezone): string
    {
        try {
            $zone = new DateTimeZone($timezone);
            $date = CarbonImmutable::parse($value, $zone);
        } catch (Throwable) {
            return 'ข้อมูลวันที่ไม่ถูกต้อง';
        }

        return $this->civilDate($date);
    }

    public function dateTime(string $value, string $timezone): string
    {
        try {
            $zone = new DateTimeZone($timezone);
            $date = CarbonImmutable::parse($value)->setTimezone($zone);
        } catch (Throwable) {
            return 'ข้อมูลวันเวลาไม่ถูกต้อง';
        }

        return sprintf(
            '%s เวลา %s น. (%s)',
            $this->civilDate($date),
            $date->format('H:i'),
            $timezone,
        );
    }

    public function buddhistYear(int $gregorianYear): int
    {
        return $gregorianYear + 543;
    }

    public function monthName(int $month): string
    {
        return self::MONTHS[$month] ?? 'เดือนไม่ถูกต้อง';
    }

    private function civilDate(CarbonImmutable $date): string
    {
        return sprintf(
            '%d %s %d',
            $date->day,
            $this->monthName($date->month),
            $this->buddhistYear($date->year),
        );
    }
}
