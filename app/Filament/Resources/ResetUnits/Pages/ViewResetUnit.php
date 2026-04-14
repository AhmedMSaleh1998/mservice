<?php

namespace App\Filament\Resources\ResetUnits\Pages;

use App\Filament\Resources\ResetUnits\ResetUnitResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;

class ViewResetUnit extends ViewRecord
{
    protected static string $resource = ResetUnitResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('checkAvailability')
                ->label(__('Check Availability'))
                ->icon('heroicon-o-calendar-days')
                ->schema([
                    DatePicker::make('from_date')
                        ->label(__('From Date'))
                        ->required(),
                    DatePicker::make('to_date')
                        ->label(__('To Date'))
                        ->required(),
                ])
                ->fillForm(fn (): array => [
                    'from_date' => $this->selectedFromDate(),
                    'to_date' => $this->selectedToDate(),
                ])
                ->action(function (array $data): void {
                    $fromDate = (string) ($data['from_date'] ?? $this->selectedFromDate());
                    $toDate = (string) ($data['to_date'] ?? $this->selectedToDate());

                    if ($toDate < $fromDate) {
                        $toDate = $fromDate;
                    }

                    $this->redirect(
                        static::getResource()::getUrl('view', [
                            'record' => $this->record,
                            'from_date' => $fromDate,
                            'to_date' => $toDate,
                        ]),
                        navigate: true,
                    );
                }),
            Action::make('resetAvailabilityPeriod')
                ->label(__('Reset Period'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => request()->filled('from_date') || request()->filled('to_date'))
                ->action(fn (): mixed => $this->redirect(
                    static::getResource()::getUrl('view', ['record' => $this->record]),
                    navigate: true,
                )),
        ];
    }

    private function selectedFromDate(): string
    {
        return (string) request()->query('from_date', now()->toDateString());
    }

    private function selectedToDate(): string
    {
        return (string) request()->query('to_date', $this->selectedFromDate());
    }
}
