<?php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Services\Models\RestUnitBedMaintenanceLog;

class BedMaintenanceLogWidget extends BaseWidget
{
    public ?int $bedId = null;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Maintenance log'))
            ->query(
                RestUnitBedMaintenanceLog::query()
                    ->where('rest_unit_bed_id', $this->bedId ?? 0)
            )
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('action')
                    ->label(__('Action'))
                    ->badge()
                    ->formatStateUsing(fn (RestUnitBedMaintenanceLog $record): string => $record->actionLabel())
                    ->color(fn (?string $state): string => $state === RestUnitBedMaintenanceLog::ACTION_TO_MAINTENANCE ? 'warning' : 'success'),
                TextColumn::make('note')->label(__('Maintenance note'))->placeholder('—')->wrap(),
                TextColumn::make('created_at')->label(__('Date'))->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label(__('Action'))
                    ->options([
                        RestUnitBedMaintenanceLog::ACTION_TO_MAINTENANCE => __('Sent to maintenance'),
                        RestUnitBedMaintenanceLog::ACTION_RETURNED => __('Returned to service'),
                    ]),
            ])
            ->emptyStateHeading(__('No maintenance history.'));
    }
}
