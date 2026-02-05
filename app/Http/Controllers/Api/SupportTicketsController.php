<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSupportTicketRequest;
use Illuminate\Http\JsonResponse;
use Modules\Support\Models\SupportTicket;
use Modules\Support\Resources\SupportTicketResource;

class SupportTicketsController extends Controller
{
    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        $data = $request->validated();

        $ticket = SupportTicket::create([
            'user_id' => auth()->id(),
            'title' => $data['title'],
            'description' => $data['description'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Support ticket created successfully.',
            'status' => 200,
            'data' => new SupportTicketResource($ticket),
        ], 201);
    }
}
