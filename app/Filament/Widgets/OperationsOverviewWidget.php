<?php

namespace App\Filament\Widgets;

use App\Models\RegistrationRequest;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Core\Models\Order;
use Modules\Support\Models\SupportTicket;

class OperationsOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected function getStats(): array
    {
        $pendingPayments = Order::query()
            ->whereIn('status', ['pending_payment', 'checkout_pending']);

        $pendingRegistrations = RegistrationRequest::query()
            ->whereIn('status', [
                RegistrationRequest::STATUS_PENDING_REVIEW,
                RegistrationRequest::STATUS_PENDING_FINAL_APPROVAL,
            ]);

        $certificateQueue = CertificateRequest::query()
            ->whereIn('status', [
                CertificateRequest::STATUS_PENDING_PAYMENT,
                CertificateRequest::STATUS_PAID_SUCCESSFULLY,
                CertificateRequest::STATUS_PROCESSING,
            ]);

        $openSupportTickets = SupportTicket::query()
            ->where('status', 'pending');

        return [
            Stat::make(__('Pending payments'), $this->formatNumber((clone $pendingPayments)->count()))
                ->description(__('EGP :amount waiting for collection', [
                    'amount' => $this->formatMoney((clone $pendingPayments)->sum('amount')),
                ]))
                ->descriptionIcon(Heroicon::Clock)
                ->icon(Heroicon::CreditCard)
                ->color('warning'),
            Stat::make(__('Registration queue'), $this->formatNumber((clone $pendingRegistrations)->count()))
                ->description(__(':count approved requests', [
                    'count' => $this->formatNumber(
                        RegistrationRequest::query()
                            ->where('status', RegistrationRequest::STATUS_APPROVED)
                            ->count(),
                    ),
                ]))
                ->descriptionIcon(Heroicon::Identification)
                ->icon(Heroicon::ClipboardDocumentList)
                ->color('info'),
            Stat::make(__('Certificate requests'), $this->formatNumber((clone $certificateQueue)->count()))
                ->description(__(':count completed requests', [
                    'count' => $this->formatNumber(
                        CertificateRequest::query()
                            ->where('status', CertificateRequest::STATUS_COMPLETED)
                            ->count(),
                    ),
                ]))
                ->descriptionIcon(Heroicon::DocumentCheck)
                ->icon(Heroicon::QueueList)
                ->color('primary'),
            Stat::make(__('Open support tickets'), $this->formatNumber((clone $openSupportTickets)->count()))
                ->description(__(':count solved tickets', [
                    'count' => $this->formatNumber(
                        SupportTicket::query()
                            ->where('status', 'solved')
                            ->count(),
                    ),
                ]))
                ->descriptionIcon(Heroicon::Lifebuoy)
                ->icon(Heroicon::ChatBubbleLeftRight)
                ->color('danger'),
        ];
    }

    protected function getHeading(): ?string
    {
        return __('Operations overview');
    }

    protected function getDescription(): ?string
    {
        return __('Requests and payments that need follow-up.');
    }

    private function formatMoney(float|int|string|null $value): string
    {
        return number_format((float) $value, 2);
    }

    private function formatNumber(float|int|string|null $value): string
    {
        return number_format((float) $value, 0);
    }
}
