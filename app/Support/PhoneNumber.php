<?php

namespace App\Support;

/**
 * Turns a phone number as people type it into one international form,
 * +[country][number], so the same customer is never two contacts.
 *
 * Without a leading + or 00 a number is read as Saudi: 05XXXXXXXX and
 * 5XXXXXXXX both become +9665XXXXXXXX. Arabic-Indic digits are accepted.
 * Anything that is not 8 to 15 digits after that is refused.
 */
final class PhoneNumber
{
    public const string DEFAULT_COUNTRY_CODE = '966';

    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $value = strtr(trim($input), [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        $international = str_starts_with($value, '+') || str_starts_with($value, '00');
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($international) {
            $digits = str_starts_with($value, '00') ? substr($digits, 2) : $digits;
        } elseif (str_starts_with($digits, self::DEFAULT_COUNTRY_CODE) && strlen($digits) >= 11) {
            // 9665XXXXXXXX typed without the plus.
        } elseif (preg_match('/^05\d{8}$/', $digits) === 1) {
            $digits = self::DEFAULT_COUNTRY_CODE.substr($digits, 1);
        } elseif (preg_match('/^5\d{8}$/', $digits) === 1) {
            $digits = self::DEFAULT_COUNTRY_CODE.$digits;
        } else {
            return null;
        }

        if (preg_match('/^[1-9]\d{7,14}$/', $digits) !== 1) {
            return null;
        }

        return '+'.$digits;
    }
}
