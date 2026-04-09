<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Modules\Ads\Models\AdRequest;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Core\Models\Order;
use Modules\Core\Models\PaymentMethod;
use Modules\Core\Services\OrderService;
use Modules\Courses\Models\CourseBooking;
use Modules\Memberships\Models\MembershipRequest;
use Modules\Services\Models\RestUnit;
use Modules\Services\Models\RestUnitBooking;
use Modules\Services\Models\Service;
use Modules\Travels\Models\TravelBooking;
use Modules\Users\Models\UserAddress;

class OrderAdminSupport
{
    private static array $paymentMethodLabels = [];

    public static function applyEagerLoading(Builder $query): Builder
    {
        return $query->with([
            'user',
            'orderable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                AdRequest::class => ['adSpace.service'],
                CourseBooking::class => ['course'],
                MembershipRequest::class => ['userAddress.province'],
                CertificateRequest::class => ['certificate', 'userAddress.province'],
                RestUnitBooking::class => ['restUnit.province'],
                TravelBooking::class => ['travel.province'],
            ]),
        ]);
    }

    public static function applyDeliveryScope(Builder $query): Builder
    {
        return $query->whereHasMorph(
            'orderable',
            [MembershipRequest::class, CertificateRequest::class],
            function (Builder $orderableQuery, string $type): void {
                if ($type === CertificateRequest::class) {
                    $orderableQuery->where('delivery_method', 'delivery');
                }
            }
        );
    }

    public static function loadRelations(Order|EloquentCollection|array $orders): void
    {
        if ($orders instanceof Order) {
            $orders = new EloquentCollection([$orders]);
        } elseif (! $orders instanceof EloquentCollection) {
            $orders = new EloquentCollection($orders);
        }

        $orders->load('user', 'orderable');
        $orders->loadMorph('orderable', [
            AdRequest::class => ['adSpace.service'],
            CourseBooking::class => ['course'],
            MembershipRequest::class => ['userAddress.province'],
            CertificateRequest::class => ['certificate', 'userAddress.province'],
            RestUnitBooking::class => ['restUnit.province'],
            TravelBooking::class => ['travel.province'],
        ]);
    }

    public static function typeOptions(): array
    {
        return [
            AdRequest::class => __('Ad Request'),
            CourseBooking::class => __('Course Booking'),
            MembershipRequest::class => __('Membership Request'),
            CertificateRequest::class => __('Certificate Request'),
            RestUnitBooking::class => __('Rest Unit Booking'),
            TravelBooking::class => __('Travel booking'),
        ];
    }

    public static function deliveryTypeOptions(): array
    {
        return [
            MembershipRequest::class => __('Membership Request'),
            CertificateRequest::class => __('Certificate Request'),
        ];
    }

    public static function orderStatusOptions(): array
    {
        return [
            'pending_payment' => __('Pending Payment'),
            'checkout_pending' => __('Checkout Pending'),
            'paid_successfully' => __('Paid Successfully'),
            'payment_expired' => __('Payment Expired'),
            'processing' => __('Processing'),
            'completed' => __('Completed'),
            'approved' => __('Approved'),
            'cancelled' => __('Cancelled'),
            'rejected' => __('Rejected'),
        ];
    }

    public static function deliveryStatusOptions(): array
    {
        return MembershipRequest::deliveryStatusOptions();
    }

    public static function paymentMethodOptions(): array
    {
        return PaymentMethod::query()
            ->get()
            ->mapWithKeys(fn (PaymentMethod $method): array => [
                $method->key => static::paymentMethodLabel($method->key) ?? $method->key,
            ])
            ->all();
    }

    public static function typeLabel(Order $order): string
    {
        return static::typeOptions()[$order->orderable_type] ?? __('Payment');
    }

    public static function serviceTitle(Order $order): string
    {
        $orderable = $order->orderable;
        $locale = app()->getLocale();

        return match (true) {
            $orderable instanceof AdRequest => static::translatedValue(data_get($orderable, 'adSpace.service'), 'title', __('Ad payment')),
            $orderable instanceof CourseBooking => static::translatedValue($orderable->course, 'title', __('Course booking')),
            $orderable instanceof MembershipRequest => static::membershipTitle($locale),
            $orderable instanceof CertificateRequest => static::translatedValue($orderable->certificate, 'name', __('Certificate request')),
            $orderable instanceof RestUnitBooking => static::translatedValue($orderable->restUnit, 'name', __('Rest Unit Booking')),
            $orderable instanceof TravelBooking => static::translatedValue($orderable->travel, 'title', __('Travel booking')),
            default => __('Payment'),
        };
    }

    public static function serviceSummary(Order $order): string
    {
        $orderable = $order->orderable;

        return match (true) {
            $orderable instanceof AdRequest => static::joinSummary([
                data_get($orderable, 'duration_months')
                    ? __(':months months', ['months' => $orderable->duration_months])
                    : null,
                static::money($orderable?->total_amount ?? $order->amount, $order->currency),
            ]),
            $orderable instanceof CourseBooking => static::joinSummary([
                static::translatedValue($orderable->course, 'title'),
                static::money($orderable->total_amount ?? $order->amount, $order->currency),
            ]),
            $orderable instanceof MembershipRequest => static::joinSummary([
                $orderable->full_name,
                $orderable->registration_number,
            ]),
            $orderable instanceof CertificateRequest => static::joinSummary([
                static::deliveryMethodLabel($orderable->delivery_method),
                static::addressLine($orderable->userAddress),
            ]),
            $orderable instanceof RestUnitBooking => static::joinSummary([
                static::roomTypeLabel($orderable->unit_type),
                static::dateRange(
                    optional($orderable->start_date)->toDateString(),
                    optional($orderable->end_date)->toDateString(),
                ),
            ]),
            $orderable instanceof TravelBooking => static::joinSummary([
                $orderable->participants_count
                    ? __(':count travelers', ['count' => $orderable->participants_count])
                    : null,
                static::dateRange(
                    optional($orderable->travel?->start_date)->toDateString(),
                    optional($orderable->travel?->end_date)->toDateString(),
                ),
            ]),
            default => '',
        };
    }

    public static function serviceReference(Order $order): ?string
    {
        return filled($order->orderable?->id)
            ? sprintf('#%s', $order->orderable->id)
            : null;
    }

    public static function serviceStatus(Order $order): ?string
    {
        return $order->orderable?->status;
    }

    public static function serviceStatusLabel(Order $order): string
    {
        return static::statusLabel(static::serviceStatus($order));
    }

    public static function serviceDetailItems(Order $order): array
    {
        $orderable = $order->orderable;

        $items = match (true) {
            $orderable instanceof AdRequest => [
                static::detail(__('Service'), static::translatedValue(data_get($orderable, 'adSpace.service'), 'title')),
                static::detail(__('Duration'), data_get($orderable, 'duration_months')
                    ? __(':months months', ['months' => $orderable->duration_months])
                    : null),
                static::detail(__('Price Per Month'), static::money($orderable->price_per_month, $order->currency)),
                static::detail(__('Ad Text'), $orderable->ad_text),
            ],
            $orderable instanceof CourseBooking => [
                static::detail(__('Course'), static::translatedValue($orderable->course, 'title')),
                static::detail(__('Booked Price'), static::money($orderable->price, $order->currency)),
                static::detail(__('Total Amount'), static::money($orderable->total_amount, $order->currency)),
            ],
            $orderable instanceof MembershipRequest => [
                static::detail(__('Full Name'), $orderable->full_name),
                static::detail(__('Registration Number'), $orderable->registration_number),
                static::detail(__('Specialty'), $orderable->specialty),
                static::detail(__('Degree'), $orderable->degree),
                static::detail(__('Address'), static::addressLine($orderable->userAddress)),
            ],
            $orderable instanceof CertificateRequest => [
                static::detail(__('Certificate'), static::translatedValue($orderable->certificate, 'name')),
                static::detail(__('Delivery Method'), static::deliveryMethodLabel($orderable->delivery_method)),
                static::detail(__('Phone'), $orderable->phone),
                static::detail(__('Email'), $orderable->email),
                static::detail(__('Address'), static::addressLine($orderable->userAddress)),
            ],
            $orderable instanceof RestUnitBooking => [
                static::detail(__('Rest Unit'), static::translatedValue($orderable->restUnit, 'name')),
                static::detail(__('Type'), static::roomTypeLabel($orderable->unit_type)),
                static::detail(__('Stay'), static::dateRange(
                    optional($orderable->start_date)->toDateString(),
                    optional($orderable->end_date)->toDateString(),
                )),
                static::detail(__('Total Amount'), static::money($orderable->total_price, $order->currency)),
            ],
            $orderable instanceof TravelBooking => [
                static::detail(__('Travel'), static::translatedValue($orderable->travel, 'title')),
                static::detail(__('Location'), static::translatedValue($orderable->travel, 'location')),
                static::detail(__('Trip Dates'), static::dateRange(
                    optional($orderable->travel?->start_date)->toDateString(),
                    optional($orderable->travel?->end_date)->toDateString(),
                )),
                static::detail(__('Travelers'), $orderable->participants_count ? (string) $orderable->participants_count : null),
                static::detail(__('Total Amount'), static::money($orderable->total_amount, $order->currency)),
            ],
            default => [],
        };

        return array_values(array_filter($items));
    }

    public static function hasDeliveryDetails(Order $order): bool
    {
        return $order->orderable instanceof MembershipRequest
            || $order->orderable instanceof CertificateRequest;
    }

    public static function hasPhysicalDelivery(Order $order): bool
    {
        $orderable = $order->orderable;

        if ($orderable instanceof MembershipRequest) {
            return true;
        }

        if ($orderable instanceof CertificateRequest) {
            return $orderable->delivery_method === 'delivery';
        }

        return false;
    }

    public static function deliveryMethod(Order $order): ?string
    {
        $orderable = $order->orderable;

        if ($orderable instanceof MembershipRequest) {
            return $orderable->delivery_method ?: 'delivery';
        }

        if ($orderable instanceof CertificateRequest) {
            return $orderable->delivery_method;
        }

        return null;
    }

    public static function deliveryMethodLabel(?string $method): string
    {
        return match ($method) {
            'delivery' => __('Delivery'),
            'digital' => __('Digital'),
            'pickup' => __('Pickup'),
            null, '' => '-',
            default => (string) $method,
        };
    }

    public static function deliveryStatus(Order $order): ?string
    {
        $orderable = $order->orderable;

        return match (true) {
            $orderable instanceof MembershipRequest => $orderable->delivery_status,
            $orderable instanceof CertificateRequest => $orderable->delivery_status,
            default => null,
        };
    }

    public static function deliveryStatusLabel(?string $status): string
    {
        return static::deliveryStatusOptions()[$status ?? ''] ?? ($status ?: '-');
    }

    public static function deliveryStatusColor(?string $status): string
    {
        return match ($status) {
            MembershipRequest::DELIVERY_STATUS_PENDING => 'warning',
            MembershipRequest::DELIVERY_STATUS_PREPARING => 'info',
            MembershipRequest::DELIVERY_STATUS_SHIPPED => 'primary',
            MembershipRequest::DELIVERY_STATUS_DELIVERED => 'success',
            MembershipRequest::DELIVERY_STATUS_FAILED,
            MembershipRequest::DELIVERY_STATUS_RETURNED => 'danger',
            default => 'gray',
        };
    }

    public static function deliveryDetailItems(Order $order): array
    {
        $orderable = $order->orderable;

        $items = match (true) {
            $orderable instanceof MembershipRequest => [
                static::detail(__('Delivery Method'), static::deliveryMethodLabel(static::deliveryMethod($order))),
                static::detail(__('Delivery Status'), static::deliveryStatusLabel(static::deliveryStatus($order))),
                static::detail(__('Address'), static::addressLine($orderable->userAddress)),
                static::detail(__('Contact Phone'), $orderable->userAddress?->phone),
            ],
            $orderable instanceof CertificateRequest => [
                static::detail(__('Delivery Method'), static::deliveryMethodLabel(static::deliveryMethod($order))),
                static::detail(__('Delivery Status'), static::deliveryStatusLabel(static::deliveryStatus($order))),
                static::detail(__('Address'), static::addressLine($orderable->userAddress)),
                static::detail(__('Contact Phone'), $orderable->phone ?: $orderable->userAddress?->phone),
                static::detail(__('Contact Email'), $orderable->email),
            ],
            default => [],
        };

        return array_values(array_filter($items));
    }

    public static function paymentMethodLabel(?string $key): ?string
    {
        if (! $key) {
            return null;
        }

        $locale = app()->getLocale();
        $cacheKey = sprintf('%s:%s', $locale, $key);

        if (array_key_exists($cacheKey, static::$paymentMethodLabels)) {
            return static::$paymentMethodLabels[$cacheKey];
        }

        $method = PaymentMethod::query()
            ->where('key', $key)
            ->first();

        static::$paymentMethodLabels[$cacheKey] = $method
            ? static::translatedValue($method, 'name', $key)
            : $key;

        return static::$paymentMethodLabels[$cacheKey];
    }

    public static function statusLabel(?string $status): string
    {
        return static::orderStatusOptions()[$status ?? '']
            ?? ($status ? Str::of($status)->replace('_', ' ')->title()->toString() : '-');
    }

    public static function orderStatusColor(?string $status): string
    {
        return match ($status) {
            'pending_payment' => 'warning',
            'checkout_pending' => 'info',
            'paid_successfully', 'completed', 'approved' => 'success',
            'processing' => 'primary',
            'payment_expired' => 'gray',
            'cancelled', 'rejected' => 'danger',
            default => 'gray',
        };
    }

    public static function pricingItems(Order $order): array
    {
        $summary = app(OrderService::class)->pricingSummary($order);

        return collect($summary['items'] ?? [])
            ->filter(static fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'label' => (string) ($item['label'] ?? $item['code'] ?? '-'),
                'description' => filled($item['description'] ?? null) ? (string) $item['description'] : null,
                'unit_price' => array_key_exists('unit_price', $item)
                    ? (float) $item['unit_price']
                    : null,
                'quantity' => max((int) ($item['quantity'] ?? 1), 1),
                'amount' => (float) ($item['amount'] ?? 0),
            ])
            ->values()
            ->all();
    }

    public static function pricingSummary(Order $order): ?array
    {
        return app(OrderService::class)->pricingSummary($order);
    }

    public static function payload(Order $order): array
    {
        return is_array($order->payload) ? $order->payload : [];
    }

    public static function prettyJson(mixed $value): ?HtmlString
    {
        if (blank($value)) {
            return null;
        }

        $json = json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            return null;
        }

        return new HtmlString(sprintf('<pre class="text-xs">%s</pre>', e($json)));
    }

    public static function money(mixed $amount, string $currency = 'EGP'): string
    {
        return sprintf('%s %s', number_format((float) $amount, 2), $currency);
    }

    private static function membershipTitle(string $locale): string
    {
        $service = Service::query()
            ->where('key', 'membership-id')
            ->first();

        return static::translatedValue($service, 'title', __('Membership ID'));
    }

    private static function translatedValue(mixed $resource, string $field, string $fallback = ''): string
    {
        if (! $resource) {
            return $fallback;
        }

        $locale = app()->getLocale();

        if (is_object($resource) && method_exists($resource, 'getTranslation')) {
            $translated = (string) $resource->getTranslation($field, $locale, false);

            if ($translated !== '') {
                return $translated;
            }
        }

        $value = data_get($resource, $field);

        if (is_array($value)) {
            $localized = $value[$locale] ?? null;

            if (filled($localized)) {
                return (string) $localized;
            }

            foreach ($value as $candidate) {
                if (filled($candidate)) {
                    return (string) $candidate;
                }
            }
        }

        return filled($value) ? (string) $value : $fallback;
    }

    private static function roomTypeLabel(?string $type): string
    {
        return match ($type) {
            RestUnit::TYPE_SINGLE_ROOM, 'single_rooms' => __('Single room'),
            RestUnit::TYPE_DOUBLE_ROOM, 'double_rooms' => __('Double room'),
            RestUnit::TYPE_TRIPLE_ROOM, 'single_bed' => __('Triple room'),
            null, '' => '-',
            default => (string) $type,
        };
    }

    private static function dateRange(?string $startDate, ?string $endDate): ?string
    {
        if (! $startDate && ! $endDate) {
            return null;
        }

        if ($startDate && $endDate) {
            return sprintf('%s - %s', $startDate, $endDate);
        }

        return $startDate ?: $endDate;
    }

    private static function addressLine(?UserAddress $address): ?string
    {
        if (! $address) {
            return null;
        }

        $parts = array_filter([
            $address->address_name,
            $address->district,
            $address->street,
            filled($address->unit_number) ? __('Unit :unit', ['unit' => $address->unit_number]) : null,
            static::translatedValue($address->province, 'name'),
        ]);

        return empty($parts) ? null : implode(', ', $parts);
    }

    private static function joinSummary(array $parts): string
    {
        $parts = array_values(array_filter($parts, static fn (mixed $value): bool => filled($value)));

        return implode(' • ', $parts);
    }

    private static function detail(string $label, mixed $value): ?array
    {
        if (! filled($value)) {
            return null;
        }

        return [
            'label' => $label,
            'value' => (string) $value,
        ];
    }
}
