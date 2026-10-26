<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function sameSaudiNumber(): array
    {
        return [
            'local with 0' => ['0551234567', '+966551234567'],
            'local without 0' => ['551234567', '+966551234567'],
            'international with spaces' => ['+966 55 123 4567', '+966551234567'],
            'international with 00' => ['00966551234567', '+966551234567'],
            'country code without plus' => ['966551234567', '+966551234567'],
            'with dashes' => ['055-123-4567', '+966551234567'],
            'Arabic-Indic digits' => ['٠٥٥١٢٣٤٥٦٧', '+966551234567'],
        ];
    }

    #[DataProvider('sameSaudiNumber')]
    public function test_every_way_of_typing_a_saudi_number_gives_one_form(string $typed, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($typed));
    }

    public function test_other_countries_keep_their_code(): void
    {
        $this->assertSame('+201001234567', PhoneNumber::normalize('+20 100 123 4567'));
        $this->assertSame('+971501234567', PhoneNumber::normalize('00971501234567'));
    }

    public function test_what_is_not_a_number_is_refused(): void
    {
        $this->assertNull(PhoneNumber::normalize(null));
        $this->assertNull(PhoneNumber::normalize(''));
        $this->assertNull(PhoneNumber::normalize('12345'));
        $this->assertNull(PhoneNumber::normalize('055123'));
        $this->assertNull(PhoneNumber::normalize('+0551234567'));
        $this->assertNull(PhoneNumber::normalize('call me'));
    }
}
