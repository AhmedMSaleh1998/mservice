<?php

namespace App\Console\Commands;

use App\Services\Payments\FawryHostedCheckoutService;
use App\Services\Payments\FawryPaymentUpdateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\Order;
use Modules\Core\Services\OrderService;
use Throwable;

class ReconcileFawryPendingPayments extends Command
{
    protected $signature = 'payments:reconcile-fawry
        {--limit=100 : Maximum number of pending checkouts to reconcile per run}
        {--days=2 : How many days back to scan pending checkouts (raise for a one-off backlog cleanup)}';

    protected $description = 'Pull Fawry payment status for pending checkouts, apply missed payments (with Oracle sync) and expire stale ones';

    public function handle(
        FawryHostedCheckoutService $fawryHostedCheckoutService,
        FawryPaymentUpdateService $fawryPaymentUpdateService,
        OrderService $orderService,
    ): int {
        if (! $fawryHostedCheckoutService->isEnabled()) {
            $this->info('Fawry is disabled; nothing to reconcile.');

            return self::SUCCESS;
        }

        $orders = Order::query()
            ->with('orderable')
            ->where('payment_method', 'fawry')
            ->where('status', 'checkout_pending')
            ->whereNotNull('merchant_ref_num')
            // Give the webhook and return redirect a head start, and skip
            // checkouts old enough that only manual review makes sense.
            ->where('payment_started_at', '<=', now()->subMinutes(2))
            ->where('payment_started_at', '>=', now()->subDays(max(1, (int) $this->option('days'))))
            ->orderBy('payment_started_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        $applied = 0;
        $expired = 0;
        $stillPending = 0;

        foreach ($orders as $order) {
            try {
                $payload = $fawryHostedCheckoutService->pullPaymentStatus($order);

                if ($fawryHostedCheckoutService->isNoTransactionPayload($payload)) {
                    // Customer has not paid yet — expected, not an error. Close the
                    // checkout once its payment window has passed, otherwise leave it.
                    $result = $orderService->expireStaleFawryCheckout($order);
                    $result->status === 'checkout_pending' ? $stillPending++ : $expired++;

                    continue;
                }

                if (! $fawryHostedCheckoutService->verifyStatusSignature($payload)) {
                    Log::warning('Fawry reconciliation received payload with invalid signature.', [
                        'order_id' => $order->id,
                        'merchant_ref_num' => $order->merchant_ref_num,
                    ]);

                    continue;
                }

                // applyFawryPaymentUpdate marks the order paid and triggers the
                // Oracle payment sync through the same pipeline the return
                // redirect and webhook use.
                $fawryPaymentUpdateService->apply($order, $payload, 'reconciliation');
                $applied++;
            } catch (Throwable $exception) {
                // Transient gateway/network issues are expected here; the next
                // scheduled run retries automatically.
                Log::warning('Fawry reconciliation failed for order.', [
                    'order_id' => $order->id,
                    'merchant_ref_num' => $order->merchant_ref_num,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->info(sprintf(
            'Reconciled %d checkouts: %d updated from Fawry, %d expired locally, %d still pending.',
            $orders->count(),
            $applied,
            $expired,
            $stillPending,
        ));

        return self::SUCCESS;
    }
}
