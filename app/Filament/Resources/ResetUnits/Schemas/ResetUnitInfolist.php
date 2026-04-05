<?php

namespace App\Filament\Resources\ResetUnits\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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
                Section::make(__('Availability Today'))
                    ->schema([
                        TextEntry::make('inventory_summary_date')
                            ->label(__('Calculated For'))
                            ->getStateUsing(fn (RestUnit $record): string => static::inventorySummary($record)['date']),
                        TextEntry::make('overall_total_rooms')
                            ->label(__('Total Rooms'))
                            ->getStateUsing(fn (RestUnit $record): int => static::inventorySummary($record)['overall']['total']),
                        TextEntry::make('overall_reserved_rooms')
                            ->label(__('Booked Today'))
                            ->getStateUsing(fn (RestUnit $record): int => static::inventorySummary($record)['overall']['reserved']),
                        TextEntry::make('overall_available_rooms')
                            ->label(__('Available Today'))
                            ->getStateUsing(fn (RestUnit $record): int => static::inventorySummary($record)['overall']['available']),
                        TextEntry::make('today_pending_bookings')
                            ->label(__('Pending Payment Bookings'))
                            ->getStateUsing(fn (RestUnit $record): int => static::inventorySummary($record)['statuses'][RestUnitBooking::STATUS_PENDING_PAYMENT]),
                        TextEntry::make('today_paid_bookings')
                            ->label(__('Paid Bookings'))
                            ->getStateUsing(fn (RestUnit $record): int => static::inventorySummary($record)['statuses'][RestUnitBooking::STATUS_PAID_SUCCESSFULLY]),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('Single Rooms'))
                    ->schema([
                        TextEntry::make('single_rooms_total')
                            ->label(__('Total Rooms'))
                            ->getStateUsing(fn (RestUnit $record): int => static::roomTypeSummary($record, RestUnit::TYPE_SINGLE_ROOM)['total']),
                        TextEntry::make('single_rooms_reserved')
                            ->label(__('Booked Today'))
                            ->getStateUsing(fn (RestUnit $record): int => static::roomTypeSummary($record, RestUnit::TYPE_SINGLE_ROOM)['reserved']),
                        TextEntry::make('single_rooms_available')
                            ->label(__('Available Today'))
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
                            ->label(__('Booked Today'))
                            ->getStateUsing(fn (RestUnit $record): int => static::roomTypeSummary($record, RestUnit::TYPE_DOUBLE_ROOM)['reserved']),
                        TextEntry::make('double_rooms_available')
                            ->label(__('Available Today'))
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
                            ->label(__('Booked Today'))
                            ->getStateUsing(fn (RestUnit $record): int => static::roomTypeSummary($record, RestUnit::TYPE_TRIPLE_ROOM)['reserved']),
                        TextEntry::make('triple_rooms_available')
                            ->label(__('Available Today'))
                            ->getStateUsing(fn (RestUnit $record): int => static::roomTypeSummary($record, RestUnit::TYPE_TRIPLE_ROOM)['available']),
                        TextEntry::make('triple_room_price')
                            ->label(__('Price Per Night'))
                            ->money('EGP'),
                    ])
                    ->columns(4)
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
        $date = now()->toDateString();
        $cacheKey = sprintf('%s:%s', (string) $record->getKey(), $date);

        if (array_key_exists($cacheKey, static::$inventorySummaryCache)) {
            return static::$inventorySummaryCache[$cacheKey];
        }

        $blockingBookings = $record->bookings()
            ->select(['id', 'unit_type', 'status'])
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->get()
            ->filter(fn (RestUnitBooking $booking): bool => RestUnitBooking::blocksInventoryStatus($booking->status));

        $types = [];
        $overallTotal = 0;
        $overallReserved = 0;

        foreach (RestUnit::supportedUnitTypes() as $type) {
            $column = RestUnit::inventoryColumnForType($type);
            $total = $column ? max((int) data_get($record, $column, 0), 0) : 0;
            $reserved = $blockingBookings
                ->filter(fn (RestUnitBooking $booking): bool => RestUnitBooking::normalizeUnitType($booking->unit_type) === $type)
                ->count();

            $types[$type] = [
                'total' => $total,
                'reserved' => $reserved,
                'available' => max($total - $reserved, 0),
            ];

            $overallTotal += $total;
            $overallReserved += $reserved;
        }

        return static::$inventorySummaryCache[$cacheKey] = [
            'date' => $date,
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
        ];
    }
}
