<?php

namespace Modules\Services\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Services\OrderService;
use Modules\Core\Services\SubscriptionChargeService;
use Modules\Services\Models\RestUnit;
use Modules\Services\Models\RestUnitBooking;
use Modules\Users\Models\User;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class RestUnitService
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

        $units = RestUnit::query()
            ->with('province')
            ->where('is_active', true)
            ->when(
                $normalizedFilters['province_ids'] !== [],
                fn ($query) => $query->whereIn('province_id', $normalizedFilters['province_ids'])
            )
            ->orderBy('id')
            ->get();

        $preparedUnits = $this->prepareUnits($units, $normalizedFilters, false)
            ->filter(fn (RestUnit $unit): bool => $this->matchesListFilters($unit, $normalizedFilters))
            ->values();

        return new LengthAwarePaginator(
            $preparedUnits->forPage($page, $limit)->values()->all(),
            $preparedUnits->count(),
            $limit,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
    }

    public function getDetail(int $id, array $filters = []): RestUnit
    {
        $normalizedFilters = $this->normalizeFilters($filters);

        $unit = RestUnit::query()
            ->with('province', 'media')
            ->where('is_active', true)
            ->findOrFail($id);

        return $this->decorateUnit(
            $unit,
            $normalizedFilters,
            $this->overlappingBookingsForUnit($unit, $normalizedFilters['from_date'], $normalizedFilters['to_date']),
            true,
        );
    }

    public function createBooking(array $data): RestUnitBooking
    {
        $normalizedUnitType = RestUnit::normalizeUnitType($data['unit_type'] ?? null);
        if (! $normalizedUnitType) {
            throw ValidationException::withMessages([
                'unit_type' => __('The selected room type is invalid.'),
            ]);
        }

        $user = User::query()->findOrFail((int) $data['user_id']);
        $subscriptionCharge = $this->resolveSubscriptionCharge($user);

        return DB::transaction(function () use ($data, $user, $subscriptionCharge, $normalizedUnitType): RestUnitBooking {
            $unit = RestUnit::query()
                ->with('province', 'media')
                ->lockForUpdate()
                ->findOrFail((int) $data['rest_unit_id']);

            if (! $unit->is_active) {
                throw ValidationException::withMessages([
                    'rest_unit_id' => __('This rest unit is not available for booking.'),
                ]);
            }

            $startDate = Carbon::parse((string) $data['start_date'])->startOfDay();
            $endDate = Carbon::parse((string) $data['end_date'])->startOfDay();
            $nights = max($startDate->diffInDays($endDate), 1);

            if (! $this->roomTypeAvailable($unit, $normalizedUnitType, $startDate->toDateString(), $endDate->toDateString())) {
                throw ValidationException::withMessages([
                    'unit_type' => __('This room type is not available for the selected dates.'),
                ]);
            }

            $priceColumn = RestUnit::priceColumnForType($normalizedUnitType);
            $pricePerNight = $priceColumn ? (float) data_get($unit, $priceColumn, 0) : 0;
            $stayAmount = $pricePerNight * $nights;
            $subscriptionAmount = (float) ($subscriptionCharge['amount'] ?? 0);
            $totalAmount = $stayAmount + $subscriptionAmount;
            $paidAt = $totalAmount <= 0 ? now() : null;
            $bookingStatus = $totalAmount <= 0
                ? RestUnitBooking::STATUS_PAID_SUCCESSFULLY
                : RestUnitBooking::STATUS_PENDING_PAYMENT;
            $pricing = $this->buildPricingSummary(
                $unit,
                $normalizedUnitType,
                $startDate,
                $endDate,
                $nights,
                $pricePerNight,
                $stayAmount,
                $subscriptionCharge,
            );

            $booking = $unit->bookings()->create([
                'user_id' => $user->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'unit_type' => $normalizedUnitType,
                'total_price' => $totalAmount,
                'status' => $bookingStatus,
                'paid_at' => $paidAt,
            ]);

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

            return $booking->fresh(['restUnit.province', 'restUnit.media', 'order']);
        });
    }

    public function book(array $data): RestUnitBooking
    {
        return $this->createBooking($data);
    }

    public function reservationTimeoutMinutes(): int
    {
        $minutes = (int) config('checkout.reservation_timeout_minutes', 5);

        return $minutes > 0 ? $minutes : 5;
    }

    public function reservationExpiresAt(RestUnitBooking $booking): Carbon
    {
        $createdAt = $booking->created_at instanceof Carbon
            ? $booking->created_at->copy()
            : Carbon::now();

        return $createdAt->addMinutes($this->reservationTimeoutMinutes());
    }

    public function isReservationExpired(RestUnitBooking $booking): bool
    {
        return $this->reservationExpiresAt($booking)->lte(Carbon::now());
    }

    public function expireReservation(RestUnitBooking $booking): bool
    {
        return DB::transaction(function () use ($booking): bool {
            $lockedBooking = RestUnitBooking::query()
                ->with('order')
                ->lockForUpdate()
                ->find($booking->id);

            if (! $lockedBooking || $lockedBooking->status !== RestUnitBooking::STATUS_PENDING_PAYMENT) {
                return false;
            }

            if (! $this->isReservationExpired($lockedBooking)) {
                return false;
            }

            $order = $lockedBooking->order()->lockForUpdate()->first();
            if ($order && $order->status === 'paid_successfully') {
                return false;
            }

            $lockedBooking->status = RestUnitBooking::STATUS_PAYMENT_EXPIRED;
            $lockedBooking->save();

            if ($order) {
                $order->forceFill([
                    'status' => RestUnitBooking::STATUS_PAYMENT_EXPIRED,
                    'gateway_status' => $order->status === 'paid_successfully' ? $order->gateway_status : 'EXPIRED',
                    'checkout_url' => null,
                    'payment_last_synced_at' => now(),
                ])->save();
            }

            return true;
        });
    }

    public function buildSummary(RestUnitBooking $booking): array
    {
        $booking->loadMissing('restUnit.province');
        $order = $booking->relationLoaded('order')
            ? $booking->getRelation('order')
            : null;

        if ($order && ($summary = $this->orderService->pricingSummary($order))) {
            return $summary;
        }

        $startDate = $booking->start_date instanceof Carbon
            ? $booking->start_date->copy()
            : Carbon::parse((string) $booking->start_date);
        $endDate = $booking->end_date instanceof Carbon
            ? $booking->end_date->copy()
            : Carbon::parse((string) $booking->end_date);
        $nights = max($startDate->diffInDays($endDate), 1);
        $pricePerNight = $this->pricePerNightForBooking($booking);
        $stayAmount = $pricePerNight * $nights;
        $totalAmount = (float) $booking->total_price;

        return [
            'title' => __('Payment Summary'),
            'currency' => (string) config('checkout.currency', 'EGP'),
            'items' => [
                [
                    'code' => 'rest_unit_stay',
                    'label' => __('Stay fees'),
                    'description' => $this->stayDescription($booking->restUnit, $booking->unit_type, $nights),
                    'unit_price' => $this->formatMoney($pricePerNight),
                    'quantity' => $nights,
                    'amount' => $this->formatMoney($stayAmount),
                ],
            ],
            'subtotal' => $this->formatMoney($totalAmount),
            'discount' => $this->formatMoney(0),
            'fees' => $this->formatMoney(0),
            'total' => $this->formatMoney($totalAmount),
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

        $roomTypes = collect($filters['room_types'] ?? [])
            ->when(
                isset($filters['room_type']) && $filters['room_type'] !== null,
                fn (Collection $collection) => $collection->push($filters['room_type'])
            )
            ->map(fn (mixed $value): ?string => RestUnit::normalizeUnitType((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'province_ids' => $provinceIds,
            'room_types' => $roomTypes,
            'from_date' => filled($filters['from_date'] ?? null) ? (string) $filters['from_date'] : null,
            'to_date' => filled($filters['to_date'] ?? null) ? (string) $filters['to_date'] : null,
            'page' => max(1, (int) ($filters['page'] ?? 1)),
        ];
    }

    private function prepareUnits(Collection $units, array $filters, bool $includeMedia = false): Collection
    {
        if ($units->isEmpty()) {
            return collect();
        }

        $bookingsByUnit = $this->overlappingBookingsForUnits(
            $units->pluck('id')->all(),
            $filters['from_date'],
            $filters['to_date']
        );

        return $units->map(function (RestUnit $unit) use ($filters, $bookingsByUnit, $includeMedia): RestUnit {
            return $this->decorateUnit($unit, $filters, $bookingsByUnit->get($unit->id, collect()), $includeMedia);
        });
    }

    private function decorateUnit(RestUnit $unit, array $filters, Collection $overlappingBookings, bool $includeMedia = false): RestUnit
    {
        $roomOptions = collect(RestUnit::supportedUnitTypes())
            ->map(fn (string $type): array => $this->buildRoomOption($unit, $type, $filters, $overlappingBookings))
            ->values();

        $datesSelected = filled($filters['from_date']) && filled($filters['to_date']);

        $unit->setAttribute('room_options', $roomOptions->all());
        $unit->setAttribute('total_places', $roomOptions->sum('total_count'));
        $unit->setAttribute('available_places', $roomOptions->sum('available_count'));
        $unit->setAttribute('dates', $datesSelected
            ? [
                'from_date' => $filters['from_date'],
                'to_date' => $filters['to_date'],
                'nights' => $this->calculateNights($filters['from_date'], $filters['to_date']),
            ]
            : null
        );
        $unit->setAttribute('availability_requires_dates', ! $datesSelected);

        if ($includeMedia) {
            $unit->setAttribute('cover_image_url', $unit->getFirstMedia('cover_image')?->getUrl());
            $unit->setAttribute('gallery_urls', $unit->getMedia('gallery')->map(fn ($media) => $media->getUrl())->values()->all());
        }

        return $unit;
    }

    private function buildRoomOption(RestUnit $unit, string $type, array $filters, Collection $overlappingBookings): array
    {
        $inventoryColumn = RestUnit::inventoryColumnForType($type);
        $priceColumn = RestUnit::priceColumnForType($type);
        $totalCount = $inventoryColumn ? max((int) data_get($unit, $inventoryColumn, 0), 0) : 0;
        $datesSelected = filled($filters['from_date']) && filled($filters['to_date']);
        $nights = $datesSelected ? $this->calculateNights($filters['from_date'], $filters['to_date']) : 0;
        $pricePerNight = $priceColumn ? (float) data_get($unit, $priceColumn, 0) : 0;
        $reservedCount = $datesSelected
            ? $overlappingBookings
                ->filter(fn (RestUnitBooking $booking): bool => RestUnitBooking::normalizeUnitType($booking->unit_type) === $type)
                ->count()
            : 0;
        $availableCount = $datesSelected ? max($totalCount - $reservedCount, 0) : $totalCount;

        return [
            'type' => $type,
            'legacy_type' => $this->legacyUnitType($type),
            'label' => $this->unitTypeLabel($type),
            'total_count' => $totalCount,
            'reserved_count' => $reservedCount,
            'available_count' => $availableCount,
            'price_per_night' => $this->formatMoney($pricePerNight),
            'total_price' => $datesSelected ? $this->formatMoney($pricePerNight * $nights) : null,
            'currency' => (string) config('checkout.currency', 'EGP'),
            'is_available' => $availableCount > 0,
            'availability_known' => $datesSelected,
        ];
    }

    private function matchesListFilters(RestUnit $unit, array $filters): bool
    {
        $roomOptions = collect($unit->getAttribute('room_options') ?? []);
        $datesSelected = filled($filters['from_date']) && filled($filters['to_date']);

        if ($filters['room_types'] !== []) {
            return $roomOptions
                ->whereIn('type', $filters['room_types'])
                ->contains(fn (array $option): bool => (bool) ($option['is_available'] ?? false));
        }

        if ($datesSelected) {
            return $roomOptions->contains(fn (array $option): bool => (bool) ($option['is_available'] ?? false));
        }

        return true;
    }

    private function overlappingBookingsForUnits(array $unitIds, ?string $fromDate, ?string $toDate): Collection
    {
        if ($unitIds === [] || blank($fromDate) || blank($toDate)) {
            return collect();
        }

        return RestUnitBooking::query()
            ->whereIn('rest_unit_id', $unitIds)
            ->where(function ($query) use ($fromDate, $toDate): void {
                $query
                    ->where('start_date', '<=', $toDate)
                    ->where('end_date', '>=', $fromDate);
            })
            ->get()
            ->filter(fn (RestUnitBooking $booking): bool => RestUnitBooking::blocksInventoryStatus($booking->status))
            ->groupBy('rest_unit_id');
    }

    private function overlappingBookingsForUnit(RestUnit $unit, ?string $fromDate, ?string $toDate): Collection
    {
        return $this->overlappingBookingsForUnits([$unit->id], $fromDate, $toDate)->get($unit->id, collect());
    }

    private function roomTypeAvailable(RestUnit $unit, string $unitType, string $fromDate, string $toDate): bool
    {
        $preparedUnit = $this->decorateUnit(
            $unit->fresh(),
            [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'room_types' => [$unitType],
                'province_ids' => [],
                'page' => 1,
            ],
            $this->overlappingBookingsForUnit($unit, $fromDate, $toDate)
        );

        return collect($preparedUnit->getAttribute('room_options') ?? [])
            ->where('type', $unitType)
            ->contains(fn (array $option): bool => (bool) ($option['is_available'] ?? false));
    }

    private function buildPricingSummary(
        RestUnit $unit,
        string $unitType,
        Carbon $startDate,
        Carbon $endDate,
        int $nights,
        float $pricePerNight,
        float $stayAmount,
        array $subscriptionCharge,
    ): array {
        $subscriptionAmount = (float) ($subscriptionCharge['amount'] ?? 0);
        $totalAmount = $stayAmount + $subscriptionAmount;
        $items = [
            [
                'code' => 'rest_unit_stay',
                'label' => __('Stay fees'),
                'description' => $this->stayDescription($unit, $unitType, $nights),
                'unit_price' => $this->formatMoney($pricePerNight),
                'quantity' => $nights,
                'amount' => $this->formatMoney($stayAmount),
                'meta' => [
                    'unit_type' => $unitType,
                    'from_date' => $startDate->toDateString(),
                    'to_date' => $endDate->toDateString(),
                    'nights' => $nights,
                ],
            ],
        ];

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

    private function stayDescription(?RestUnit $unit, ?string $unitType, int $nights): string
    {
        $unitName = trim((string) data_get($unit, 'name', __('Rest Unit')));
        $roomLabel = $this->unitTypeLabel((string) $unitType);

        return sprintf('%s - %s (%d %s)', $unitName, $roomLabel, $nights, __('Nights'));
    }

    private function unitTypeLabel(string $type): string
    {
        return match (RestUnit::normalizeUnitType($type)) {
            RestUnit::TYPE_SINGLE_ROOM => __('Single room'),
            RestUnit::TYPE_DOUBLE_ROOM => __('Double room'),
            RestUnit::TYPE_TRIPLE_ROOM => __('Triple room'),
            default => __('Room'),
        };
    }

    private function legacyUnitType(string $type): string
    {
        return match (RestUnit::normalizeUnitType($type)) {
            RestUnit::TYPE_SINGLE_ROOM => 'single_rooms',
            RestUnit::TYPE_DOUBLE_ROOM => 'double_rooms',
            RestUnit::TYPE_TRIPLE_ROOM => 'single_bed',
            default => $type,
        };
    }

    private function pricePerNightForBooking(RestUnitBooking $booking): float
    {
        $restUnit = $booking->restUnit;
        if (! $restUnit) {
            return 0;
        }

        $priceColumn = RestUnit::priceColumnForType((string) $booking->unit_type);

        return $priceColumn ? (float) data_get($restUnit, $priceColumn, 0) : 0;
    }

    private function calculateNights(?string $fromDate, ?string $toDate): int
    {
        if (blank($fromDate) || blank($toDate)) {
            return 0;
        }

        return max(Carbon::parse($fromDate)->diffInDays(Carbon::parse($toDate)), 1);
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
