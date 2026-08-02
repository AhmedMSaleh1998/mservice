<?php

namespace App\Filament\Widgets;

use App\Models\RegistrationRequest;
use Filament\Widgets\ChartWidget;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Core\Models\Order;
use Modules\Services\Models\RestUnitBooking;
use Modules\Support\Models\SupportTicket;

class WorkloadBreakdownChartWidget extends ChartWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    public function getHeading(): ?string
    {
        return __('Current workload distribution');
    }

    public function getDescription(): ?string
    {
        return __('Queues and follow-ups that need attention right now.');
    }

    protected function getData(): array
    {
        $pendingPayments = Order::query()
            ->whereIn('status', ['pending_payment', 'checkout_pending'])
            ->count();

        $registrationQueue = RegistrationRequest::query()
            ->whereIn('status', [
                RegistrationRequest::STATUS_PENDING_REVIEW,
                RegistrationRequest::STATUS_PENDING_FINAL_APPROVAL,
            ])
            ->count();

        $certificateQueue = CertificateRequest::query()
            ->whereIn('status', [
                CertificateRequest::STATUS_PENDING_PAYMENT,
                CertificateRequest::STATUS_PAID_SUCCESSFULLY,
                CertificateRequest::STATUS_PROCESSING,
            ])
            ->count();

        $pendingRestBookings = RestUnitBooking::query()
            ->whereIn('status', [
                RestUnitBooking::STATUS_PENDING_PAYMENT,
                'checkout_pending',
            ])
            ->count();

        $openSupportTickets = SupportTicket::query()
            ->where('status', 'pending')
            ->count();

        return [
            'datasets' => [
                [
                    'label' => __('Current workload distribution'),
                    'data' => [
                        $pendingPayments,
                        $registrationQueue,
                        $certificateQueue,
                        $pendingRestBookings,
                        $openSupportTickets,
                    ],
                    'backgroundColor' => [
                        '#f59e0b',
                        '#2563eb',
                        '#059669',
                        '#8b5cf6',
                        '#dc2626',
                    ],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => [
                __('Pending payments'),
                __('Registration queue'),
                __('Certificate requests'),
                __('Pending rest bookings'),
                __('Open support tickets'),
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
