<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Modules\Certificates\Models\CertificateRequest;

class CertificateStatusChartWidget extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected ?string $maxHeight = '300px';

    public function getHeading(): ?string
    {
        return __('Certificate status breakdown');
    }

    public function getDescription(): ?string
    {
        return __('Current status distribution of certificate requests.');
    }

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => __('Certificate Requests'),
                    'data' => [
                        CertificateRequest::query()->where('status', CertificateRequest::STATUS_PENDING_PAYMENT)->count(),
                        CertificateRequest::query()->where('status', CertificateRequest::STATUS_PAID_SUCCESSFULLY)->count(),
                        CertificateRequest::query()->where('status', CertificateRequest::STATUS_PROCESSING)->count(),
                        CertificateRequest::query()->where('status', CertificateRequest::STATUS_COMPLETED)->count(),
                        CertificateRequest::query()->where('status', CertificateRequest::STATUS_CANCELLED)->count(),
                    ],
                    'backgroundColor' => [
                        '#f59e0b',
                        '#2563eb',
                        '#8b5cf6',
                        '#059669',
                        '#dc2626',
                    ],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => [
                __('Pending Payment'),
                __('Paid Successfully'),
                __('Processing'),
                __('Completed'),
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
