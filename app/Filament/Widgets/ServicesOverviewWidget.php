<?php

namespace App\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Ads\Models\AdRequest;
use Modules\Ads\Models\AdSpace;
use Modules\Services\Models\RestUnit;
use Modules\Services\Models\RestUnitBooking;
use Modules\Services\Models\Service;

class ServicesOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected function getStats(): array
    {
        $activeServices = Service::query()->where('is_active', true);
        $activeRestUnits = RestUnit::query()->where('is_active', true);
        $pendingBookings = RestUnitBooking::query()
            ->whereIn('status', [
                RestUnitBooking::STATUS_PENDING_PAYMENT,
                'checkout_pending',
            ]);
        $occupiedAdSpaces = AdSpace::query()->where('is_available', false);

        return [
            Stat::make(__('Active services'), $this->formatNumber((clone $activeServices)->count()))
                ->description(__(':count featured services', [
                    'count' => $this->formatNumber(
                        Service::query()
                            ->where('is_featured', true)
                            ->count(),
                    ),
                ]))
                ->descriptionIcon(Heroicon::CheckBadge)
                ->icon(Heroicon::Briefcase)
                ->color('success'),
            Stat::make(__('Rest units'), $this->formatNumber((clone $activeRestUnits)->count()))
                ->description(__(':count upcoming confirmed stays', [
                    'count' => $this->formatNumber(
                        RestUnitBooking::query()
                            ->where('status', RestUnitBooking::STATUS_PAID_SUCCESSFULLY)
                            ->whereDate('end_date', '>=', today())
                            ->count(),
                    ),
                ]))
                ->descriptionIcon(Heroicon::HomeModern)
                ->icon(Heroicon::BuildingOffice2)
                ->color('info'),
            Stat::make(__('Pending rest bookings'), $this->formatNumber((clone $pendingBookings)->count()))
                ->description(__(':count paid bookings this month', [
                    'count' => $this->formatNumber(
                        RestUnitBooking::query()
                            ->where('status', RestUnitBooking::STATUS_PAID_SUCCESSFULLY)
                            ->whereMonth('created_at', now()->month)
                            ->whereYear('created_at', now()->year)
                            ->count(),
                    ),
                ]))
                ->descriptionIcon(Heroicon::CalendarDays)
                ->icon(Heroicon::QueueList)
                ->color('warning'),
            Stat::make(__('Occupied ad spaces'), $this->formatNumber((clone $occupiedAdSpaces)->count()))
                ->description(__(':count approved ads live now', [
                    'count' => $this->formatNumber(
                        AdRequest::query()
                            ->where('status', 'approved')
                            ->count(),
                    ),
                ]))
                ->descriptionIcon(Heroicon::Megaphone)
                ->icon(Heroicon::ChartBarSquare)
                ->color('primary'),
        ];
    }

    protected function getHeading(): ?string
    {
        return __('Services snapshot');
    }

    protected function getDescription(): ?string
    {
        return __('Live activity across services, rest units, and ads.');
    }

    private function formatNumber(float|int|string|null $value): string
    {
        return number_format((float) $value, 0);
    }
}
