<?php

namespace App\Services\Payments;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Ads\Models\AdRequest;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Core\Models\Order;
use Modules\Courses\Models\CourseBooking;
use Modules\Memberships\Models\MembershipRequest;
use Modules\Services\Models\RestUnitBooking;
use Modules\Travels\Models\TravelBooking;
use Modules\Users\Models\User;
use RuntimeException;

class FawryHostedCheckoutService
{
    private const INIT_PATH = '/fawrypay-api/api/payments/init';

    private const STATUS_PATH = '/ECommerceWeb/Fawry/payments/status/v2';

    public function isEnabled(): bool
    {
        return (bool) config('services.fawry.enabled')
            && filled(config('services.fawry.merchant_code'))
            && filled(config('services.fawry.secure_key'));
    }

    public function createCheckout(Order $order, User $user, string $locale): array
    {
        $this->ensureConfigured();
        $this->ensureCustomerData($user);
        $order->loadMissing('orderable');

        // Fawry requires a fresh merchant reference for every new charge request.
        $merchantRefNum = $this->buildMerchantRefNum($order);
        $returnUrl = $this->returnUrl();
        $chargeItems = $this->buildChargeItems($order);
        $totalAmount = $this->formatAmount($order->amount);

        $payload = [
            'merchantCode' => $this->merchantCode(),
            'merchantRefNum' => $merchantRefNum,
            'customerProfileId' => (string) $user->id,
            'customerName' => (string) $user->name,
            'customerMobile' => (string) $user->phone,
            'customerEmail' => (string) $user->email,
            'amount' => $totalAmount,
            'currencyCode' => (string) config('services.fawry.currency_code', 'EGP'),
            'paymentMethod' => (string) config('services.fawry.payment_method', 'CARD'),
            'paymentExpiry' => $this->paymentExpiry($order),
            'chargeItems' => $chargeItems,
            'returnUrl' => $returnUrl,
            'signature' => $this->buildChargeSignature($merchantRefNum, (string) $user->id, $returnUrl, $chargeItems),
        ];

        $response = Http::asJson()
            ->timeout(20)
            ->connectTimeout(5)
            ->post($this->initUrl(), $payload);

        if (! $response->successful()) {
            Log::warning('Fawry init request failed', [
                'order_id' => $order->id,
                'merchant_ref_num' => $merchantRefNum,
                'http_status' => $response->status(),
                'request' => Arr::except($payload, ['signature']),
                'response_body' => $response->body(),
                'response_json' => $response->json(),
            ]);

            $response->throw();
        }

        $responseData = $response->json();
        $paymentUrl = null;

        if (is_array($responseData)) {
            $statusCode = (int) data_get($responseData, 'statusCode', 200);
            if ($statusCode !== 200) {
                Log::warning('Fawry charge request rejected', [
                    'order_id' => $order->id,
                    'merchant_ref_num' => $merchantRefNum,
                    'status_code' => $statusCode,
                    'status_description' => data_get($responseData, 'statusDescription'),
                    'request' => Arr::except($payload, ['signature']),
                    'response' => $responseData,
                ]);

                throw new RuntimeException((string) data_get($responseData, 'statusDescription', 'Unable to create Fawry checkout.'));
            }

            $paymentUrl = $this->extractPaymentUrl($responseData);
        } else {
            $paymentUrl = $this->extractPaymentUrlFromSuccessfulResponse($response->headers(), $response->body());

            Log::warning('Fawry init returned non-json success response', [
                'order_id' => $order->id,
                'merchant_ref_num' => $merchantRefNum,
                'http_status' => $response->status(),
                'request' => Arr::except($payload, ['signature']),
                'response_headers' => $response->headers(),
                'response_body' => $response->body(),
                'derived_payment_url' => $paymentUrl,
            ]);
        }

        if (blank($paymentUrl)) {
            throw new RuntimeException('Fawry checkout response did not include a payment URL.');
        }

        return [
            'mode' => 'redirect',
            'provider' => 'fawry',
            'status' => 'checkout_pending',
            'payment_method' => 'fawry',
            'reference' => $merchantRefNum,
            'gateway_reference' => data_get($responseData, 'referenceNumber'),
            'payment_url' => $paymentUrl,
            'instructions' => __('Open the payment URL to complete your payment via Fawry.'),
            'gateway_status' => (string) data_get($responseData, 'type', 'NEW'),
            'charge_request' => $payload,
            'charge_response' => is_array($responseData) ? $responseData : [
                'raw_body' => $response->body(),
                'headers' => $response->headers(),
            ],
            'merchant_ref_num' => $merchantRefNum,
            'reference_number' => data_get($responseData, 'referenceNumber'),
        ];
    }

