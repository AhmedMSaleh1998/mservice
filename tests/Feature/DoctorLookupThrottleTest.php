<?php

namespace Tests\Feature;

use App\Support\DoctorLookupThrottle;
use App\Support\NationalIdentifier;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DoctorLookupThrottleTest extends TestCase
{
    private const NATIONAL_ID = '29901011234567';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        config()->set('auth.doctor_lookup_throttle.identity.max_attempts', 3);
        config()->set('auth.doctor_lookup_throttle.ip.max_attempts', 10);
        config()->set('auth.doctor_lookup_throttle.decay_seconds', 900);

        RateLimiter::clear('doctor-lookup:identity:' . NationalIdentifier::fingerprint(self::NATIONAL_ID));
    }

    public function test_it_allows_attempts_up_to_the_identity_limit(): void
    {
        $throttle = new DoctorLookupThrottle();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $throttle->ensureNotThrottled(self::NATIONAL_ID);
            $throttle->recordFailedAttempt(self::NATIONAL_ID, '12345');
        }

        $this->expectException(ValidationException::class);
        $throttle->ensureNotThrottled(self::NATIONAL_ID);
    }

    public function test_it_reports_the_retry_delay_on_the_reg_number_field(): void
    {
        $throttle = new DoctorLookupThrottle();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $throttle->recordFailedAttempt(self::NATIONAL_ID, '12345');
        }

        try {
            $throttle->ensureNotThrottled(self::NATIONAL_ID);
            $this->fail('The lookup should be throttled after the limit is reached.');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['reg_number'][0] ?? '';

            $this->assertNotSame('', $message);
            $this->assertStringNotContainsString(':seconds', $message);
        }
    }

    public function test_a_successful_match_clears_the_recorded_failures(): void
    {
        $throttle = new DoctorLookupThrottle();

        $throttle->recordFailedAttempt(self::NATIONAL_ID, '12345');
        $throttle->recordFailedAttempt(self::NATIONAL_ID, '12346');
        $throttle->clear(self::NATIONAL_ID);

        // A cleared budget must survive another full run of failures.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $throttle->ensureNotThrottled(self::NATIONAL_ID);
            $throttle->recordFailedAttempt(self::NATIONAL_ID, '12345');
        }

        $this->expectException(ValidationException::class);
        $throttle->ensureNotThrottled(self::NATIONAL_ID);
    }

    public function test_alternate_digit_spellings_share_one_budget(): void
    {
        $throttle = new DoctorLookupThrottle();

        // Same identity typed with Arabic-Indic digits and separators must not
        // hand the caller a fresh set of attempts.
        $throttle->recordFailedAttempt('299-0101-1234567', '12345');
        $throttle->recordFailedAttempt('٢٩٩٠١٠١١٢٣٤٥٦٧', '12346');
        $throttle->recordFailedAttempt(self::NATIONAL_ID, '12347');

        $this->expectException(ValidationException::class);
        $throttle->ensureNotThrottled(self::NATIONAL_ID);
    }

    public function test_a_different_identity_keeps_its_own_budget(): void
    {
        $otherId = '29801011234567';
        RateLimiter::clear('doctor-lookup:identity:' . NationalIdentifier::fingerprint($otherId));

        $throttle = new DoctorLookupThrottle();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $throttle->recordFailedAttempt(self::NATIONAL_ID, '12345');
        }

        // The IP bucket is set to 10 here, so only the identity bucket is spent.
        $throttle->ensureNotThrottled($otherId);

        $this->assertTrue(true);
    }

    public function test_the_ip_bucket_stops_probing_across_many_identities(): void
    {
        config()->set('auth.doctor_lookup_throttle.identity.max_attempts', 50);
        config()->set('auth.doctor_lookup_throttle.ip.max_attempts', 4);

        $throttle = new DoctorLookupThrottle();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $throttle->recordFailedAttempt('2990101123456' . $attempt, '12345');
        }

        $this->expectException(ValidationException::class);
        $throttle->ensureNotThrottled('29901011234569');
    }
}
