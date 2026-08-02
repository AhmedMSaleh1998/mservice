<?php

namespace App\Filament\Resources\Courses\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseBooking;

class CourseInfolist
{
    private static array $summaryCache = [];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Course'))
                    ->schema([
                        TextEntry::make('title')
                            ->label(__('Title'))
                            ->getStateUsing(fn (Course $record): string => (string) $record->getTranslation('title', app()->getLocale())),
                        TextEntry::make('type')
                            ->label(__('Type'))
                            ->getStateUsing(fn (Course $record): string => static::courseTypeLabel($record->type)),
                        TextEntry::make('start_date')
                            ->label(__('Start Date'))
                            ->date(),
                        TextEntry::make('end_date')
                            ->label(__('End Date'))
                            ->date(),
                        TextEntry::make('price')
                            ->label(__('Price'))
                            ->money('EGP'),
                        IconEntry::make('is_active')
                            ->label(__('Is Active'))
                            ->boolean()
                            ->trueColor('success')
                            ->falseColor('danger'),
                        IconEntry::make('is_featured')
                            ->label(__('Is Featured'))
                            ->boolean()
                            ->trueColor('success')
                            ->falseColor('danger'),
                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label(__('Updated At'))
                            ->dateTime(),
                        TextEntry::make('description')
                            ->label(__('Description'))
                            ->getStateUsing(fn (Course $record): ?string => filled($record->description) ? (string) $record->getTranslation('description', app()->getLocale()) : null)
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
                Section::make(__('Capacity'))
                    ->schema([
                        TextEntry::make('total_seats')
                            ->label(__('Total Seats'))
                            ->getStateUsing(fn (Course $record): int => static::summary($record)['total_seats']),
                        TextEntry::make('reserved_seats')
                            ->label(__('Reserved Seats'))
                            ->getStateUsing(fn (Course $record): int => static::summary($record)['reserved_seats']),
                        TextEntry::make('available_seats')
                            ->label(__('Available Count'))
                            ->getStateUsing(fn (Course $record): int => static::summary($record)['available_seats']),
                        TextEntry::make('bookings_count')
                            ->label(__('Bookings'))
                            ->getStateUsing(fn (Course $record): int => static::summary($record)['bookings_count']),
                        TextEntry::make('pending_bookings_count')
                            ->label(__('Pending Payment Bookings'))
                            ->getStateUsing(fn (Course $record): int => static::summary($record)['pending_bookings_count']),
                        TextEntry::make('paid_bookings_count')
                            ->label(__('Paid Bookings'))
                            ->getStateUsing(fn (Course $record): int => static::summary($record)['paid_bookings_count']),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('Bookings'))
                    ->schema([
                        TextEntry::make('course_bookings_notice')
                            ->hiddenLabel()
                            ->getStateUsing(fn (): string => __('No course bookings yet.'))
                            ->visible(fn (Course $record): bool => static::summary($record)['attendees'] === [])
                            ->columnSpanFull(),
                        RepeatableEntry::make('attendees')
                            ->label(__('Bookings'))
                            ->hiddenLabel()
                            ->getStateUsing(fn (Course $record): array => static::summary($record)['attendees'])
                            ->visible(fn (Course $record): bool => static::summary($record)['attendees'] !== [])
                            ->table([
                                TableColumn::make(__('Booking ID')),
                                TableColumn::make(__('User')),
                                TableColumn::make(__('Phone')),
                                TableColumn::make(__('Email')),
                                TableColumn::make(__('Status')),
                                TableColumn::make(__('Created At')),
                                TableColumn::make(__('Paid At')),
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
                                TextEntry::make('user_email')
                                    ->hiddenLabel()
                                    ->placeholder('-'),
                                TextEntry::make('status_label')
                                    ->hiddenLabel(),
                                TextEntry::make('created_at')
                                    ->hiddenLabel()
                                    ->placeholder('-'),
                                TextEntry::make('paid_at')
                                    ->hiddenLabel()
                                    ->placeholder('-'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    private static function summary(Course $record): array
    {
        $cacheKey = sprintf('%s:%s', spl_object_id($record), app()->getLocale());

        if (array_key_exists($cacheKey, static::$summaryCache)) {
            return static::$summaryCache[$cacheKey];
        }

        $bookings = CourseBooking::query()
            ->with('user:id,name,phone,email')
            ->where('course_id', $record->id)
            ->whereNotIn('status', ['payment_expired', 'cancelled'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $reservedSeats = $bookings->count();
        $availableSeats = max((int) $record->available_count, 0);

        return static::$summaryCache[$cacheKey] = [
            'total_seats' => $availableSeats + $reservedSeats,
            'reserved_seats' => $reservedSeats,
            'available_seats' => $availableSeats,
            'bookings_count' => $reservedSeats,
            'pending_bookings_count' => $bookings->where('status', 'pending_payment')->count(),
            'paid_bookings_count' => $bookings->where('status', 'paid_successfully')->count(),
            'attendees' => $bookings
                ->map(fn (CourseBooking $booking): array => [
                    'booking_id' => $booking->id,
                    'user_name' => $booking->user?->name,
                    'user_phone' => $booking->user?->phone,
                    'user_email' => $booking->user?->email,
                    'status_label' => static::bookingStatusLabel($booking->status),
                    'created_at' => optional($booking->created_at)->format('Y-m-d H:i'),
                    'paid_at' => optional($booking->paid_at)->format('Y-m-d H:i'),
                ])
                ->all(),
        ];
    }

    private static function courseTypeLabel(?string $value): string
    {
        return match ($value) {
            'attend' => __('Attend'),
            'online' => __('Online'),
            'hybrid' => __('Hybrid'),
            default => (string) $value,
        };
    }

    private static function bookingStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending_payment' => __('Pending Payment'),
            'paid_successfully' => __('Paid Successfully'),
            'payment_expired' => __('Payment Expired'),
            'cancelled' => __('Cancelled'),
            default => (string) $status,
        };
    }
}
