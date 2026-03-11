<?php

namespace App\Support;

final class UploadedFileNameSanitizer
{
    public static function sanitize(string $fileName, string $fallback = 'document'): string
    {
        $extension = static::sanitizeExtension(pathinfo($fileName, PATHINFO_EXTENSION));
        $name = static::sanitizeSegment(pathinfo($fileName, PATHINFO_FILENAME), $fallback);

        return $extension === '' ? $name : "{$name}.{$extension}";
    }

    private static function sanitizeSegment(string $value, string $fallback): string
    {
        $sanitized = preg_replace('/\p{C}+/u', '', $value) ?? $value;
        $sanitized = str_replace(['/', '\\'], '-', $sanitized);
        $sanitized = preg_replace('/[?%*:|"<>]+/u', '-', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/\s+/u', ' ', $sanitized) ?? $sanitized;
        $sanitized = trim($sanitized, " \t\n\r\0\x0B.-_");

        return $sanitized !== '' ? $sanitized : $fallback;
    }

    private static function sanitizeExtension(string $extension): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9]+/', '', $extension) ?? $extension;

        return strtolower($sanitized);
    }
}
