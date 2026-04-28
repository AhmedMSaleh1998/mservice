<?php

namespace Modules\Users\Services;

use App\Services\Oracle\OracleDoctorExistenceService;
use App\Support\PhoneNumberNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\Otp;
use Modules\Users\Dto\LoginDTO;
use Modules\Users\Dto\RegisterDTO;
use Modules\Users\Models\User;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class AuthService
{
    public function __construct(
        private readonly OracleDoctorExistenceService $oracleDoctorExistenceService,
    ) {
    }

    public function register(RegisterDTO $dto): User
    {
        $phoneVariants = PhoneNumberNormalizer::variants($dto->phone);
        $existingUser = User::query()
            ->whereIn('phone', $phoneVariants)
            ->latest('id')
            ->first();

        if ($existingUser?->active) {
            throw ValidationException::withMessages([
                'phone' => [__('Phone number already exists.')],
            ]);
        }

        if ($existingUser) {
            throw ValidationException::withMessages([
                'phone' => [__('A verification code has already been sent to this phone number. Please verify the account or request a new code.')],
            ]);
        }

//        // Verify that the phone has been verified
//        $verifiedPhoneOtp = Otp::where('phone', $dto->phone)
//            ->where('action', 'register')
//            ->where('verified_at', '!=', null)
//            ->where('verified_at', '>', now()->subMinutes(3))
//            ->where('is_used', false)
//            ->latest()
//            ->first();
//        if (!$verifiedPhoneOtp) {
//            throw ValidationException::withMessages([
//                'phone' => [__('This phone number has not been verified.')],
//            ]);
//        }
        try {
            $doctorExists = $this->oracleDoctorExistenceService->doctorExists($dto->regNumber, $dto->nationalId);
        } catch (RuntimeException $exception) {
            throw new ServiceUnavailableHttpException(
                null,
                __('Unable to verify doctor data with Oracle at the moment. Please try again later.'),
                $exception,
            );
        }

        if (! $doctorExists) {
            throw ValidationException::withMessages([
                'reg_number' => [__('No doctor record matches the provided registration number and national ID in Oracle.')],
            ]);
        }

        return User::create([
            'name' => $dto->name,
            'phone' => PhoneNumberNormalizer::normalize($dto->phone),
            'password' => bcrypt($dto->password),
            'national_id' => $dto->nationalId,
            'email' => $dto->email,
            'reg_number' => $dto->regNumber,
        ]);
    }

    public function login(LoginDTO $dto)
    {
        $credentials = [
            'phone' => PhoneNumberNormalizer::normalize($dto->phone),
            'password' => $dto->password,
        ];

        if (!Auth::attempt($credentials, $dto->remember)) {
            throw ValidationException::withMessages([
                'phone' => [__('auth.failed')],
            ]);
        }

        $user = Auth::user();

        if (!$user->active) {
            throw ValidationException::withMessages([
                'email' => [__('auth.inactive')],
            ]);
        }

        // Revoke any existing tokens
        $user->tokens()->where('name', 'auth_token')->delete();

        // Create token with expiration
        $expiresAt = Carbon::now()->addDays(120);
        $accessToken = $user->createToken($this->generateDeviceName(), ['*'], $expiresAt);
        $user->auth_token = $accessToken->plainTextToken;

        return $user;
    }

    public function logout(): bool
    {
        $user = Auth::user();

        if ($user) {
            // For API logout - revoke all tokens
            if (request()->expectsJson()) {
//                $user->tokens()->delete();
                $user->currentAccessToken()->delete();
            } else {
                Auth::logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();
            }

            return true;
        }

        return false;
    }

    private function generateDeviceName(): string
    {
        // Get user agent
        $userAgent = request()->header('User-Agent');

        // Try to identify device type
        $deviceType = 'unknown';
        if (str_contains($userAgent, 'Mobile') || str_contains($userAgent, 'Android') || str_contains($userAgent, 'iPhone')) {
            $deviceType = 'mobile';
        } elseif (str_contains($userAgent, 'Tablet') || str_contains($userAgent, 'iPad')) {
            $deviceType = 'tablet';
        } else {
            $deviceType = 'desktop';
        }

        // Add timestamp to make it unique
        return $deviceType . '_' . now()->timestamp;
    }
}
