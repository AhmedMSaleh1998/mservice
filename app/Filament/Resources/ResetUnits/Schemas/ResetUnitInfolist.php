<?php

namespace App\Filament\Resources\ResetUnits\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Modules\Services\Models\RestUnit;
use Modules\Services\Models\RestUnitBed;
use Modules\Services\Models\RestUnitBooking;
use Modules\Services\Models\RestUnitRoom;

class ResetUnitInfolist
{
    private static array $summaryCache = [];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Cover Image'))
                    ->schema([
                        SpatieMediaLibraryImageEntry::make('cover_image')
                            ->label(__('Image'))
                            ->collection('cover_image')
                            ->placeholder(__('No image uploaded.'))
                            ->imageSize(220)
                            ->url(fn (RestUnit $record): string => $record->getFirstMediaUrl('cover_image'))
                            ->openUrlInNewTab()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make(__('Rest Unit'))
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('Name'))
                            ->formatStateUsing(fn ($state, RestUnit $record): string => (string) $record->getTranslation('name', app()->getLocale())),
                        TextEntry::make('type')
                            ->label(__('Type'))
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => RestUnit::typeLabel($state)),
                        TextEntry::make('province.name')
                            ->label(__('Province'))
                            ->formatStateUsing(fn ($state, RestUnit $record): ?string => $record->province?->getTranslation('name', app()->getLocale())),
                        IconEntry::make('is_active')
                            ->label(__('Is Active'))
                            ->boolean()
                            ->trueColor('success')
                            ->falseColor('danger'),
                        TextEntry::make('status')
                            ->label(__('Unit status'))
                            ->badge()
                            ->visible(fn (RestUnit $record): bool => $record->isWholeUnit())
                            ->formatStateUsing(fn (?string $state): string => $state === RestUnit::STATUS_MAINTENANCE ? __('Under maintenance') : __('In service'))
                            ->color(fn (?string $state): string => $state === RestUnit::STATUS_MAINTENANCE ? 'warning' : 'success'),
                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime(),
                        TextEntry::make('address')
                            ->label(__('Address'))
                            ->formatStateUsing(fn ($state, RestUnit $record): string => (string) $record->getTranslation('address', app()->getLocale()))
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('Availability for Selected Period'))
                    ->schema([
                        TextEntry::make('summary_from')
                            ->label(__('From Date'))
                            ->getStateUsing(fn (RestUnit $record): string => static::summary($record)['from_date']),
                        TextEntry::make('summary_to')
                            ->label(__('To Date'))
                            ->getStateUsing(fn (RestUnit $record): string => static::summary($record)['to_date']),
                        TextEntry::make('overall_total')
                            ->label(__('Total Places'))
                            ->getStateUsing(fn (RestUnit $record): int => static::summary($record)['overall']['total']),
                        TextEntry::make('overall_reserved')
                            ->label(__('Occupied Places'))
                            ->getStateUsing(fn (RestUnit $record): int => static::summary($record)['overall']['reserved']),
                        TextEntry::make('overall_available')
                            ->label(__('Available Places'))
                            ->getStateUsing(fn (RestUnit $record): int => static::summary($record)['overall']['available']),
                        TextEntry::make('pending_bookings')
                            ->label(__('Pending Payment Bookings'))
                            ->getStateUsing(fn (RestUnit $record): int => static::summary($record)['statuses'][RestUnitBooking::STATUS_PENDING_PAYMENT]),
                        TextEntry::make('paid_bookings')
                            ->label(__('Paid Bookings'))
                            ->getStateUsing(fn (RestUnit $record): int => static::summary($record)['statuses'][RestUnitBooking::STATUS_PAID_SUCCESSFULLY]),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
                RepeatableEntry::make('breakdown')
                    ->label(__('Availability by Type'))
                    ->getStateUsing(fn (RestUnit $record): array => static::summary($record)['options'])
                    ->table([
                        TableColumn::make(__('Type')),
                        TableColumn::make(__('Total Places')),
                        TableColumn::make(__('Occupied')),
                        TableColumn::make(__('Available')),
                        TableColumn::make(__('Price Per Night')),
                    ])
                    ->schema([
                        TextEntry::make('label')->hiddenLabel(),
                        TextEntry::make('total')->hiddenLabel(),
                        TextEntry::make('reserved')->hiddenLabel(),
                        TextEntry::make('available')->hiddenLabel(),
                        TextEntry::make('price')->hiddenLabel()->money('EGP'),
                    ])
                    ->columnSpanFull(),
                Section::make(__('Guests in Selected Period'))
                    ->schema([
                        TextEntry::make('guests_notice')
                            ->hiddenLabel()
                            ->getStateUsing(fn (): string => __('No active bookings for the selected period.'))
                            ->visible(fn (RestUnit $record): bool => static::summary($record)['bookings'] === [])
                            ->columnSpanFull(),
                        RepeatableEntry::make('period_bookings')
                            ->hiddenLabel()
                            ->getStateUsing(fn (RestUnit $record): array => static::summary($record)['bookings'])
                            ->visible(fn (RestUnit $record): bool => static::summary($record)['bookings'] !== [])
                            ->table([
                                TableColumn::make(__('Booking ID')),
                                TableColumn::make(__('Guest')),
                                TableColumn::make(__('Phone')),
                                TableColumn::make(__('Type')),
                                TableColumn::make(__('Start Date')),
                                TableColumn::make(__('End Date')),
                                TableColumn::make(__('Status')),
                            ])
                            ->schema([
                                TextEntry::make('booking_id')->hiddenLabel(),
                                TextEntry::make('user_name')->hiddenLabel()->placeholder('-'),
                                TextEntry::make('user_phone')->hiddenLabel()->placeholder('-'),
                                TextEntry::make('target_label')->hiddenLabel(),
                                TextEntry::make('start_date')->hiddenLabel(),
                                TextEntry::make('end_date')->hiddenLabel(),
                                TextEntry::make('status_label')->hiddenLabel(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    private static function summary(RestUnit $record): array
    {
        $range = static::selectedRange();
        $filtered = $range['filtered'];
        $from = $range['from_date'];
        $to = $range['to_date'];
        $cacheKey = sprintf('%s:%s:%s', spl_object_id($record), $from ?? 'all', $to ?? 'all');

        if (array_key_exists($cacheKey, static::$summaryCache)) {
            return static::$summaryCache[$cacheKey];
        }

        $record->loadMissing(['rooms.roomType', 'beds']);

        // Default (no dates) shows all bookings; dates are an optional filter.
        $blocking = $record->bookings()
            ->with(['user:id,name,phone', 'room.roomType'])
            ->when($filtered, fn ($q) => $q->where('start_date', '<=', $to)->where('end_date', '>=', $from))
            ->get()
            ->filter(fn (RestUnitBooking $b): bool => RestUnitBooking::blocksInventoryStatus($b->status));

        // Occupancy is only meaningful for a chosen period; otherwise show full capacity.
        $options = static::options($record, $filtered ? $blocking : collect());
        $overallTotal = array_sum(array_column($options, 'total'));
        $overallReserved = array_sum(array_column($options, 'reserved'));

        return static::$summaryCache[$cacheKey] = [
            'from_date' => $from ?? '—',
            'to_date' => $to ?? '—',
            'overall' => [
                'total' => $overallTotal,
                'reserved' => $overallReserved,
                'available' => max($overallTotal - $overallReserved, 0),
            ],
            'statuses' => [
                RestUnitBooking::STATUS_PENDING_PAYMENT => $blocking->where('status', RestUnitBooking::STATUS_PENDING_PAYMENT)->count(),
                RestUnitBooking::STATUS_PAID_SUCCESSFULLY => $blocking->where('status', RestUnitBooking::STATUS_PAID_SUCCESSFULLY)->count(),
            ],
            'options' => $options,
            'bookings' => $blocking
                ->sortBy(fn (RestUnitBooking $b): string => sprintf('%s|%010d', optional($b->start_date)->toDateString(), $b->id))
                ->map(fn (RestUnitBooking $b): array => [
                    'booking_id' => $b->id,
                    'user_name' => $b->guestName(),
                    'user_phone' => $b->user?->phone,
                    'target_label' => $b->targetLabel(),
                    'start_date' => optional($b->start_date)->toDateString(),
                    'end_date' => optional($b->end_date)->toDateString(),
                    'status_label' => static::statusLabel($b->status),
                ])
                ->values()
                ->all(),
        ];
    }

    private static function options(RestUnit $record, $blocking): array
    {
        if ($record->isRooms()) {
            $occupied = $blocking->pluck('rest_unit_room_id')->filter()->all();

            return $record->rooms
                ->where('status', RestUnitRoom::STATUS_IN_SERVICE)
                ->groupBy('room_type_id')
                ->map(function ($rooms) use ($occupied): array {
                    $total = $rooms->count();
                    $available = $rooms->reject(fn (RestUnitRoom $room): bool => in_array($room->id, $occupied, true))->count();

                    return [
                        'label' => $rooms->first()->typeName() ?? __('Room'),
                        'total' => $total,
                        'reserved' => max($total - $available, 0),
                        'available' => $available,
                        'price' => (float) ($rooms->min('price') ?? 0),
                    ];
                })->values()->all();
        }

        if ($record->isBeds()) {
            $occupied = $blocking->pluck('rest_unit_bed_id')->filter()->all();
            $beds = $record->beds->where('status', RestUnitBed::STATUS_IN_SERVICE);
            $total = $beds->count();
            $available = $beds->reject(fn (RestUnitBed $bed): bool => in_array($bed->id, $occupied, true))->count();

            return [[
                'label' => __('Beds'),
                'total' => $total,
                'reserved' => max($total - $available, 0),
                'available' => $available,
                'price' => (float) $record->price,
            ]];
        }

        $total = $record->isUnderMaintenance() ? 0 : 1;
        $reserved = $blocking->isNotEmpty() ? 1 : 0;

        return [[
            'label' => __('Whole unit'),
            'total' => $total,
            'reserved' => $reserved,
            'available' => max($total - $reserved, 0),
            'price' => (float) $record->price,
        ]];
    }

    private static function selectedRange(): array
    {
        $from = static::normalizeDate((string) request()->query('from_date'));
        $to = static::normalizeDate((string) request()->query('to_date'));
        $filtered = filled($from) && filled($to);

        if ($filtered && $to < $from) {
            $to = $from;
        }

        return [
            'from_date' => $filtered ? $from : null,
            'to_date' => $filtered ? $to : null,
            'filtered' => $filtered,
        ];
    }

    private static function normalizeDate(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function statusLabel(?string $value): string
    {
        return match ($value) {
            RestUnitBooking::STATUS_PENDING_PAYMENT => __('Pending Payment'),
            RestUnitBooking::STATUS_PAID_SUCCESSFULLY => __('Paid Successfully'),
            RestUnitBooking::STATUS_PAYMENT_EXPIRED => __('Payment Expired'),
            RestUnitBooking::STATUS_CANCELLED => __('Cancelled'),
            default => (string) $value,
        };
    }
}
