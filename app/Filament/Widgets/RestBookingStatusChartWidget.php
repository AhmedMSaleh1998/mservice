<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Modules\Services\Models\RestUnitBooking;

class RestBookingStatusChartWidget extends ChartWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected ?string $maxHeight = '300px';

    public function getHeading(): ?string
    {
        return __('Rest booking status breakdown');
    }

    public function getDescription(): ?string
    {
        return __('Current status distribution of rest unit bookings.');
    }

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => __('Rest Unit Bookings'),
                    'data' => [
                        RestUnitBooking::query()->where('status', RestUnitBooking::STATUS_PENDING_PAYMENT)->count(),
                        RestUnitBooking::query()->where('status', 'checkout_pending')->count(),
                        RestUnitBooking::query()->where('status', RestUnitBooking::STATUS_PAID_SUCCESSFULLY)->count(),
                        RestUnitBooking::query()->where('status', RestUnitBooking::STATUS_PAYMENT_EXPIRED)->count(),
                        RestUnitBooking::query()->where('status', RestUnitBooking::STATUS_CANCELLED)->count(),
                    ],
                    'backgroundColor' => [
                        '#f59e0b',
                        '#2563eb',
                        '#059669',
                        '#6b7280',
                        '#dc2626',
                    ],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => [
                __('Pending Payment'),
                __('Checkout Pending'),
                __('Paid Successfully'),
                __('Payment Expired'),
                __('Cancelled'),
            ],
        ];
    }

    protected function getOptions(): ?array
    {
        return [
            'cutout' => '65%',
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
