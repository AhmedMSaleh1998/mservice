<?php

namespace App\Http\Middleware;

use App\Services\DoctorSituationService;
use Closure;
use Illuminate\Http\Request;
use Modules\Users\Models\User;
use Symfony\Component\HttpFoundation\Response;

class EnsureDoctorHasNoSituation
{
    public function __construct(
        private readonly DoctorSituationService $doctorSituationService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $this->doctorSituationService->userHasSituation($user)) {
            return response()->json([
                'message' => $this->doctorSituationService->blockedMessage(),
                'status' => 403,
            ], 403);
        }

        return $next($request);
    }
}
