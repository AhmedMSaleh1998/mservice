<?php

namespace App\Filament\Resources\Transactions\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Core\Models\Order;

class TransactionsStatsWidget extends StatsOverviewWidget
{
    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $paid = Order::query()->where('status', 'paid_successfully');

        $paidToday = Order::query()
            ->where('status', 'paid_successfully')
            ->whereDate('paid_at', today());

        $pending = Order::query()
            ->whereIn('status', ['pending_payment', 'checkout_pending']);

        // Same definition the "Sync all with Oracle" header action uses.
        $awaitingOracleSync = Order::query()
            ->where('status', 'paid_successfully')
            ->where(function ($query): void {
                $query->whereNull('payload->oracle_payment_sync->status')
                    ->orWhere('payload->oracle_payment_sync->status', '!=', 'success');
            });

        return [
            Stat::make(__('Paid transactions'), $this->formatNumber((clone $paid)->count()))
                ->description(__('EGP :amount collected', [
                    'amount' => $this->formatMoney((clone $paid)->sum('amount')),
                ]))
                ->descriptionIcon(Heroicon::Banknotes)
                ->icon(Heroicon::CheckCircle)
                ->color('success'),
            Stat::make(__('Paid today'), $this->formatNumber((clone $paidToday)->count()))
                ->description(__('EGP :amount collected', [
                    'amount' => $this->formatMoney((clone $paidToday)->sum('amount')),
                ]))
                ->descriptionIcon(Heroicon::Banknotes)
                ->icon(Heroicon::CalendarDays)
                ->color('info'),
            Stat::make(__('Pending payments'), $this->formatNumber((clone $pending)->count()))
                ->description(__('EGP :amount waiting for collection', [
                    'amount' => $this->formatMoney((clone $pending)->sum('amount')),
                ]))
                ->descriptionIcon(Heroicon::Clock)
                ->icon(Heroicon::CreditCard)
                ->color('warning'),
            Stat::make(__('Awaiting Oracle sync'), $this->formatNumber((clone $awaitingOracleSync)->count()))
                ->description(__('Paid but not synced to Oracle yet'))
                ->descriptionIcon(Heroicon::ExclamationTriangle)
                ->icon(Heroicon::ArrowPath)
                ->color('danger'),
        ];
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
