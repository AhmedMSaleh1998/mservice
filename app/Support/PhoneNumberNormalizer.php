<?php

namespace App\Support;

class PhoneNumberNormalizer
{
    public static function normalize(string $phone, ?string $countryCode = null): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

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

    public static function variants(string $phone, ?string $countryCode = null): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
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

    private static function countryCode(?string $countryCode): string
    {
        return preg_replace(
            '/\D+/',
            '',
            $countryCode ?? (string) config('services.community_sms.default_country_code', '20')
        ) ?? '';
    }
}
