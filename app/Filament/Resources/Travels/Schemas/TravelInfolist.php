<?php

namespace App\Filament\Resources\Travels\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Travels\Models\Travel;
use Modules\Travels\Models\TravelBooking;
use Modules\Travels\Models\TravelCategory;

class TravelInfolist
{
    private static array $summaryCache = [];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Travel'))
                    ->schema([
                        TextEntry::make('title')
                            ->label(__('Name'))
                            ->getStateUsing(fn (Travel $record): string => (string) $record->getTranslation('title', app()->getLocale())),
                        TextEntry::make('province_name')
                            ->label(__('Province'))
                            ->getStateUsing(fn (Travel $record): string => (string) $record->province?->getTranslation('name', app()->getLocale())),
                        TextEntry::make('start_date')
                            ->label(__('Start Date'))
                            ->date(),
                        TextEntry::make('end_date')
                            ->label(__('End Date'))
                            ->date(),
                        IconEntry::make('is_active')
                            ->label(__('Is Active'))
                            ->boolean()
                            ->trueColor('success')
                            ->falseColor('danger'),
                        TextEntry::make('location')
                            ->label(__('Location'))
                            ->getStateUsing(fn (Travel $record): ?string => filled($record->location) ? (string) $record->getTranslation('location', app()->getLocale()) : null)
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label(__('Updated At'))
                            ->dateTime(),
                        TextEntry::make('description')
                            ->label(__('Description'))
                            ->getStateUsing(fn (Travel $record): ?string => filled($record->description) ? (string) $record->getTranslation('description', app()->getLocale()) : null)
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
                Section::make(__('Capacity'))
                    ->schema([
                        TextEntry::make('total_seats')
                            ->label(__('Total Seats'))
                            ->getStateUsing(fn (Travel $record): int => static::summary($record)['total_seats']),
                        TextEntry::make('reserved_seats')
                            ->label(__('Reserved Seats'))
                            ->getStateUsing(fn (Travel $record): int => static::summary($record)['reserved_seats']),
                        TextEntry::make('available_seats')
                            ->label(__('Available Count'))
                            ->getStateUsing(fn (Travel $record): int => static::summary($record)['available_seats']),
                        TextEntry::make('bookings_count')
                            ->label(__('Bookings'))
                            ->getStateUsing(fn (Travel $record): int => static::summary($record)['bookings_count']),
                        TextEntry::make('participants_count')
                            ->label(__('Participants'))
                            ->getStateUsing(fn (Travel $record): int => static::summary($record)['participants_count']),
                        TextEntry::make('pending_bookings_count')
                            ->label(__('Pending Payment Bookings'))
                            ->getStateUsing(fn (Travel $record): int => static::summary($record)['pending_bookings_count']),
                        TextEntry::make('paid_bookings_count')
                            ->label(__('Paid Bookings'))
                            ->getStateUsing(fn (Travel $record): int => static::summary($record)['paid_bookings_count']),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
                Section::make(__('Categories'))
                    ->schema([
                        RepeatableEntry::make('category_summary')
                            ->label(__('Categories'))
                            ->hiddenLabel()
                            ->getStateUsing(fn (Travel $record): array => static::summary($record)['categories'])
                            ->table([
                                TableColumn::make(__('Name')),
                                TableColumn::make(__('Capacity')),
                                TableColumn::make(__('Reserved Seats')),
                                TableColumn::make(__('Available Count')),
                                TableColumn::make(__('Price')),
                            ])
                            ->schema([
                                TextEntry::make('name')
                                    ->hiddenLabel(),
                                TextEntry::make('capacity')
                                    ->hiddenLabel(),
                                TextEntry::make('reserved')
                                    ->hiddenLabel(),
                                TextEntry::make('available')
                                    ->hiddenLabel(),
                                TextEntry::make('price')
                                    ->hiddenLabel(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make(__('Travelers'))
                    ->schema([
                        TextEntry::make('travelers_notice')
                            ->hiddenLabel()
                            ->getStateUsing(fn (): string => __('No travel bookings yet.'))
                            ->visible(fn (Travel $record): bool => static::summary($record)['travelers'] === [])
                            ->columnSpanFull(),
                        RepeatableEntry::make('travelers')
                            ->label(__('Travelers'))
                            ->hiddenLabel()
                            ->getStateUsing(fn (Travel $record): array => static::summary($record)['travelers'])
                            ->visible(fn (Travel $record): bool => static::summary($record)['travelers'] !== [])
                            ->table([
                                TableColumn::make(__('Booking ID')),
                                TableColumn::make(__('User')),
                                TableColumn::make(__('Phone')),
                                TableColumn::make(__('Participants')),
                                TableColumn::make(__('Categories')),
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
                                TextEntry::make('participants_count')
                                    ->hiddenLabel(),
                                TextEntry::make('categories_summary')
                                    ->hiddenLabel()
                                    ->placeholder('-'),
                                TextEntry::make('status_label')
                                    ->hiddenLabel(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    private static function summary(Travel $record): array
    {
        $cacheKey = sprintf('%s:%s', spl_object_id($record), app()->getLocale());

        if (array_key_exists($cacheKey, static::$summaryCache)) {
            return static::$summaryCache[$cacheKey];
        }

        $categories = $record->categories()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $bookings = $record->bookings()
            ->with([
                'user:id,name,phone',
                'items:id,travel_booking_id,travel_category_id,category_code,category_name,quantity',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (TravelBooking $booking): bool => static::countsTowardManifest($booking))
            ->values();

        $reservedByCategory = [];

        foreach ($bookings as $booking) {
            foreach ($booking->items as $item) {
                $categoryId = (int) $item->travel_category_id;
                $reservedByCategory[$categoryId] = (int) ($reservedByCategory[$categoryId] ?? 0) + max((int) $item->quantity, 0);
            }
        }

        $categoryRows = $categories
            ->map(function (TravelCategory $category) use ($reservedByCategory): array {
                $reserved = (int) ($reservedByCategory[$category->id] ?? 0);
                $capacity = max((int) $category->capacity, 0);

                return [
                    'name' => (string) $category->getTranslation('name', app()->getLocale()),
                    'capacity' => $capacity,
                    'reserved' => $reserved,
                    'available' => max($capacity - $reserved, 0),
                    'price' => number_format((float) $category->price, 2, '.', ''),
                ];
            })
            ->values();

        return static::$summaryCache[$cacheKey] = [
            'total_seats' => $categoryRows->sum('capacity'),
            'reserved_seats' => $categoryRows->sum('reserved'),
            'available_seats' => $categoryRows->sum('available'),
            'bookings_count' => $bookings->count(),
            'participants_count' => $bookings->sum(fn (TravelBooking $booking): int => max((int) $booking->participants_count, 0)),
            'pending_bookings_count' => $bookings->where('status', TravelBooking::STATUS_PENDING_PAYMENT)->count(),
            'paid_bookings_count' => $bookings->where('status', TravelBooking::STATUS_PAID_SUCCESSFULLY)->count(),
            'categories' => $categoryRows->all(),
            'travelers' => $bookings
                ->map(fn (TravelBooking $booking): array => [
                    'booking_id' => $booking->id,
                    'user_name' => $booking->user?->name,
                    'user_phone' => $booking->user?->phone,
                    'participants_count' => max((int) $booking->participants_count, 0),
                    'categories_summary' => $booking->items
                        ->map(fn ($item): string => trim(sprintf(
                            '%s x%d',
                            (string) ($item->category_name ?: $item->category_code),
                            max((int) $item->quantity, 0),
                        )))
                        ->filter()
                        ->implode(', '),
                    'status_label' => static::bookingStatusLabel($booking->status),
                ])
                ->all(),
        ];
    }

    private static function countsTowardManifest(TravelBooking $booking): bool
    {
        if (! TravelBooking::blocksInventoryStatus($booking->status)) {
            return false;
        }

        if ($booking->status !== TravelBooking::STATUS_PENDING_PAYMENT) {
            return true;
        }

        return optional($booking->created_at)->gt(now()->subMinutes(static::reservationTimeoutMinutes())) ?? false;
    }

    private static function reservationTimeoutMinutes(): int
    {
        $minutes = (int) config('checkout.reservation_timeout_minutes', 5);

        return $minutes > 0 ? $minutes : 5;
    }

    private static function bookingStatusLabel(?string $status): string
    {
        return match ($status) {
            TravelBooking::STATUS_PENDING_PAYMENT => __('Pending Payment'),
            TravelBooking::STATUS_PAID_SUCCESSFULLY => __('Paid Successfully'),
            TravelBooking::STATUS_PAYMENT_EXPIRED => __('Payment Expired'),
            TravelBooking::STATUS_CANCELLED => __('Cancelled'),
            default => (string) $status,
        };
    }
}
