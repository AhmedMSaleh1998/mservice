<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Modules\Ads\Models\AdRequest;

class AdRequestStatusChartWidget extends ChartWidget
{
    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected ?string $maxHeight = '300px';

    public function getHeading(): ?string
    {
        return __('Ad request status breakdown');
    }

    public function getDescription(): ?string
    {
        return __('Current status distribution of ad requests.');
    }

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => __('Ad Requests'),
                    'data' => [
                        AdRequest::query()->where('status', 'pending_payment')->count(),
                        AdRequest::query()->where('status', 'checkout_pending')->count(),
                        AdRequest::query()->where('status', 'paid_successfully')->count(),
                        AdRequest::query()->where('status', 'approved')->count(),
                        AdRequest::query()->where('status', 'completed')->count(),
                        AdRequest::query()->where('status', 'payment_expired')->count(),
                        AdRequest::query()->where('status', 'cancelled')->count(),
                        AdRequest::query()->where('status', 'rejected')->count(),
                    ],
                    'backgroundColor' => [
                        '#f59e0b',
                        '#2563eb',
                        '#8b5cf6',
                        '#059669',
                        '#14b8a6',
                        '#6b7280',
                        '#ef4444',
                        '#991b1b',
                    ],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => [
                __('Pending Payment'),
                __('Checkout Pending'),
                __('Paid Successfully'),
                __('Approved'),
                __('Completed'),
                __('Payment Expired'),
                __('Cancelled'),
                __('Rejected'),
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
