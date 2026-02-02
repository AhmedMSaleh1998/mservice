<?php

namespace App\Support;

class CountryCodeOptions
{
    private const LIST = [
        ['code' => '+20', 'iso2' => 'EG', 'name' => 'Egypt'],
        ['code' => '+213', 'iso2' => 'DZ', 'name' => 'Algeria'],
        ['code' => '+216', 'iso2' => 'TN', 'name' => 'Tunisia'],
        ['code' => '+218', 'iso2' => 'LY', 'name' => 'Libya'],
        ['code' => '+249', 'iso2' => 'SD', 'name' => 'Sudan'],
        ['code' => '+212', 'iso2' => 'MA', 'name' => 'Morocco'],
        ['code' => '+970', 'iso2' => 'PS', 'name' => 'Palestine'],
        ['code' => '+962', 'iso2' => 'JO', 'name' => 'Jordan'],
        ['code' => '+961', 'iso2' => 'LB', 'name' => 'Lebanon'],
        ['code' => '+963', 'iso2' => 'SY', 'name' => 'Syria'],
        ['code' => '+964', 'iso2' => 'IQ', 'name' => 'Iraq'],
        ['code' => '+967', 'iso2' => 'YE', 'name' => 'Yemen'],
        ['code' => '+966', 'iso2' => 'SA', 'name' => 'Saudi Arabia'],
        ['code' => '+971', 'iso2' => 'AE', 'name' => 'United Arab Emirates'],
        ['code' => '+965', 'iso2' => 'KW', 'name' => 'Kuwait'],
        ['code' => '+973', 'iso2' => 'BH', 'name' => 'Bahrain'],
        ['code' => '+974', 'iso2' => 'QA', 'name' => 'Qatar'],
        ['code' => '+968', 'iso2' => 'OM', 'name' => 'Oman'],
        ['code' => '+90', 'iso2' => 'TR', 'name' => 'Turkey'],
        ['code' => '+1', 'iso2' => 'US', 'name' => 'United States'],
        ['code' => '+44', 'iso2' => 'GB', 'name' => 'United Kingdom'],
        ['code' => '+33', 'iso2' => 'FR', 'name' => 'France'],
        ['code' => '+49', 'iso2' => 'DE', 'name' => 'Germany'],
        ['code' => '+39', 'iso2' => 'IT', 'name' => 'Italy'],
        ['code' => '+34', 'iso2' => 'ES', 'name' => 'Spain'],
        ['code' => '+7', 'iso2' => 'RU', 'name' => 'Russia'],
        ['code' => '+91', 'iso2' => 'IN', 'name' => 'India'],
        ['code' => '+92', 'iso2' => 'PK', 'name' => 'Pakistan'],
        ['code' => '+86', 'iso2' => 'CN', 'name' => 'China'],
        ['code' => '+81', 'iso2' => 'JP', 'name' => 'Japan'],
        ['code' => '+82', 'iso2' => 'KR', 'name' => 'South Korea'],
    ];

    public static function options(): array
    {
        $options = [];

        foreach (self::LIST as $country) {
            $options[$country['code']] = self::formatLabel($country);
        }

        return $options;
    }

    public static function label(?string $code): ?string
    {
        if (! $code) {
            return null;
        }

        foreach (self::LIST as $country) {
            if ($country['code'] === $code) {
                return self::formatLabel($country);
            }
        }

        return $code;
    }

    public static function shortLabel(?string $code): ?string
    {
        if (! $code) {
            return null;
        }

        foreach (self::LIST as $country) {
            if ($country['code'] === $code) {
                $flag = self::flagEmoji($country['iso2']);
                return $flag ? "{$flag} {$country['code']}" : $country['code'];
            }
        }

        return $code;
    }

    private static function formatLabel(array $country): string
    {
        $flag = self::flagEmoji($country['iso2']);
        $label = "{$country['code']} {$country['name']}";

        return $flag ? "{$flag} {$label}" : $label;
    }

    private static function flagEmoji(string $iso2): string
    {
        $iso2 = strtoupper($iso2);

        if (strlen($iso2) !== 2) {
            return '';
        }

        $first = self::unicodeChr(0x1F1E6 + (ord($iso2[0]) - 65));
        $second = self::unicodeChr(0x1F1E6 + (ord($iso2[1]) - 65));

        return $first . $second;
    }

    private static function unicodeChr(int $codepoint): string
    {
        if (function_exists('mb_chr')) {
            return mb_chr($codepoint, 'UTF-8');
        }

        if (class_exists(\IntlChar::class)) {
            return \IntlChar::chr($codepoint);
        }

        return '';
    }
}
