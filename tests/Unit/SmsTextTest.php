<?php

namespace Tests\Unit;

use App\Services\Sms\SmsText;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SmsTextTest extends TestCase
{
    public function test_normalization_is_strict(): void
    {
        foreach (['09171234567', '639171234567', '+639171234567'] as $number) {
            $this->assertSame('+639171234567', SmsText::phone($number));
        }
        $this->expectException(InvalidArgumentException::class);
        SmsText::phone('call09171234567');
    }

    public function test_gsm_extensions_and_unicode_use_correct_units(): void
    {
        $this->assertSame(['encoding' => 'GSM-7', 'units' => 162, 'segments' => 2], SmsText::estimate(str_repeat('^', 81)));
        $this->assertSame(2, SmsText::estimate(str_repeat('a', 161))['segments']);
        $this->assertSame(['encoding' => 'Unicode', 'units' => 72, 'segments' => 2], SmsText::estimate(str_repeat('😀', 36)));
    }

    public function test_unknown_template_variables_fail_closed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SmsText::render('Hello {{untrusted}}', 'A', 'Campaign');
    }
}
