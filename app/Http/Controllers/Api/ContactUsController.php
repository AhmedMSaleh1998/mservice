<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactInfo;
use Illuminate\Http\JsonResponse;

class ContactUsController extends Controller
{
    public function show(): JsonResponse
    {
        $contact = ContactInfo::query()->first();
        $address = null;

        if ($contact && filled($contact->address)) {
            $address = $contact->getTranslation('address', app()->getLocale());
        }

        return response()->json([
            'status' => 200,
            'data' => [
                'address' => $address ?? config('contact.address'),
                'email' => $contact?->email ?? config('contact.email'),
                'phones' => $contact?->phones ?? config('contact.phones') ?? [],
                'whatsapp' => $contact?->whatsapp ?? config('contact.whatsapp'),
                'fax' => $contact?->fax ?? config('contact.fax'),
            ],
        ]);
    }
}
