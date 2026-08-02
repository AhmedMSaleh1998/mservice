<?php

namespace App\Filament\Widgets;

use App\Models\RegistrationRequest;
use Filament\Widgets\ChartWidget;

class RegistrationStatusChartWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected ?string $maxHeight = '300px';

    public function getHeading(): ?string
    {
        return __('Registration status breakdown');
    }

    public function getDescription(): ?string
    {
        return __('Current status distribution of registration requests.');
    }

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => __('Registration Requests'),
                    'data' => [
                        RegistrationRequest::query()->where('status', RegistrationRequest::STATUS_PENDING_REVIEW)->count(),
                        RegistrationRequest::query()->where('status', RegistrationRequest::STATUS_PENDING_FINAL_APPROVAL)->count(),
                        RegistrationRequest::query()->where('status', RegistrationRequest::STATUS_APPROVED)->count(),
                    ],
                    'backgroundColor' => [
                        '#f59e0b',
                        '#2563eb',
                        '#059669',
                    ],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => [
                __('Pending'),
                __('Pending Final Approval'),
                __('Approved'),
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