    public function pullPaymentStatus(Order $order): array
    {
        $this->ensureConfigured();

        if (blank($order->merchant_ref_num)) {
            throw ValidationException::withMessages([
                'payment' => __('Checkout has not been started for this order.'),
            ]);
        }

        $response = Http::acceptJson()
            ->timeout(20)
            ->connectTimeout(5)
            ->get($this->statusUrl(), [
                'merchantCode' => $this->merchantCode(),
                'merchantRefNumber' => $order->merchant_ref_num,
                'signature' => hash('sha256', $this->merchantCode() . $order->merchant_ref_num . $this->secureKey()),
            ])
            ->throw();

        return $response->json();
    }

    public function verifyReturnSignature(array $payload): bool
    {
        $signature = strtolower((string) data_get($payload, 'signature'));
        if ($signature === '') {
            return false;
        }

        $expected = hash('sha256',
            (string) data_get($payload, 'referenceNumber')
            . (string) data_get($payload, 'merchantRefNumber')
            . $this->formatAmount(data_get($payload, 'paymentAmount'))
            . $this->formatAmount(data_get($payload, 'orderAmount'))
            . (string) data_get($payload, 'orderStatus')
            . (string) data_get($payload, 'paymentMethod')
            . $this->optionalAmount(data_get($payload, 'fawryFees'))
            . $this->optionalAmount(data_get($payload, 'shippingFees'))
            . (string) data_get($payload, 'authNumber')
            . (string) data_get($payload, 'customerMail')
            . (string) data_get($payload, 'customerMobile')
            . $this->secureKey()
        );

        return hash_equals($expected, $signature);
    }

    public function verifyStatusSignature(array $payload): bool
    {
        $signature = strtolower((string) data_get($payload, 'messageSignature', data_get($payload, 'signature')));
        if ($signature === '') {
            return false;
        }

        $paymentReference = (string) data_get($payload, 'paymentRefrenceNumber', data_get($payload, 'paymentReferenceNumber'));

        $expected = hash('sha256',
            (string) data_get($payload, 'fawryRefNumber')
            . (string) data_get($payload, 'merchantRefNumber')
            . $this->formatAmount(data_get($payload, 'paymentAmount'))
            . $this->formatAmount(data_get($payload, 'orderAmount'))
            . (string) data_get($payload, 'orderStatus')
            . (string) data_get($payload, 'paymentMethod')
            . $paymentReference
            . $this->secureKey()
        );

        return hash_equals($expected, $signature);
    }

