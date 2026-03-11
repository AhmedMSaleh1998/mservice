<?php

namespace Tests\Unit;

use App\Support\UploadedFileNameSanitizer;
use PHPUnit\Framework\TestCase;

class UploadedFileNameSanitizerTest extends TestCase
{
    public function test_it_removes_unicode_control_characters_and_preserves_extension(): void
    {
        $fileName = "graduation_certificate_image__⁨شهادة التخرج_1⁩.JPG";

        $this->assertSame(
            'graduation_certificate_image__شهادة التخرج_1.jpg',
            UploadedFileNameSanitizer::sanitize($fileName),
        );
    }

    public function test_it_falls_back_when_the_visible_name_becomes_empty(): void
    {
        $fileName = "\u{200F}\u{2068}\u{2069}.jpeg";

        $this->assertSame('personal_image.jpeg', UploadedFileNameSanitizer::sanitize($fileName, 'personal_image'));
    }
}
