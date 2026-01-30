<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Core\Models\PaymentMethod;
use Modules\Core\Resources\PaymentMethodResource;

class PaymentMethodsController extends Controller
{
    public function index()
    {
        return PaymentMethodResource::collection(
            PaymentMethod::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get()
        );
    }
}
