<?php

namespace Modules\Ads\Services;

use Illuminate\Support\Facades\DB;
use Modules\Ads\Models\AdRequest;
use Modules\Ads\Models\AdSpace;

class AdRequestService
{
    public function listApproved()
    {
        return AdRequest::query()
            ->approved()
            ->with(['adSpace', 'media'])
            ->latest()
            ->get();
    }

    public function create(array $data, int $userId): AdRequest
    {
        return DB::transaction(function () use ($data, $userId) {
            $adSpace = AdSpace::query()->findOrFail($data['ad_space_id']);
            $durationMonths = (int) $data['duration_months'];
            $pricePerMonth = (float) $adSpace->price_per_month;
            $totalAmount = $pricePerMonth * $durationMonths;

            $adRequest = AdRequest::create([
                'user_id' => $userId,
                'ad_space_id' => $adSpace->id,
                'duration_months' => $durationMonths,
                'price_per_month' => $pricePerMonth,
                'total_amount' => $totalAmount,
                'ad_text' => $data['ad_text'] ?? null,
                'design_image_path' => '',
                'status' => 'pending_payment',
                'payment_method' => $data['payment_method'] ?? null,
            ]);

            $file = $data['design_image'] ?? null;
            if ($file) {
                $adRequest
                    ->addMedia($file)
                    ->toMediaCollection('design_image');
            }

            return $adRequest->fresh(['adSpace', 'media']);
        });
    }

    public function markPaid(AdRequest $adRequest, string $paymentMethod): AdRequest
    {
        $adRequest->payment_method = $paymentMethod;
        $adRequest->status = 'paid_successfully';
        $adRequest->save();

        return $adRequest->fresh(['adSpace']);
    }
}