    public function frontendReturnUrl(array $query): ?string
    {
        $url = (string) config('services.fawry.frontend_return_url', '');
        if ($url === '') {
            return null;
        }

        $normalizedUrl = rtrim($url, '/');
        $normalizedAppUrl = rtrim((string) config('app.url', ''), '/');

        if ($normalizedAppUrl !== '' && $normalizedUrl === $normalizedAppUrl) {
            $url = route('api.orders.fawry.result');
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query(array_filter(
            $query,
            static fn ($value) => ! is_null($value) && $value !== ''
        ));
    }

    public function returnUrl(): string
    {
        return (string) config('services.fawry.return_url', route('api.orders.fawry.return'));
    }

    public function notificationUrl(): string
    {
        return (string) config('services.fawry.webhook_url', route('api.orders.fawry.notification'));
    }

    private function ensureConfigured(): void
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Fawry checkout is not configured.');
        }
    }

    private function ensureCustomerData(User $user): void
    {
        if (blank($user->phone)) {
            throw ValidationException::withMessages([
                'phone' => __('A valid mobile number is required before starting Fawry checkout.'),
            ]);
        }

        if (blank($user->email) || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => __('A valid email address is required before starting Fawry checkout.'),
            ]);
        }
    }

    private function buildChargeItems(Order $order): array
    {
        $order->loadMissing('orderable');
        $orderable = $order->orderable;

        if ($orderable instanceof AdRequest) {
            $orderable->loadMissing('adSpace.service');
            $baseAmount = $this->formatAmount((float) $orderable->price_per_month * (int) $orderable->duration_months);

            if ($this->formatAmount($order->amount) !== $baseAmount) {
                return [[
                    'itemId' => sprintf('ADREQ%d', $orderable->id),
                    'description' => sprintf('Ad request %d', $orderable->id),
                    'price' => (float) $this->formatAmount($order->amount),
                    'quantity' => 1,
                ]];
            }

            return [[
                'itemId' => sprintf('ADREQ%d', $orderable->id),
                'description' => sprintf('Ad request %d', $orderable->id),
                'price' => (float) $this->formatAmount($orderable->price_per_month),
                'quantity' => (int) $orderable->duration_months,
            ]];
        }

        if ($orderable instanceof CourseBooking) {
            $orderable->loadMissing('course');

            return [[
                'itemId' => sprintf('COURSEBOOK%d', $orderable->id),
                'description' => sprintf('Course booking %d', $orderable->id),
                'price' => (float) $this->formatAmount($orderable->total_amount),
                'quantity' => 1,
            ]];
        }

        if ($orderable instanceof MembershipRequest) {
            return [[
                'itemId' => sprintf('MEMREQ%d', $orderable->id),
                'description' => sprintf('Membership request %d', $orderable->id),
                'price' => (float) $this->formatAmount($orderable->total_amount),
                'quantity' => 1,
            ]];
        }

        if ($orderable instanceof CertificateRequest) {
            $orderable->loadMissing('certificate');
            $certificateName = trim((string) data_get($orderable->certificate, 'name'));

            return [[
                'itemId' => sprintf('CERTREQ%d', $orderable->id),
                'description' => $certificateName !== ''
                    ? sprintf('Certificate request %d - %s', $orderable->id, $certificateName)
                    : sprintf('Certificate request %d', $orderable->id),
                'price' => (float) $this->formatAmount($orderable->total_amount),
                'quantity' => 1,
            ]];
        }

        if ($orderable instanceof RestUnitBooking) {
            $orderable->loadMissing('restUnit');

            return $this->buildPricingChargeItems(
                $order,
                sprintf('RUB%d', $orderable->id),
                sprintf('Rest unit booking %d', $orderable->id),
            );
        }

        if ($orderable instanceof TravelBooking) {
            $orderable->loadMissing('travel');

            return $this->buildPricingChargeItems(
                $order,
                sprintf('TRV%d', $orderable->id),
                sprintf('Travel booking %d', $orderable->id),
            );
        }

        throw new RuntimeException('Fawry checkout is not supported for this order.');
    }

    private function buildChargeSignature(string $merchantRefNum, string $customerProfileId, string $returnUrl, array $chargeItems): string
    {
        $sortedItems = collect($chargeItems)
            ->sortBy(static fn (array $item) => (string) ($item['itemId'] ?? ''))
            ->values();

        $itemsSignature = $sortedItems->reduce(function (string $carry, array $item): string {
            return $carry
                . (string) ($item['itemId'] ?? '')
                . (string) ((int) ($item['quantity'] ?? 0))
                . $this->formatAmount($item['price'] ?? 0);
        }, '');

        return hash('sha256',
            $this->merchantCode()
            . $merchantRefNum
            . $customerProfileId
            . $returnUrl
            . $itemsSignature
            . $this->secureKey()
        );
    }

    private function extractPaymentUrl(array $responseData): ?string
    {
        return Arr::first([
            data_get($responseData, 'url'),
            data_get($responseData, 'paymentUrl'),
            data_get($responseData, 'paymentURL'),
            data_get($responseData, 'redirectUrl'),
            data_get($responseData, 'redirectURL'),
        ], static fn ($value) => filled($value));
    }

    private function extractPaymentUrlFromSuccessfulResponse(array $headers, string $body): ?string
    {
        $locationHeader = Arr::first($headers['Location'] ?? $headers['location'] ?? []);
        if (filled($locationHeader)) {
            return $locationHeader;
        }

        $trimmedBody = trim($body);
        if (filter_var($trimmedBody, FILTER_VALIDATE_URL)) {
            return $trimmedBody;
        }

        if (preg_match('/https?:\/\/[^\s"\']+/i', $body, $matches) === 1) {
            return $matches[0];
        }

        return null;
    }

    private function initUrl(): string
    {
        return rtrim((string) config('services.fawry.base_url'), '/') . self::INIT_PATH;
    }

    private function statusUrl(): string
    {
        return rtrim((string) config('services.fawry.base_url'), '/') . self::STATUS_PATH;
    }

    private function merchantCode(): string
    {
        return (string) config('services.fawry.merchant_code');
    }

    private function secureKey(): string
    {
        return (string) config('services.fawry.secure_key');
    }

    private function paymentExpiry(Order $order): int
    {
        $reservationExpiry = $order->created_at instanceof Carbon
            ? $order->created_at->copy()->addMinutes((int) config('checkout.reservation_timeout_minutes', 5))
            : null;

        $expiryMinutes = config('services.fawry.payment_expiry_minutes');
        if (is_numeric($expiryMinutes) && (int) $expiryMinutes > 0) {
            $gatewayExpiry = Carbon::now()->addMinutes((int) $expiryMinutes);
        } else {
            $gatewayExpiry = Carbon::now()->addHours((int) config('services.fawry.payment_expiry_hours', 24));
        }

        $expiryAt = $reservationExpiry
            ? $reservationExpiry->min($gatewayExpiry)
            : $gatewayExpiry;

        if ($expiryAt->lte(Carbon::now())) {
            $expiryAt = Carbon::now()->addSecond();
        }

        return $expiryAt->getTimestampMs();
    }

    private function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function optionalAmount(mixed $amount): string
    {
        return blank($amount) ? '' : $this->formatAmount($amount);
    }

    private function buildMerchantRefNum(Order $order): string
    {
        $order->loadMissing('orderable');
        $prefix = trim((string) config('services.fawry.merchant_ref_prefix', ''));
        $orderable = $order->orderable;
        $reference = match (true) {
            $orderable instanceof AdRequest => sprintf('AD%d', $orderable->id),
            $orderable instanceof CertificateRequest => sprintf('CERT%d', $orderable->id),
            $orderable instanceof CourseBooking => sprintf('CB%d', $orderable->id),
            $orderable instanceof MembershipRequest => sprintf('MID%d', $orderable->id),
            $orderable instanceof RestUnitBooking => sprintf('RUB%d', $orderable->id),
            $orderable instanceof TravelBooking => sprintf('TRV%d', $orderable->id),
            default => sprintf('ORD%d', $order->id),
        };
        $attemptSuffix = Carbon::now()->format('ymdHis') . Str::upper(Str::random(4));

        return preg_replace('/[^A-Za-z0-9]/', '', $prefix . $reference . $attemptSuffix);
    }

    private function buildPricingChargeItems(Order $order, string $itemPrefix, string $fallbackDescription): array
    {
        $items = collect(data_get($order->payload, 'pricing.items', []))
            ->filter(static fn (mixed $item): bool => is_array($item))
            ->map(function (array $item, int $index) use ($itemPrefix): array {
                $quantity = max((int) ($item['quantity'] ?? 1), 1);
                $amount = (float) ($item['amount'] ?? 0);
                $unitPrice = array_key_exists('unit_price', $item)
                    ? (float) $item['unit_price']
                    : ($quantity > 0 ? $amount / $quantity : $amount);
                $description = trim((string) ($item['description'] ?? $item['label'] ?? ''));
                $suffix = preg_replace('/[^A-Za-z0-9]/', '', strtoupper((string) ($item['code'] ?? '')));

                return [
                    'itemId' => $itemPrefix . ($suffix !== '' ? $suffix : (string) ($index + 1)),
                    'description' => $description !== '' ? $description : sprintf('Item %d', $index + 1),
                    'price' => (float) $this->formatAmount($unitPrice),
                    'quantity' => $quantity,
                ];
            })
            ->filter(static fn (array $item): bool => $item['price'] > 0 && $item['quantity'] > 0)
            ->values();

        $totalFromItems = $items->sum(static fn (array $item): float => ((float) $item['price']) * (int) $item['quantity']);

        if ($items->isEmpty() || abs($totalFromItems - (float) $order->amount) > 0.01) {
            return [[
                'itemId' => $itemPrefix,
                'description' => $fallbackDescription,
                'price' => (float) $this->formatAmount($order->amount),
                'quantity' => 1,
            ]];
        }

        return $items->all();
    }
}
