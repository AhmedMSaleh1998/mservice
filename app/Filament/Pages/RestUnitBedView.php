<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BedBookingsWidget;
use App\Filament\Widgets\BedMaintenanceLogWidget;
use Filament\Pages\Page;
use Filament\Panel;
use Modules\Services\Models\RestUnitBed;

class RestUnitBedView extends Page
{
    protected string $view = 'filament.pages.rest-unit-bed-view';

    protected static bool $shouldRegisterNavigation = false;

    public RestUnitBed $bed;

    public static function getRoutePath(Panel $panel): string
    {
        return '/rest-unit-beds/{record}';
    }

    public function mount(int|string $record): void
    {
        $this->bed = RestUnitBed::query()->with('restUnit')->findOrFail($record);
    }

    public function getTitle(): string
    {
        return (string) $this->bed->label;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            BedBookingsWidget::class,
            BedMaintenanceLogWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getWidgetData(): array
    {
        return [
            'bedId' => $this->bed->getKey(),
        ];
    }
}
