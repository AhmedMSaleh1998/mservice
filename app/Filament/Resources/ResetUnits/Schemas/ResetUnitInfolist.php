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
use Modules\Services\Models\RestUnitBooking;

class ResetUnitInfolist
{
    private static array $inventorySummaryCache = [];

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
                        TextEntry::make('province.name')
                            ->label(__('Province'))
                            ->formatStateUsing(fn ($state, RestUnit $record): ?string => $record->province?->getTranslation('name', app()->getLocale())),
                        IconEntry::make('is_active')
                            ->label(__('Is Active'))
                            ->boolean()
                            ->trueColor('success')
                            ->falseColor('danger'),
                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label(__('Updated At'))
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
                        TextEntry::make('inventory_summary_from_date')
                            ->label(__('From Date'))
                            ->getStateUsing(fn (RestUnit $record): string => static::inventorySummary($record)['from_date']),
                        TextEntry::make('inventory_summary_to_date')
                            ->label(__('To Date'))
                            ->getStateUsing(fn (RestUnit $record): string => static::inventorySummary($record)['to_date']),
                        TextEntry::make('overall_total_rooms')
                            ->label(__('Total Rooms'))
                            ->getStateUsing(fn (RestUnit $record): int => static::inventorySummary($record)['overall']['total']),
                        TextEntry::make('overall_reserved_rooms')
                            ->label(__('Occupied Rooms'))
                            ->getStateUsing(fn (RestUnit $record): int => static::inventorySummary($record)['overall']['reserved']),
                        TextEntry::make('overall_available_rooms')
                            ->label(__('Available Rooms'))
                            ->getStateUsing(fn (RestUnit $record): int => static::inventorySummary($record)['overall']['available']),
                        TextEntry::make('period_pending_bookings')
                            ->label(__('Pending Payment Bookings'))
                            ->getStateUsing(fn (RestUnit $record): int => static::inventorySummary($record)['statuses'][RestUnitBooking::STATUS_PENDING_PAYMENT]),
                        TextEntry::make('period_paid_bookings')
                            ->label(__('Paid Bookings'))
                            ->getStateUsing(fn (RestUnit $record): int => static::inventorySummary($record)['statuses'][RestUnitBooking::STATUS_PAID_SUCCESSFULLY]),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
                Section::make(__('Single Rooms'))
                    ->schema([
                        TextEntry::make('single_rooms_total')
                            ->label(__('Total Rooms'))
                            ->getStateUsing(fn (RestUnit $record): int => static::roomTypeSummary($record, RestUnit::TYPE_SINGLE_ROOM)['total']),
                        TextEntry::make('single_rooms_reserved')
                            ->label(__('Occupied Rooms'))
                            ->getStateUsing(fn (RestUnit $record): int => static::roomTypeSummary($record, RestUnit::TYPE_SINGLE_ROOM)['reserved']),
                        TextEntry::make('single_rooms_available')
                            ->label(__('Available Rooms'))
                            ->getStateUsing(fn (RestUnit $record): int => static::roomTypeSummary($record, RestUnit::TYPE_SINGLE_ROOM)['available']),
                        TextEntry::make('single_room_price')
                            ->label(__('Price Per Night'))
                            ->money('EGP'),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
                Section::make(__('Double Rooms'))
                    ->schema([
                        TextEntry::make('double_rooms_total')
                            ->label(__('Total Rooms'))
                            ->getStateUsing(fn (RestUnit $record): int => static::roomTypeSummary($record, RestUnit::TYPE_DOUBLE_ROOM)['total']),
                        TextEntry::make('double_rooms_reserved')
                            ->label(__('Occupied Rooms'))
                            ->getStateUsing(fn (RestUnit $record): int => static::roomTypeSummary($record, RestUnit::TYPE_DOUBLE_ROOM)['reserved']),
                        TextEntry::make('double_rooms_available')
                            ->label(__('Available Rooms'))
                            ->getStateUsing(fn (RestUnit $record): int => static::roomTypeSummary($record, RestUnit::TYPE_DOUBLE_ROOM)['available']),
                        TextEntry::make('double_room_price')
                            ->label(__('Price Per Night'))
                            ->money('EGP'),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
                Section::make(__('Triple Rooms'))
                    ->schema([
                        TextEntry::make('triple_rooms_total')
                            ->label(__('Total Rooms'))
                            ->getStateUsing(fn (RestUnit $record): int => static::roomTypeSummary($record, RestUnit::TYPE_TRIPLE_ROOM)['total']),
                        TextEntry::make('triple_rooms_reserved')
                            ->label(__('Occupied Rooms'))
                            ->getStateUsing(fn (RestUnit $record): int => static::roomTypeSummary($record, RestUnit::TYPE_TRIPLE_ROOM)['reserved']),
                        TextEntry::make('triple_rooms_available')
                            ->label(__('Available Rooms'))
                            ->getStateUsing(fn (RestUnit $record): int => static::roomTypeSummary($record, RestUnit::TYPE_TRIPLE_ROOM)['available']),
                        TextEntry::make('triple_room_price')
                            ->label(__('Price Per Night'))
                            ->money('EGP'),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
                Section::make(__('Guests in Selected Period'))
                    ->schema([
                        TextEntry::make('period_bookings_notice')
                            ->label(__('Guests in Selected Period'))
                            ->hiddenLabel()
                            ->getStateUsing(fn (): string => __('No active bookings for the selected period.'))
                            ->visible(fn (RestUnit $record): bool => ! static::hasOverlappingBookings($record))
                            ->columnSpanFull(),
                        RepeatableEntry::make('period_bookings')
                            ->label(__('Guests in Selected Period'))
                            ->hiddenLabel()
                            ->getStateUsing(fn (RestUnit $record): array => static::overlappingBookings($record))
                            ->visible(fn (RestUnit $record): bool => static::hasOverlappingBookings($record))
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
                                TextEntry::make('booking_id')
                                    ->hiddenLabel(),
                                TextEntry::make('user_name')
                                    ->hiddenLabel()
                                    ->placeholder('-'),
                                TextEntry::make('user_phone')
                                    ->hiddenLabel()
                                    ->placeholder('-'),
                                TextEntry::make('unit_type_label')
                                    ->hiddenLabel(),
                                TextEntry::make('start_date')
                                    ->hiddenLabel(),
                                TextEntry::make('end_date')
                                    ->hiddenLabel(),
                                TextEntry::make('status_label')
                                    ->hiddenLabel(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    private static function roomTypeSummary(RestUnit $record, string $type): array
    {
        return static::inventorySummary($record)['types'][$type] ?? [
            'total' => 0,
            'reserved' => 0,
            'available' => 0,
        ];
    }

    private static function inventorySummary(RestUnit $record): array
    {
        $range = static::selectedRange();
        $fromDate = $range['from_date'];
        $toDate = $range['to_date'];
        $cacheKey = sprintf('%s:%s:%s', spl_object_id($record), $fromDate, $toDate);

        if (array_key_exists($cacheKey, static::$inventorySummaryCache)) {
            return static::$inventorySummaryCache[$cacheKey];
        }

        $blockingBookings = $record->bookings()
            ->with(['user:id,name,phone'])
            ->select(['id', 'rest_unit_id', 'user_id', 'unit_type', 'status', 'start_date', 'end_date'])
            ->where('start_date', '<=', $toDate)
            ->where('end_date', '>=', $fromDate)
            ->get()
            ->filter(fn (RestUnitBooking $booking): bool => RestUnitBooking::blocksInventoryStatus($booking->status));
        $peakCounts = RestUnitBooking::peakActiveCounts($blockingBookings, $fromDate, $toDate);

        $types = [];
        $overallTotal = 0;
        $overallReserved = (int) ($peakCounts['overall'] ?? 0);

        foreach (RestUnit::supportedUnitTypes() as $type) {
            $column = RestUnit::inventoryColumnForType($type);
            $total = $column ? max((int) data_get($record, $column, 0), 0) : 0;
            $reserved = (int) ($peakCounts['types'][$type] ?? 0);

            $types[$type] = [
                'total' => $total,
                'reserved' => $reserved,
                'available' => max($total - $reserved, 0),
            ];

            $overallTotal += $total;
        }

        return static::$inventorySummaryCache[$cacheKey] = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'overall' => [
                'total' => $overallTotal,
                'reserved' => $overallReserved,
                'available' => max($overallTotal - $overallReserved, 0),
            ],
            'statuses' => [
                RestUnitBooking::STATUS_PENDING_PAYMENT => $blockingBookings->where('status', RestUnitBooking::STATUS_PENDING_PAYMENT)->count(),
                RestUnitBooking::STATUS_PAID_SUCCESSFULLY => $blockingBookings->where('status', RestUnitBooking::STATUS_PAID_SUCCESSFULLY)->count(),
            ],
            'types' => $types,
            'bookings' => $blockingBookings
                ->sortBy(fn (RestUnitBooking $booking): string => sprintf(
                    '%s|%s|%s',
                    optional($booking->start_date)->toDateString(),
                    optional($booking->end_date)->toDateString(),
                    str_pad((string) $booking->id, 10, '0', STR_PAD_LEFT),
                ))
                ->map(fn (RestUnitBooking $booking): array => [
                    'booking_id' => $booking->id,
                    'user_name' => $booking->user?->name,
                    'user_phone' => $booking->user?->phone,
                    'unit_type_label' => static::roomTypeLabel($booking->unit_type),
                    'start_date' => optional($booking->start_date)->toDateString(),
                    'end_date' => optional($booking->end_date)->toDateString(),
                    'status_label' => static::bookingStatusLabel($booking->status),
                ])
                ->values()
                ->all(),
        ];
    }

    private static function overlappingBookings(RestUnit $record): array
    {
        return static::inventorySummary($record)['bookings'] ?? [];
    }

    private static function hasOverlappingBookings(RestUnit $record): bool
    {
        return static::overlappingBookings($record) !== [];
    }

    private static function selectedRange(): array
    {
        $today = now()->toDateString();
        $fromDate = static::normalizeDate((string) request()->query('from_date', $today)) ?? $today;
        $toDate = static::normalizeDate((string) request()->query('to_date', $fromDate)) ?? $fromDate;

        if ($toDate < $fromDate) {
            $toDate = $fromDate;
        }

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
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

    private static function roomTypeLabel(?string $value): string
    {
        return match (RestUnit::normalizeUnitType($value)) {
            RestUnit::TYPE_SINGLE_ROOM => __('Single room'),
            RestUnit::TYPE_DOUBLE_ROOM => __('Double room'),
            RestUnit::TYPE_TRIPLE_ROOM => __('Triple room'),
            default => (string) $value,
        };
    }

    private static function bookingStatusLabel(?string $value): string
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
