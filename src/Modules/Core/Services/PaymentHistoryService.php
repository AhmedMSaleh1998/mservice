<?php

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Ads\Models\AdRequest;
use Modules\Ads\Services\AdRequestService;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Core\Models\Order;
use Modules\Core\Models\PaymentMethod;
use Modules\Courses\Models\CourseBooking;
use Modules\Courses\Services\CourseBookingService;
use Modules\Memberships\Models\MembershipRequest;
use Modules\Memberships\Services\MembershipService;
use Modules\Services\Models\Service;
use Modules\Services\Models\RestUnitBooking;
use Modules\Services\Services\RestUnitService;
use Modules\Users\Models\User;

class PaymentHistoryService
{
    public function __construct(
        private readonly AdRequestService $adRequestService,
        private readonly CourseBookingService $courseBookingService,
        private readonly MembershipService $membershipService,
        private readonly RestUnitService $restUnitService,
    ) {
    }

    public function listForUser(User $user, array $filters, string $locale): LengthAwarePaginator
    {
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->where('status', 'paid_successfully')
            ->with('orderable')
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->get();

        $this->loadOrderableRelations($orders);
        $paymentMethodLabels = $this->paymentMethodLabels($locale);

        $items = $orders
            ->map(fn (Order $order): array => $this->buildListItem($order, $locale, $paymentMethodLabels))
            ->filter(fn (array $item): bool => $this->matchesFilters($item, $filters))
            ->values();

        $perPage = max(1, min(50, (int) ($filters['per_page'] ?? 15)));
        $currentPage = max(1, (int) ($filters['page'] ?? 1));
        $total = $items->count();
        $offset = ($currentPage - 1) * $perPage;

        return new LengthAwarePaginator(
            $items->slice($offset, $perPage)->values()->all(),
            $total,
            $perPage,
            $currentPage,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
    }

    public function detailForOrder(Order $order, string $locale): array
    {
        $this->loadOrderableRelations(new EloquentCollection([$order]));

        $paymentMethodLabels = $this->paymentMethodLabels($locale);
        $summary = $this->buildSummary($order->orderable);

        return [
            'id' => $order->id,
            'title' => $this->resolveTitle($order->orderable, $locale),
            'reference_number' => $this->resolveReferenceNumber($order),
            'paid_at' => optional($order->paid_at ?: $order->created_at)->format('Y-m-d H:i:s'),
            'amount' => $this->formatMoney($order->amount),
            'currency' => $order->currency,
            'status' => $order->status,
            'payment_method' => [
                'key' => $order->payment_method,
                'label' => $paymentMethodLabels[$order->payment_method] ?? $order->payment_method,
            ],
            'request' => [
                'id' => $order->orderable?->id,
                'type' => $this->resolveType($order->orderable),
            ],
            'items' => $this->normalizeItems(collect($summary['items'] ?? [])),
            'total' => $summary['total'] ?? $this->formatMoney($order->amount),
            'thumbnail_url' => $this->resolveThumbnailUrl($order->orderable),
        ];
    }

    private function buildListItem(Order $order, string $locale, array $paymentMethodLabels): array
    {
        return [
            'id' => $order->id,
            'title' => $this->resolveTitle($order->orderable, $locale),
            'reference_number' => $this->resolveReferenceNumber($order),
            'paid_at' => optional($order->paid_at ?: $order->created_at)->format('Y-m-d H:i:s'),
            'date' => optional($order->paid_at ?: $order->created_at)->toDateString(),
            'amount' => $this->formatMoney($order->amount),
            'currency' => $order->currency,
            'payment_method' => [
                'key' => $order->payment_method,
                'label' => $paymentMethodLabels[$order->payment_method] ?? $order->payment_method,
            ],
            'type' => $this->resolveType($order->orderable),
            'thumbnail_url' => $this->resolveThumbnailUrl($order->orderable),
        ];
    }

    private function loadOrderableRelations(EloquentCollection|Collection|array $orders): void
    {
        if (! $orders instanceof EloquentCollection) {
            $orders = new EloquentCollection($orders instanceof Collection ? $orders->all() : $orders);
        }

        $orders->loadMorph('orderable', [
            AdRequest::class => ['adSpace.service'],
            CourseBooking::class => ['course'],
            MembershipRequest::class => ['userAddress.province'],
            CertificateRequest::class => ['userAddress', 'certificate'],
            RestUnitBooking::class => ['restUnit.province', 'restUnit.media'],
        ]);
    }

    private function buildSummary(mixed $orderable): array
    {
        return match (true) {
            $orderable instanceof AdRequest => $this->adRequestService->buildSummary($orderable),
            $orderable instanceof CourseBooking => $this->courseBookingService->buildSummary($orderable),
            $orderable instanceof MembershipRequest => $this->membershipService->buildSummary($orderable),
            $orderable instanceof CertificateRequest => $this->buildCertificateSummary($orderable),
            $orderable instanceof RestUnitBooking => $this->restUnitService->buildSummary($orderable),
            default => [
                'items' => [],
                'total' => $this->formatMoney(data_get($orderable, 'total_amount', 0)),
            ],
        };
    }

    private function buildCertificateSummary(CertificateRequest $certificateRequest): array
    {
        $items = [];

        if ((float) $certificateRequest->printing_cost > 0) {
            $items[] = [
                'label' => __('Certificate printing'),
                'amount' => $this->formatMoney($certificateRequest->printing_cost),
            ];
        }

        if ((float) $certificateRequest->delivery_cost > 0) {
            $items[] = [
                'label' => __('Shipping fees'),
                'amount' => $this->formatMoney($certificateRequest->delivery_cost),
            ];
        }

        if ((float) $certificateRequest->subscription_cost > 0) {
            $items[] = [
                'label' => __('Subscription fees'),
                'amount' => $this->formatMoney($certificateRequest->subscription_cost),
            ];
        }

        return [
            'items' => $items,
            'total' => $this->formatMoney($certificateRequest->total_amount),
        ];
    }

    private function normalizeItems(Collection $items): array
    {
        return $items
            ->map(static fn (array $item): array => [
                'label' => $item['label'] ?? null,
                'description' => $item['description'] ?? null,
                'amount' => $item['amount'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function resolveTitle(mixed $orderable, string $locale): string
    {
        return match (true) {
            $orderable instanceof AdRequest => $this->resolveAdTitle($orderable, $locale),
            $orderable instanceof CourseBooking => $this->translatedValue($orderable->course, 'title', $locale, __('Course booking')),
            $orderable instanceof MembershipRequest => $this->resolveMembershipTitle($locale),
            $orderable instanceof CertificateRequest => $this->translatedValue($orderable->certificate, 'name', $locale, __('Certificate request')),
            $orderable instanceof RestUnitBooking => $this->translatedValue($orderable->restUnit, 'name', $locale, __('Rest Unit Booking')),
            default => __('Payment'),
        };
    }

    private function resolveAdTitle(AdRequest $adRequest, string $locale): string
    {
        $adText = trim((string) $adRequest->ad_text);

        if ($adText !== '') {
            return Str::limit($adText, 80);
        }

        return $this->translatedValue(data_get($adRequest, 'adSpace.service'), 'title', $locale, __('Ad payment'));
    }

    private function resolveMembershipTitle(string $locale): string
    {
        $service = Service::query()->where('key', 'membership-id')->first();

        return $this->translatedValue($service, 'title', $locale, __('Membership ID'));
    }

    private function resolveType(mixed $orderable): ?string
    {
        return match (true) {
            $orderable instanceof AdRequest => 'ad_request',
            $orderable instanceof CourseBooking => 'course_booking',
            $orderable instanceof MembershipRequest => 'membership_request',
            $orderable instanceof CertificateRequest => 'certificate_request',
            $orderable instanceof RestUnitBooking => 'rest_unit_booking',
            default => null,
        };
    }

    private function resolveReferenceNumber(Order $order): string
    {
        return (string) ($order->gateway_reference ?: $order->merchant_ref_num ?: $order->id);
    }

    private function resolveThumbnailUrl(mixed $orderable): ?string
    {
        if ($orderable instanceof AdRequest) {
            return $orderable->getFirstMedia('design_image')?->getUrl();
        }

        if ($orderable instanceof RestUnitBooking) {
            return $orderable->restUnit?->getFirstMedia('cover_image')?->getUrl();
        }

        return null;
    }

    private function paymentMethodLabels(string $locale): array
    {
        return PaymentMethod::query()
            ->get()
            ->mapWithKeys(fn (PaymentMethod $paymentMethod): array => [
                $paymentMethod->key => $this->translatedValue($paymentMethod, 'name', $locale, $paymentMethod->key),
            ])
            ->all();
    }

    private function translatedValue(mixed $resource, string $field, string $locale, string $fallback = ''): string
    {
        if (! $resource) {
            return $fallback;
        }

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

    private function matchesFilters(array $item, array $filters): bool
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $haystack = Str::lower(implode(' ', array_filter([
                $item['title'] ?? '',
                $item['reference_number'] ?? '',
                $item['payment_method']['label'] ?? '',
                $item['payment_method']['key'] ?? '',
                $item['type'] ?? '',
            ])));

            if (! str_contains($haystack, Str::lower($search))) {
                return false;
            }
        }

        $date = trim((string) ($filters['date'] ?? ''));
        if ($date !== '' && ($item['date'] ?? null) !== $date) {
            return false;
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && ($item['date'] ?? '') < $dateFrom) {
            return false;
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '' && ($item['date'] ?? '') > $dateTo) {
            return false;
        }

        return true;
    }

    private function formatMoney(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
