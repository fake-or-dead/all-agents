<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ThaiDateFormatter;
use PHPUnit\Framework\TestCase;

final class ThaiDateFormatterTest extends TestCase
{
    public function test_it_formats_buddhist_civil_dates_in_the_session_timezone(): void
    {
        $formatter = new ThaiDateFormatter;

        self::assertSame(
            '1 กรกฎาคม 2569 เวลา 00:00 น. (Asia/Bangkok)',
            $formatter->dateTime('2026-06-30 17:00:00+00', 'Asia/Bangkok'),
        );
        self::assertSame(
            '5 สิงหาคม 2569 เวลา 23:59 น. (Asia/Bangkok)',
            $formatter->dateTime('2026-08-05 16:59:59+00', 'Asia/Bangkok'),
        );
        self::assertSame(
            '10 สิงหาคม 2569',
            $formatter->date('2026-08-10', 'Asia/Bangkok'),
        );
        self::assertSame(2569, $formatter->buddhistYear(2026));
        self::assertSame('สิงหาคม', $formatter->monthName(8));
    }
}
