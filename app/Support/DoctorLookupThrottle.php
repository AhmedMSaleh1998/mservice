<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Guards the syndicate lookup performed during registration against someone
 * probing registration numbers until one sticks.
 *
 * Only failed lookups are counted, so a doctor fixing a typo is never punished
 * for it, and a successful match clears the record entirely. Two buckets run in
 * parallel: one per identity (one person hammering registration numbers) and
 * one per IP (one source working through several identities).
 */
class DoctorLookupThrottle
{
    private const IDENTITY_PREFIX = 'doctor-lookup:identity:';

    private const IP_PREFIX = 'doctor-lookup:ip:';

    public function ensureNotThrottled(string $nationalId): void
    {
        foreach ($this->buckets($nationalId) as $bucket) {
            if (! RateLimiter::tooManyAttempts($bucket['key'], $bucket['max_attempts'])) {
                continue;
            }

            $seconds = RateLimiter::availableIn($bucket['key']);

            Log::warning('Doctor lookup throttled.', [
                'scope' => $bucket['scope'],
                // Logged unmasked on purpose: support searches the logs by the
                // exact national ID.
                'national_id' => NationalIdentifier::normalize($nationalId),
                'ip' => $this->clientIp(),
                'max_attempts' => $bucket['max_attempts'],
                'retry_after_seconds' => $seconds,
            ]);

            throw ValidationException::withMessages([
                'reg_number' => [__('auth.doctor_lookup_throttle', ['seconds' => $seconds])],
            ]);
        }
    }

    public function recordFailedAttempt(string $nationalId, string $registerNo): void
    {
        $decaySeconds = $this->decaySeconds();

        foreach ($this->buckets($nationalId) as $bucket) {
            RateLimiter::hit($bucket['key'], $decaySeconds);
        }

        Log::warning('Doctor lookup failed attempt recorded.', [
            // Logged unmasked on purpose: support searches the logs by the
            // exact national ID.
            'national_id' => NationalIdentifier::normalize($nationalId),
            'register_no' => NationalIdentifier::normalize($registerNo),
            'ip' => $this->clientIp(),
            'identity_attempts' => RateLimiter::attempts($this->identityKey($nationalId)),
            'ip_attempts' => RateLimiter::attempts($this->ipKey()),
        ]);
    }

    public function clear(string $nationalId): void
    {
        foreach ($this->buckets($nationalId) as $bucket) {
            RateLimiter::clear($bucket['key']);
        }
    }

    /**
     * @return array<int, array{scope: string, key: string, max_attempts: int}>
     */
    private function buckets(string $nationalId): array
    {
        return [
            [
                'scope' => 'identity',
                'key' => $this->identityKey($nationalId),
                'max_attempts' => (int) config('auth.doctor_lookup_throttle.identity.max_attempts', 5),
            ],
            [
                'scope' => 'ip',
                'key' => $this->ipKey(),
                'max_attempts' => (int) config('auth.doctor_lookup_throttle.ip.max_attempts', 15),
            ],
        ];
    }

    private function identityKey(string $nationalId): string
    {
        return self::IDENTITY_PREFIX . NationalIdentifier::fingerprint($nationalId);
    }

    private function ipKey(): string
    {
        return self::IP_PREFIX . sha1($this->clientIp());
    }

    private function clientIp(): string
    {
        return (string) (request()?->ip() ?? 'unknown');
    }

    private function decaySeconds(): int
    {
        return (int) config('auth.doctor_lookup_throttle.decay_seconds', 900);
    }
}
