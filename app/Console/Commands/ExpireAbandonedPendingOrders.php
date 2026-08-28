<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Core\Models\Order;
use Modules\Memberships\Models\MembershipRequest;

class ExpireAbandonedPendingOrders extends Command
{
    /**
     * Two days with no gateway session means abandoned: without a merchant
     * reference the member never reached Fawry, so no payment can be in
     * flight and a blind local expiry is safe.
     */
    private const ABANDONED_AFTER_DAYS = 2;

    protected $signature = 'payments:expire-abandoned-orders';

    protected $description = 'Expire membership/certificate orders stuck in pending_payment with no gateway session for 2+ days';

    public function handle(): int
    {
        $expired = 0;

        Order::query()
            ->whereHasMorph('orderable', [MembershipRequest::class, CertificateRequest::class])
            ->where('status', 'pending_payment')
            ->whereNull('merchant_ref_num')
            ->where('created_at', '<=', now()->subDays(self::ABANDONED_AFTER_DAYS))
            ->chunkById(200, function (Collection $orders) use (&$expired): void {
                foreach ($orders as $order) {
                    if ($this->expireOrder($order)) {
                        $expired++;
                    }
                }
            });

        $this->info(sprintf('Expired %d abandoned pending order(s).', $expired));

        return self::SUCCESS;
    }

    private function expireOrder(Order $order): bool
    {
        return DB::transaction(function () use ($order): bool {
            $locked = Order::query()->lockForUpdate()->find($order->id);

            // Re-check under the lock: the member may have just started a
            // checkout (merchant_ref_num set) or the order changed state.
            if (! $locked || $locked->status !== 'pending_payment' || $locked->merchant_ref_num !== null) {
                return false;
            }

            // Mirrors the terminal-expiry shape used by the booking release
            // commands so expired orders look the same everywhere.
            $locked->forceFill([
                'status' => 'payment_expired',
                'gateway_status' => 'EXPIRED',
                'checkout_url' => null,
                'payment_last_synced_at' => now(),
            ])->save();

            $orderable = $locked->orderable;

            if ($orderable && $orderable->status === 'pending_payment') {
                $orderable->status = 'payment_expired';
                $orderable->save();
            }

            return true;
        });
    }
}
