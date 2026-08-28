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
use Modules\Services\Models\RestUnitBed;
use Modules\Services\Models\RestUnitBooking;
use Modules\Services\Models\RestUnitRoom;
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

    private const EAGER = ['province', 'rooms.roomType', 'beds'];

    public function getList(int $limit = 100, array $filters = []): LengthAwarePaginator
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $page = max(1, (int) ($normalizedFilters['page'] ?? 1));

        $units = RestUnit::query()
            ->with(self::EAGER)
            ->where('is_active', true)
            ->when(
                $normalizedFilters['province_ids'] !== [],
                fn ($query) => $query->whereIn('province_id', $normalizedFilters['province_ids'])
            )
            ->orderBy('id')
            ->get();

        $preparedUnits = $this->prepareUnits($units, $normalizedFilters)
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
            ->with([...self::EAGER, 'media'])
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
        $user = User::query()->findOrFail((int) $data['user_id']);
        $subscriptionCharge = $this->resolveSubscriptionCharge($user);

        return DB::transaction(function () use ($data, $user, $subscriptionCharge): RestUnitBooking {
            $unit = RestUnit::query()
                ->with([...self::EAGER, 'media'])
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
            $from = $startDate->toDateString();
            $to = $endDate->toDateString();

            $target = $this->resolveBookingTarget($unit, $data, $from, $to);

            $pricePerNight = $target['price'];
            $stayAmount = $pricePerNight * $nights;
            $subscriptionAmount = (float) ($subscriptionCharge['amount'] ?? 0);
            $totalAmount = $stayAmount + $subscriptionAmount;
            $isFreeBooking = $this->orderService->isFreeAmount($totalAmount);
            $paidAt = $isFreeBooking ? now() : null;
            $bookingStatus = $isFreeBooking
                ? RestUnitBooking::STATUS_PAID_SUCCESSFULLY
                : RestUnitBooking::STATUS_PENDING_PAYMENT;
            $pricing = $this->buildPricingSummary(
                $unit,
                $target['label'],
                $startDate,
                $endDate,
                $nights,
                $pricePerNight,
                $stayAmount,
                $subscriptionCharge,
            );

            $booking = $unit->bookings()->create([
                'user_id' => $user->id,
                'beneficiary_type' => RestUnitBooking::BENEFICIARY_MEMBER,
                'rest_unit_room_id' => $target['room_id'],
                'rest_unit_bed_id' => $target['bed_id'],
                'start_date' => $from,
                'end_date' => $to,
                'unit_type' => $target['label'],
                'total_price' => $totalAmount,
                'status' => $bookingStatus,
                'paid_at' => $paidAt,
            ]);

            if (! $isFreeBooking) {
                $this->orderService->sync($booking, [
                    'user_id' => $user->id,
                    'amount' => $totalAmount,
                    'status' => $bookingStatus,
                    'payload' => $this->orderService->withPricingPayload(
                        $this->orderService->withSubscriptionChargePayload(null, $subscriptionCharge),
                        $pricing,
                    ),
                ]);
            }

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
        $booking->loadMissing('restUnit.province', 'room.roomType', 'bed');
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
                    'description' => $this->stayDescription($booking->restUnit, $booking->targetLabel(), $nights),
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

        $roomTypeIds = collect($filters['room_type_ids'] ?? [])
            ->when(
                isset($filters['room_type_id']) && $filters['room_type_id'] !== null,
                fn (Collection $collection) => $collection->push($filters['room_type_id'])
            )
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values()
            ->all();

        return [
            'province_ids' => $provinceIds,
            'room_type_ids' => $roomTypeIds,
            'from_date' => filled($filters['from_date'] ?? null) ? (string) $filters['from_date'] : null,
            'to_date' => filled($filters['to_date'] ?? null) ? (string) $filters['to_date'] : null,
            'page' => max(1, (int) ($filters['page'] ?? 1)),
        ];
    }

    private function prepareUnits(Collection $units, array $filters): Collection
    {
        if ($units->isEmpty()) {
            return collect();
        }

        $bookingsByUnit = $this->overlappingBookingsForUnits(
            $units->pluck('id')->all(),
            $filters['from_date'],
            $filters['to_date']
        );

        return $units->map(function (RestUnit $unit) use ($filters, $bookingsByUnit): RestUnit {
            return $this->decorateUnit($unit, $filters, $bookingsByUnit->get($unit->id, collect()), false);
        });
    }

    private function decorateUnit(RestUnit $unit, array $filters, Collection $overlappingBookings, bool $includeMedia = false): RestUnit
    {
        $datesSelected = filled($filters['from_date']) && filled($filters['to_date']);
        $nights = $datesSelected ? $this->calculateNights($filters['from_date'], $filters['to_date']) : 0;

        $roomOptions = $this->buildOptions($unit, $overlappingBookings, $datesSelected, $nights);

        $unit->setAttribute('room_options', $roomOptions->all());
        $unit->setAttribute('total_places', $roomOptions->sum('total_count'));
        $unit->setAttribute('available_places', $datesSelected ? $roomOptions->sum('available_count') : $roomOptions->sum('total_count'));
        $unit->setAttribute('dates', $datesSelected
            ? [
                'from_date' => $filters['from_date'],
                'to_date' => $filters['to_date'],
                'nights' => $nights,
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

    private function buildOptions(RestUnit $unit, Collection $overlappingBookings, bool $datesSelected, int $nights): Collection
    {
        $currency = (string) config('checkout.currency', 'EGP');

        if ($unit->isRooms()) {
            $occupiedRoomIds = $overlappingBookings->pluck('rest_unit_room_id')->filter()->all();

            return $unit->rooms
                ->where('status', RestUnitRoom::STATUS_IN_SERVICE)
                ->groupBy('room_type_id')
                ->map(function (Collection $rooms) use ($occupiedRoomIds, $datesSelected, $nights, $currency): array {
                    $total = $rooms->count();
                    $available = $datesSelected
                        ? $rooms->reject(fn (RestUnitRoom $room): bool => in_array($room->id, $occupiedRoomIds, true))->count()
                        : $total;
                    $reserved = $datesSelected ? max($total - $available, 0) : 0;
                    $price = (float) ($rooms->min('price') ?? 0);
                    $first = $rooms->first();

                    return $this->optionRow(
                        key: 'room_type_'.$first->room_type_id,
                        label: $first->typeName() ?? __('Room'),
                        total: $total,
                        reserved: $reserved,
                        available: $available,
                        price: $price,
                        datesSelected: $datesSelected,
                        nights: $nights,
                        currency: $currency,
                        extra: ['room_type_id' => $first->room_type_id],
                    );
                })
                ->values();
        }

        if ($unit->isBeds()) {
            $occupiedBedIds = $overlappingBookings->pluck('rest_unit_bed_id')->filter()->all();
            $beds = $unit->beds->where('status', RestUnitBed::STATUS_IN_SERVICE);
            $total = $beds->count();
            $available = $datesSelected
                ? $beds->reject(fn (RestUnitBed $bed): bool => in_array($bed->id, $occupiedBedIds, true))->count()
                : $total;
            $reserved = $datesSelected ? max($total - $available, 0) : 0;

            return collect([$this->optionRow(
                key: 'beds',
                label: __('Beds'),
                total: $total,
                reserved: $reserved,
                available: $available,
                price: (float) $unit->price,
                datesSelected: $datesSelected,
                nights: $nights,
                currency: $currency,
            )]);
        }

        // whole unit
        $total = $unit->isUnderMaintenance() ? 0 : 1;
        $reserved = $datesSelected && $overlappingBookings->isNotEmpty() ? 1 : 0;
        $available = $datesSelected ? max($total - $reserved, 0) : $total;

        return collect([$this->optionRow(
            key: 'whole_unit',
            label: __('Whole unit'),
            total: $total,
            reserved: $reserved,
            available: $available,
            price: (float) $unit->price,
            datesSelected: $datesSelected,
            nights: $nights,
            currency: $currency,
        )]);
    }

    private function optionRow(string $key, string $label, int $total, int $reserved, int $available, float $price, bool $datesSelected, int $nights, string $currency, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'type' => $key,
            'label' => $label,
            'total_count' => $total,
            'reserved_count' => $reserved,
            'available_count' => $available,
            'price_per_night' => $this->formatMoney($price),
            'total_price' => $datesSelected ? $this->formatMoney($price * $nights) : null,
            'currency' => $currency,
            'is_available' => $available > 0,
            'availability_known' => $datesSelected,
        ], $extra);
    }

    private function matchesListFilters(RestUnit $unit, array $filters): bool
    {
        $roomOptions = collect($unit->getAttribute('room_options') ?? []);
        $datesSelected = filled($filters['from_date']) && filled($filters['to_date']);

        if ($filters['room_type_ids'] !== []) {
            return $roomOptions
                ->whereIn('room_type_id', $filters['room_type_ids'])
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

        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->startOfDay();

        // Checkout day is exclusive: a stay ending on day X frees day X for the
        // next guest. A single-day range still counts as one night's stay.
        if ($to->lessThanOrEqualTo($from)) {
            $to = $from->copy()->addDay();
        }

        return RestUnitBooking::query()
            ->whereIn('rest_unit_id', $unitIds)
            ->where(function ($query) use ($from, $to): void {
                $query
                    ->whereDate('start_date', '<', $to->toDateString())
                    ->whereDate('end_date', '>', $from->toDateString());
            })
            ->get()
            ->filter(fn (RestUnitBooking $booking): bool => RestUnitBooking::blocksInventoryStatus($booking->status))
            ->groupBy('rest_unit_id');
    }

    private function overlappingBookingsForUnit(RestUnit $unit, ?string $fromDate, ?string $toDate): Collection
    {
        return $this->overlappingBookingsForUnits([$unit->id], $fromDate, $toDate)->get($unit->id, collect());
    }

    /**
     * Auto-assign the first available concrete unit for a booking.
     *
     * @return array{room_id: ?int, bed_id: ?int, price: float, label: string}
     */
    private function resolveBookingTarget(RestUnit $unit, array $data, string $from, string $to): array
    {
        $blocking = $this->overlappingBookingsForUnit($unit, $from, $to);

        if ($unit->isRooms()) {
            $occupied = $blocking->pluck('rest_unit_room_id')->filter()->all();
            $rooms = $unit->rooms->where('status', RestUnitRoom::STATUS_IN_SERVICE);

            if (filled($data['room_type_id'] ?? null)) {
                $rooms = $rooms->where('room_type_id', (int) $data['room_type_id']);
            }

            $room = $rooms->first(fn (RestUnitRoom $room): bool => ! in_array($room->id, $occupied, true));

            if (! $room) {
                throw ValidationException::withMessages([
                    'rest_unit_id' => __('This room type is not available for the selected dates.'),
                ]);
            }

            return ['room_id' => $room->id, 'bed_id' => null, 'price' => (float) $room->price, 'label' => $room->label()];
        }

        if ($unit->isBeds()) {
            $occupied = $blocking->pluck('rest_unit_bed_id')->filter()->all();
            $bed = $unit->beds
                ->where('status', RestUnitBed::STATUS_IN_SERVICE)
                ->first(fn (RestUnitBed $bed): bool => ! in_array($bed->id, $occupied, true));

            if (! $bed) {
                throw ValidationException::withMessages([
                    'rest_unit_id' => __('No beds are available for the selected dates.'),
                ]);
            }

            return ['room_id' => null, 'bed_id' => $bed->id, 'price' => (float) $unit->price, 'label' => $bed->label];
        }

        // whole unit
        if ($unit->isUnderMaintenance() || $blocking->isNotEmpty()) {
            throw ValidationException::withMessages([
                'rest_unit_id' => __('This unit is not available for the selected dates.'),
            ]);
        }

        return ['room_id' => null, 'bed_id' => null, 'price' => (float) $unit->price, 'label' => __('Whole unit')];
    }

    private function buildPricingSummary(
        RestUnit $unit,
        string $label,
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
                'description' => $this->stayDescription($unit, $label, $nights),
                'unit_price' => $this->formatMoney($pricePerNight),
                'quantity' => $nights,
                'amount' => $this->formatMoney($stayAmount),
                'meta' => [
                    'unit_type' => $label,
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

    private function stayDescription(?RestUnit $unit, ?string $label, int $nights): string
    {
        $unitName = trim((string) data_get($unit, 'name', __('Rest Unit')));

        return sprintf('%s - %s (%d %s)', $unitName, (string) $label, $nights, __('Nights'));
    }

    private function pricePerNightForBooking(RestUnitBooking $booking): float
    {
        if ($booking->rest_unit_room_id) {
            return (float) ($booking->room?->price ?? 0);
        }

        return (float) ($booking->restUnit?->price ?? 0);
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
