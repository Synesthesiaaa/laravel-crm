<?php

namespace Tests\Unit\Support;

use App\Support\CsvCellEncoder;
use PHPUnit\Framework\TestCase;

class CsvCellEncoderTest extends TestCase
{
    public function test_formula_markers_after_control_whitespace_are_escaped(): void
    {
        foreach (['=1+1', "\t=1+1", "\r\n@SUM(A1:A2)", "\u{FEFF}+1", ' -1+CMD()'] as $value) {
            self::assertSame("'".$value, CsvCellEncoder::encode($value));
        }
    }

    public function test_only_strict_numeric_values_can_bypass_formula_prefix_escaping(): void
    {
        self::assertSame('-12.50', CsvCellEncoder::encode('-12.50', true));
        self::assertSame("'=1+1", CsvCellEncoder::encode('=1+1', true));
        self::assertSame("' -1+CMD()", CsvCellEncoder::encode(' -1+CMD()', true));
    }
}
