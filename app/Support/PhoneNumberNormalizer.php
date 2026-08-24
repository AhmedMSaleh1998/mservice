<?php

namespace App\Support;

class PhoneNumberNormalizer
{
    /**
     * National (subscriber) number shapes we can recognise with certainty, keyed
     * by country calling code.
     *
     * Egyptian mobiles are rigid enough to normalise structurally: the national
     * number is always 1 + operator digit (0 Vodafone, 1 Etisalat, 2 Orange,
     * 5 WE) + 8 subscriber digits. Anything that peels down to that shape is an
     * Egyptian mobile no matter how many trunk zeros or country codes were
     * stacked in front of it.
     */
    private const NATIONAL_NUMBER_PATTERNS = [
        '20' => '/^1[0125]\d{8}$/',
    ];

    public static function normalize(string $phone, ?string $countryCode = null): string
    {
        $digits = self::digits($phone);

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        $countryCode = self::countryCode($countryCode);

        if ($countryCode === '') {
            return $digits;
        }

        // Where the national number shape is known, that shape is the entire
        // rule: peel down to it, or hand the number back exactly as it came in.
        // Attaching a country code to anything else is guesswork — it is what
        // turned foreign local numbers such as 0501234567 into 20501234567.
        if (isset(self::NATIONAL_NUMBER_PATTERNS[$countryCode])) {
            $nationalNumber = self::peelToNationalNumber($digits, $countryCode);

            return $nationalNumber === null
                ? $digits
                : $countryCode . $nationalNumber;
        }

        return self::legacyNormalize($digits, $countryCode);
    }

    /**
     * Prefix juggling for country codes we have no structural rule for. Kept so
     * that pointing `default_country_code` at another country keeps behaving as
     * it did before Egyptian numbers gained a shape of their own.
     */
    private static function legacyNormalize(string $digits, string $countryCode): string
    {
        if (str_starts_with($digits, $countryCode . '0')) {
            return $countryCode . substr($digits, strlen($countryCode) + 1);
        }

        if (str_starts_with($digits, $countryCode)) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return $countryCode . substr($digits, 1);
        }

        return $digits;
    }

    /**
     * True when the number resolves to a recognised mobile for the country —
     * i.e. normalize() produced a number we can vouch for structurally rather
     * than one it simply passed through untouched.
     */
    public static function isValidMobile(string $phone, ?string $countryCode = null): bool
    {
        $resolvedCountryCode = self::countryCode($countryCode);

        if (! isset(self::NATIONAL_NUMBER_PATTERNS[$resolvedCountryCode])) {
            return false;
        }

        $digits = self::digits($phone);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return self::peelToNationalNumber($digits, $resolvedCountryCode) !== null;
    }

    public static function variants(string $phone, ?string $countryCode = null): array
    {
        $digits = self::digits($phone);
        $normalized = self::normalize($phone, $countryCode);
        $countryCode = self::countryCode($countryCode);
        $variants = [$phone, $digits, $normalized];

        foreach ([$digits, $normalized] as $value) {
            if ($value !== '') {
                $variants[] = '+' . $value;
                $variants[] = '00' . $value;
            }
        }

        if ($countryCode !== '' && str_starts_with($normalized, $countryCode)) {
            $local = '0' . substr($normalized, strlen($countryCode));

            $variants[] = $local;
            $variants[] = $countryCode . $local;
            $variants[] = '+' . $countryCode . $local;
        }

        return array_values(array_unique(array_filter($variants, static fn ($value): bool => $value !== '')));
    }

    /**
     * Strip trunk zeros and stacked country codes until what remains matches the
     * country's national number shape. Returns null when the number never gets
     * there, which leaves foreign numbers untouched.
     */
    private static function peelToNationalNumber(string $digits, string $countryCode): ?string
    {
        $pattern = self::NATIONAL_NUMBER_PATTERNS[$countryCode] ?? null;

        if ($pattern === null) {
            return null;
        }

        $candidate = $digits;

        // Every pass removes at least one leading digit, so this terminates.
        while ($candidate !== '') {
            if (preg_match($pattern, $candidate) === 1) {
                return $candidate;
            }

            if (str_starts_with($candidate, '0')) {
                $candidate = substr($candidate, 1);
                continue;
            }

            if (str_starts_with($candidate, $countryCode) && strlen($candidate) > strlen($countryCode)) {
                $candidate = substr($candidate, strlen($countryCode));
                continue;
            }

            return null;
        }

        return null;
    }

    /**
     * Digits only, with Arabic-Indic and Eastern Arabic numerals folded to ASCII
     * first — otherwise a number typed on an Arabic keypad collapses to nothing.
     */
    private static function digits(string $phone): string
    {
        return NationalIdentifier::normalize($phone);
    }

    private static function countryCode(?string $countryCode): string
    {
        return preg_replace(
            '/\D+/',
            '',
            $countryCode ?? (string) config('services.community_sms.default_country_code', '20')
        ) ?? '';
    }
}
