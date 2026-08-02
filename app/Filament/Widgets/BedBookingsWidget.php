<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\RestUnitBookings\RestUnitBookingResource;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Services\Models\RestUnitBooking;

class BedBookingsWidget extends BaseWidget
{
    public ?int $bedId = null;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Bookings'))
            ->query(
                RestUnitBooking::query()
                    ->with('user:id,name,phone')
                    ->where('rest_unit_bed_id', $this->bedId ?? 0)
            )
            ->defaultSort('start_date', 'desc')
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('id')->label(__('Booking ID'))->sortable(),
                TextColumn::make('guest')
                    ->label(__('Guest'))
                    ->state(fn (RestUnitBooking $record): ?string => $record->guestName())
                    ->searchable(query: fn ($query, string $search) => $query
                        ->where('beneficiary_name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))),
                TextColumn::make('beneficiary_type')
                    ->label(__('Beneficiary'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => RestUnitBooking::beneficiaryTypeOptions()[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => $state === RestUnitBooking::BENEFICIARY_MARTYR_FAMILY ? 'warning' : 'gray'),
                TextColumn::make('total_price')->label(__('Total Amount'))->money('EGP')->sortable(),
                TextColumn::make('start_date')->label(__('Start Date'))->date()->sortable(),
                TextColumn::make('end_date')->label(__('End Date'))->date()->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => RestUnitBookingResource::getStatusLabel($state))
                    ->color(fn (?string $state): string => RestUnitBookingResource::getStatusColor($state)),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(RestUnitBookingResource::statusOptions()),
                SelectFilter::make('beneficiary_type')
                    ->label(__('Beneficiary'))
                    ->options(RestUnitBooking::beneficiaryTypeOptions()),
                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('from_date')->label(__('From Date'))->native(false)->displayFormat('d-m-Y'),
                        DatePicker::make('to_date')->label(__('To Date'))->native(false)->displayFormat('d-m-Y'),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from_date'] ?? null, fn ($q, $date) => $q->where('end_date', '>=', $date))
                        ->when($data['to_date'] ?? null, fn ($q, $date) => $q->where('start_date', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from_date'] ?? null) {
                            $indicators[] = __('From: :date', ['date' => $data['from_date']]);
                        }
                        if ($data['to_date'] ?? null) {
                            $indicators[] = __('To: :date', ['date' => $data['to_date']]);
                        }

                        return $indicators;
                    }),
            ])
            ->emptyStateHeading(__('No bookings for this bed.'));
    }
}
