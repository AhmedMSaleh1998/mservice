<?php

namespace App\Support;

/**
 * Shared handling for the syndicate identifiers that arrive straight from user
 * input (national ID, registration number). Arabic-Indic digits, separators and
 * stray whitespace must collapse to one canonical value before anything keys on
 * them — otherwise the same identity reaches Oracle, the logs and the rate
 * limiter under several different spellings.
 */
class NationalIdentifier
{
    private const DIGIT_MAP = [
        '٠' => '0',
        '١' => '1',
        '٢' => '2',
        '٣' => '3',
        '٤' => '4',
        '٥' => '5',
        '٦' => '6',
        '٧' => '7',
        '٨' => '8',
        '٩' => '9',
        '۰' => '0',
        '۱' => '1',
        '۲' => '2',
        '۳' => '3',
        '۴' => '4',
        '۵' => '5',
        '۶' => '6',
        '۷' => '7',
        '۸' => '8',
        '۹' => '9',
    ];

    public static function normalize(string $value): string
    {
        $normalized = strtr(trim($value), self::DIGIT_MAP);

        return preg_replace('/\D+/', '', $normalized) ?? '';
    }

    public static function mask(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return str_repeat('*', max(strlen($value) - 4, 0)) . substr($value, -4);
    }

    /**
     * Stable, non-reversible label for a normalized identifier. Masking alone
     * hides everything but the last four digits, so two different identities
     * can look identical in the logs; the fingerprint tells them apart without
     * putting the identifier itself in a cache key or a log line.
     */
    public static function fingerprint(string $value): string
    {
        return substr(hash('sha256', self::normalize($value)), 0, 12);
    }
}
