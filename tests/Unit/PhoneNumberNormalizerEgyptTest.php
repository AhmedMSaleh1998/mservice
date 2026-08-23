<?php

namespace Tests\Unit;

use App\Support\PhoneNumberNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNumberNormalizerEgyptTest extends TestCase
{
    public const CANONICAL = '201026513696';

    public static function egyptianFormsProvider(): array
    {
        return [
            'local with trunk zero' => ['01026513696'],
            'already canonical' => ['201026513696'],
            'plus prefixed' => ['+201026513696'],
            'international 00 prefix' => ['00201026513696'],
            'country code before trunk zero' => ['2001026513696'],
            'country code typed twice' => ['20201026513696'],
            'country code typed twice before trunk zero' => ['202001026513696'],
            'bare national number' => ['1026513696'],
            'arabic indic digits' => ['٠١٠٢٦٥١٣٦٩٦'],
            'eastern arabic digits' => ['۰۱۰۲۶۵۱۳۶۹۶'],
            'spaced' => ['0102 651 3696'],
            'mixed separators' => ['+20 102-651-3696'],
            'stacked prefixes and separators' => ['00 2020 1026513696'],
        ];
    }

    #[DataProvider('egyptianFormsProvider')]
    public function test_every_egyptian_form_resolves_to_one_canonical_number(string $input): void
    {
        $this->assertSame(self::CANONICAL, PhoneNumberNormalizer::normalize($input));
    }

    public function test_normalizing_is_idempotent(): void
    {
        $once = PhoneNumberNormalizer::normalize('01026513696');

        $this->assertSame($once, PhoneNumberNormalizer::normalize($once));
    }

    public function test_every_egyptian_operator_prefix_is_recognised(): void
    {
        // 010 Vodafone, 011 Etisalat, 012 Orange, 015 WE.
        foreach (['010', '011', '012', '015'] as $prefix) {
            $local = $prefix . '26513696';

            $this->assertSame(
                '20' . substr($local, 1),
                PhoneNumberNormalizer::normalize($local),
                sprintf('Operator prefix %s should normalize.', $prefix),
            );
        }
    }

    public function test_an_unknown_operator_prefix_is_not_treated_as_a_mobile(): void
    {
        // 013 is not an assigned Egyptian mobile prefix.
        $this->assertFalse(PhoneNumberNormalizer::isValidMobile('01326513696'));
    }

    public static function foreignNumbersProvider(): array
    {
        return [
            'saudi' => ['966501234567'],
            'lebanese' => ['9613456789'],
            'north american' => ['12125551234'],
        ];
    }

    #[DataProvider('foreignNumbersProvider')]
    public function test_foreign_numbers_pass_through_untouched(string $input): void
    {
        $this->assertSame($input, PhoneNumberNormalizer::normalize($input));
        $this->assertFalse(PhoneNumberNormalizer::isValidMobile($input));
    }

    public function test_it_reports_validity_for_egyptian_mobiles(): void
    {
        $this->assertTrue(PhoneNumberNormalizer::isValidMobile('01026513696'));
        $this->assertTrue(PhoneNumberNormalizer::isValidMobile('20201026513696'));
        $this->assertFalse(PhoneNumberNormalizer::isValidMobile('0102651'));
        $this->assertFalse(PhoneNumberNormalizer::isValidMobile(''));
    }

    public function test_variants_cover_the_canonical_and_local_forms(): void
    {
        $variants = PhoneNumberNormalizer::variants('٠١٠٢٦٥١٣٦٩٦');

        $this->assertContains(self::CANONICAL, $variants);
        $this->assertContains('01026513696', $variants);
        $this->assertContains('+' . self::CANONICAL, $variants);
    }

    public function test_an_empty_number_stays_empty(): void
    {
        $this->assertSame('', PhoneNumberNormalizer::normalize(''));
        $this->assertSame('', PhoneNumberNormalizer::normalize('---'));
    }
}
