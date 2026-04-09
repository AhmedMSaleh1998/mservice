<?php

namespace Modules\Travels\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Services\OrderService;
use Modules\Core\Services\SubscriptionChargeService;
use Modules\Travels\Models\Travel;
use Modules\Travels\Models\TravelBooking;
use Modules\Travels\Models\TravelBookingItem;
use Modules\Travels\Models\TravelCategory;
use Modules\Users\Models\User;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class TravelService
{
    private readonly SubscriptionChargeService $subscriptionChargeService;

    public function __construct(
        private readonly OrderService $orderService,
        ?SubscriptionChargeService $subscriptionChargeService = null,
    ) {
        $this->subscriptionChargeService = $subscriptionChargeService ?? app(SubscriptionChargeService::class);
    }

    public function getList(int $limit = 100, array $filters = []): LengthAwarePaginator
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $page = max(1, (int) ($normalizedFilters['page'] ?? 1));

        $travels = Travel::query()
            ->with(['province', 'categories' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
            ->active()
            ->upcoming()
            ->when(
                $normalizedFilters['province_ids'] !== [],
                fn ($query) => $query->whereIn('province_id', $normalizedFilters['province_ids'])
            )
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Travel $travel): Travel => $this->decorateTravel($travel, false))
            ->filter(fn (Travel $travel): bool => $this->matchesSearch($travel, $normalizedFilters['search']))
            ->values();

        return new LengthAwarePaginator(
            $travels->forPage($page, $limit)->values()->all(),
            $travels->count(),
            $limit,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
    }

    public function getDetail(int $id): Travel
    {
        $travel = Travel::query()
            ->with(['province', 'categories' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
            ->active()
            ->findOrFail($id);

        return $this->decorateTravel($travel, true);
    }

    public function createBooking(Travel $travel, int $userId, array $data): TravelBooking
    {
        $user = User::query()->findOrFail($userId);
        $subscriptionCharge = $this->resolveSubscriptionCharge($user);

        return DB::transaction(function () use ($travel, $user, $data, $subscriptionCharge): TravelBooking {
            $lockedTravel = Travel::query()
                ->with(['province', 'categories' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
                ->lockForUpdate()
                ->findOrFail($travel->id);

            if (! $lockedTravel->is_active || $this->hasTravelStarted($lockedTravel)) {
                throw ValidationException::withMessages([
                    'travel_id' => __('This travel is not available for booking.'),
                ]);
            }

            $selectedItems = collect($data['items'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item))
                ->map(fn (array $item): array => [
                    'travel_category_id' => (int) ($item['travel_category_id'] ?? 0),
                    'quantity' => max((int) ($item['quantity'] ?? 0), 0),
                ])
                ->filter(fn (array $item): bool => $item['travel_category_id'] > 0 && $item['quantity'] > 0)
                ->values();

            if ($selectedItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => __('Select at least one travel category.'),
                ]);
            }

            $categoryIds = $selectedItems->pluck('travel_category_id')->unique()->values()->all();
            $categories = TravelCategory::query()
                ->where('travel_id', $lockedTravel->id)
                ->where('is_active', true)
                ->whereIn('id', $categoryIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($categories->count() !== count($categoryIds)) {
                throw ValidationException::withMessages([
                    'items' => __('One or more selected travel categories are invalid.'),
                ]);
            }

            $reservedQuantities = $this->reservedQuantitiesForTravel($lockedTravel, $categoryIds);
            $bookingLines = [];
            $subtotal = 0.0;
            $participantsCount = 0;

            foreach ($selectedItems as $index => $selectedItem) {
                /** @var TravelCategory $category */
                $category = $categories->get($selectedItem['travel_category_id']);
                $availableCount = max(
                    (int) $category->capacity - (int) ($reservedQuantities[$category->id] ?? 0),
                    0
                );

                if ($selectedItem['quantity'] > $availableCount) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => __('Selected quantity exceeds available seats for :category.', [
                            'category' => $this->translatedValue($category, 'name', app()->getLocale(), (string) $category->code),
                        ]),
                    ]);
                }

                $unitPrice = (float) $category->price;
                $lineAmount = $unitPrice * $selectedItem['quantity'];
                $participantsCount += $selectedItem['quantity'];
                $subtotal += $lineAmount;

                $bookingLines[] = [
                    'category' => $category,
                    'category_code' => (string) $category->code,
                    'category_name' => $this->translatedValue($category, 'name', app()->getLocale(), (string) $category->code),
                    'quantity' => $selectedItem['quantity'],
                    'unit_price' => $unitPrice,
                    'amount' => $lineAmount,
                ];
            }

            $subscriptionAmount = (float) ($subscriptionCharge['amount'] ?? 0);
            $totalAmount = $subtotal + $subscriptionAmount;
            $paidAt = $totalAmount <= 0 ? now() : null;
            $bookingStatus = $totalAmount <= 0
                ? TravelBooking::STATUS_PAID_SUCCESSFULLY
                : TravelBooking::STATUS_PENDING_PAYMENT;

            $booking = TravelBooking::query()->create([
                'travel_id' => $lockedTravel->id,
                'user_id' => $user->id,
                'status' => $bookingStatus,
                'total_amount' => $totalAmount,
                'participants_count' => $participantsCount,
                'paid_at' => $paidAt,
            ]);

            $booking->items()->createMany(collect($bookingLines)->map(static fn (array $line): array => [
                'travel_category_id' => $line['category']->id,
                'category_code' => $line['category_code'],
                'category_name' => $line['category_name'],
                'unit_price' => $line['unit_price'],
                'quantity' => $line['quantity'],
                'total_price' => $line['amount'],
            ])->all());

            $pricing = $this->buildPricingSummary($lockedTravel, $bookingLines, $subscriptionCharge);

            $this->orderService->sync($booking, [
                'user_id' => $user->id,
                'amount' => $totalAmount,
                'status' => $bookingStatus,
                'payment_method' => $totalAmount <= 0 ? 'free' : null,
                'provider' => $totalAmount <= 0 ? 'system' : null,
                'gateway_status' => $totalAmount <= 0 ? 'PAID' : null,
                'paid_at' => $paidAt,
                'payment_last_synced_at' => $paidAt,
                'payload' => $this->orderService->withPricingPayload(
                    $this->orderService->withSubscriptionChargePayload(null, $subscriptionCharge),
                    $pricing,
                ),
            ]);

            return $booking->fresh(['travel.province', 'travel.categories', 'items.category', 'order']);
        });
    }

    public function reservationTimeoutMinutes(): int
    {
        $minutes = (int) config('checkout.reservation_timeout_minutes', 5);

        return $minutes > 0 ? $minutes : 5;
    }

    public function reservationExpiresAt(TravelBooking $booking): Carbon
    {
        $createdAt = $booking->created_at instanceof Carbon
            ? $booking->created_at->copy()
            : Carbon::now();

        return $createdAt->addMinutes($this->reservationTimeoutMinutes());
    }

    public function isReservationExpired(TravelBooking $booking): bool
    {
        return $this->reservationExpiresAt($booking)->lte(Carbon::now());
    }

    public function expireReservation(TravelBooking $booking): bool
    {
        return DB::transaction(function () use ($booking): bool {
            $lockedBooking = TravelBooking::query()
                ->with('order')
                ->lockForUpdate()
                ->find($booking->id);

            if (! $lockedBooking || $lockedBooking->status !== TravelBooking::STATUS_PENDING_PAYMENT) {
                return false;
            }

            if (! $this->isReservationExpired($lockedBooking)) {
                return false;
            }

            $order = $lockedBooking->order()->lockForUpdate()->first();
            if ($order && $order->status === 'paid_successfully') {
                return false;
            }

            $lockedBooking->status = TravelBooking::STATUS_PAYMENT_EXPIRED;
            $lockedBooking->save();

            if ($order) {
                $order->forceFill([
                    'status' => TravelBooking::STATUS_PAYMENT_EXPIRED,
                    'gateway_status' => $order->status === 'paid_successfully' ? $order->gateway_status : 'EXPIRED',
                    'checkout_url' => null,
                    'payment_last_synced_at' => now(),
                ])->save();
            }

            return true;
        });
    }

    public function buildSummary(TravelBooking $booking): array
    {
        $booking->loadMissing('travel', 'items');
        $order = $booking->relationLoaded('order')
            ? $booking->getRelation('order')
            : null;

        if ($order && ($summary = $this->orderService->pricingSummary($order))) {
            return $summary;
        }

        return [
            'title' => __('Payment Summary'),
            'currency' => (string) config('checkout.currency', 'EGP'),
            'items' => $booking->items
                ->map(fn (TravelBookingItem $item): array => [
                    'code' => 'travel_category_' . strtolower((string) $item->category_code),
                    'label' => $item->category_name,
                    'unit_price' => $this->formatMoney($item->unit_price),
                    'quantity' => (int) $item->quantity,
                    'amount' => $this->formatMoney($item->total_price),
                ])
                ->values()
                ->all(),
            'subtotal' => $this->formatMoney($booking->total_amount),
            'discount' => $this->formatMoney(0),
            'fees' => $this->formatMoney(0),
            'total' => $this->formatMoney($booking->total_amount),
        ];
    }

    private function normalizeFilters(array $filters): array
    {
        $provinceIds = collect($filters['province_ids'] ?? [])
            ->when(
                isset($filters['province_id']) && $filters['province_id'] !== null,
                fn (Collection $collection) => $collection->push($filters['province_id'])
            )
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values()
            ->all();

        return [
            'search' => trim((string) ($filters['search'] ?? '')),
            'province_ids' => $provinceIds,
            'page' => max(1, (int) ($filters['page'] ?? 1)),
        ];
    }

    private function decorateTravel(Travel $travel, bool $includeDetail): Travel
    {
        $travel->loadMissing(['province', 'categories' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id')]);

        $categories = $travel->categories
            ->where('is_active', true)
            ->sortBy(fn (TravelCategory $category): array => [
                (int) $category->sort_order,
                (int) $category->id,
            ])
            ->values();

        $reservedQuantities = $this->reservedQuantitiesForTravel($travel, $categories->pluck('id')->all());
        $categoryOptions = $categories
            ->map(fn (TravelCategory $category): array => $this->buildCategoryPayload(
                $category,
                (int) ($reservedQuantities[$category->id] ?? 0),
                $includeDetail
            ))
            ->values();

        $travel->setAttribute('image_url', $this->imageUrl($travel));
        $travel->setAttribute('gallery_urls', $travel->getMedia('gallery')->map(fn ($media) => $media->getFullUrl())->values()->all());
        $travel->setAttribute('itinerary_file_url', $travel->getFirstMedia('itinerary_file')?->getFullUrl());
        $travel->setAttribute('itinerary_file_name', $travel->getFirstMedia('itinerary_file')?->file_name);
        $travel->setAttribute('available_slots', $categoryOptions->sum('remaining_count'));
        $travel->setAttribute('starting_price', $this->resolveStartingPrice($categories));
        $travel->setAttribute('currency', (string) config('checkout.currency', 'EGP'));
        $travel->setAttribute('category_options', $categoryOptions->all());
        $travel->setAttribute('booking_open', $travel->is_active && ! $this->hasTravelStarted($travel) && $categoryOptions->sum('remaining_count') > 0);

        return $travel;
    }

    private function buildCategoryPayload(TravelCategory $category, int $reservedQuantity, bool $includeDetail): array
    {
        $availableCount = max((int) $category->capacity - $reservedQuantity, 0);
        $payload = [
            'id' => $category->id,
            'name' => $category->name,
            'price' => $this->formatMoney($category->price),
            'remaining_count' => $availableCount,
            'is_available' => $availableCount > 0,
        ];

        if ($includeDetail) {
            $payload['capacity'] = (int) $category->capacity;
            $payload['features'] = collect($category->features ?? [])
                ->filter(fn (mixed $feature): bool => filled($feature))
                ->values()
                ->all();
        }

        return $payload;
    }

    private function matchesSearch(Travel $travel, string $search): bool
    {
        if ($search === '') {
            return true;
        }

        $haystack = collect([
            $this->translatedValue($travel, 'title', app()->getLocale()),
            $this->translatedValue($travel, 'location', app()->getLocale()),
        ])->implode(' ');

        return str_contains(mb_strtolower($haystack), mb_strtolower($search));
    }

    private function reservedQuantitiesForTravel(Travel $travel, array $categoryIds = []): Collection
    {
        return TravelBookingItem::query()
            ->join('travel_bookings', 'travel_booking_items.travel_booking_id', '=', 'travel_bookings.id')
            ->where('travel_bookings.travel_id', $travel->id)
            ->whereNotIn('travel_bookings.status', [
                TravelBooking::STATUS_CANCELLED,
                TravelBooking::STATUS_PAYMENT_EXPIRED,
            ])
            ->where(function ($query): void {
                $query
                    ->where('travel_bookings.status', '!=', TravelBooking::STATUS_PENDING_PAYMENT)
                    ->orWhere('travel_bookings.created_at', '>', now()->subMinutes($this->reservationTimeoutMinutes()));
            })
            ->when(
                $categoryIds !== [],
                fn ($query) => $query->whereIn('travel_booking_items.travel_category_id', $categoryIds)
            )
            ->selectRaw('travel_booking_items.travel_category_id, COALESCE(SUM(travel_booking_items.quantity), 0) as reserved_quantity')
            ->groupBy('travel_booking_items.travel_category_id')
            ->get()
            ->pluck('reserved_quantity', 'travel_category_id');
    }

    private function buildPricingSummary(Travel $travel, array $bookingLines, array $subscriptionCharge): array
    {
        $travelTitle = $this->translatedValue($travel, 'title', app()->getLocale(), __('Travel booking'));
        $subscriptionAmount = (float) ($subscriptionCharge['amount'] ?? 0);
        $subtotal = collect($bookingLines)->sum('amount');
        $totalAmount = $subtotal + $subscriptionAmount;
        $items = collect($bookingLines)
            ->map(fn (array $line): array => [
                'code' => 'travel_category_' . strtolower((string) $line['category_code']),
                'label' => $line['category_name'],
                'description' => $travelTitle,
                'unit_price' => $this->formatMoney($line['unit_price']),
                'quantity' => $line['quantity'],
                'amount' => $this->formatMoney($line['amount']),
                'meta' => [
                    'category_id' => $line['category']->id,
                    'category_code' => $line['category_code'],
                ],
            ])
            ->values()
            ->all();

        if ($subscriptionAmount > 0) {
            $items[] = [
                'code' => 'subscription_fees',
                'unit_price' => $this->formatMoney($subscriptionAmount),
                'quantity' => 1,
                'amount' => $this->formatMoney($subscriptionAmount),
                'meta' => [
                    'subscription_years' => max((int) ($subscriptionCharge['years'] ?? 0), 0),
                ],
            ];
        }

        return [
            'currency' => (string) config('checkout.currency', 'EGP'),
            'items' => $items,
            'subtotal' => $this->formatMoney($totalAmount),
            'discount' => $this->formatMoney(0),
            'fees' => $this->formatMoney(0),
            'total' => $this->formatMoney($totalAmount),
        ];
    }

    private function resolveStartingPrice(Collection $categories): ?string
    {
        $price = $categories
            ->filter(fn (TravelCategory $category): bool => $category->is_active)
            ->min(fn (TravelCategory $category): float => (float) $category->price);

        return $price === null ? null : $this->formatMoney($price);
    }

    private function hasTravelStarted(Travel $travel): bool
    {
        return optional($travel->start_date)->lt(Carbon::today()) ?? false;
    }

    private function imageUrl(Travel $travel): ?string
    {
        return $travel->getFirstMedia('image')?->getFullUrl()
            ?: $travel->getFirstMedia('cover_image')?->getFullUrl();
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

    private function formatMoney(float|int|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function resolveSubscriptionCharge(User $user): array
    {
        try {
            return $this->subscriptionChargeService->resolveForUser($user);
        } catch (RuntimeException $exception) {
            throw new ServiceUnavailableHttpException(
                null,
                __('Unable to verify subscription fees with Oracle at the moment. Please try again later.'),
                $exception,
            );
        }
    }
}
