<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Filament\Resources\Transactions\Widgets\TransactionsStatsWidget;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Modules\Core\Models\Order;
use Modules\Core\Services\OrderService;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderWidgets(): array
    {
        return [
            TransactionsStatsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncAllOracle')
                ->label(__('Sync all with Oracle'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('Sync all with Oracle'))
                ->modalDescription(__('Sync all paid payments that have not been synced with Oracle yet.'))
                ->action(function (): void {
                    $service = app(OrderService::class);
                    $synced = 0;
                    $failed = 0;

                    // Only paid orders that were never synced successfully.
                    Order::query()
                        ->where('status', 'paid_successfully')
                        ->where(function ($query): void {
                            $query->whereNull('payload->oracle_payment_sync->status')
                                ->orWhere('payload->oracle_payment_sync->status', '!=', 'success');
                        })
                        ->with(['orderable', 'user'])
                        ->chunkById(100, function ($orders) use ($service, &$synced, &$failed): void {
                            foreach ($orders as $order) {
                                $result = $service->runOraclePaymentSync($order);

                                if (($result['status'] ?? null) === 'success') {
                                    $synced++;
                                } else {
                                    $failed++;
                                }
                            }
                        });

                    Notification::make()
                        ->title(__('Oracle sync finished.'))
                        ->body(__(':synced synced, :failed not synced.', ['synced' => $synced, 'failed' => $failed]))
                        ->success()
                        ->send();
                }),
        ];
    }
}
