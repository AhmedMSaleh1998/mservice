<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Courses\Models\CourseBooking;
use Modules\Courses\Services\MyCoursesService;

class MyCoursesController extends Controller
{
    public function __construct(
        private readonly MyCoursesService $myCoursesService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $courses = $this->myCoursesService->listForUser(
            $request->user(),
            $request->only(['search', 'type', 'page', 'per_page'])
        );

        return response()->json([
            'message' => 'My courses loaded successfully.',
            'status' => 200,
            'data' => [
                'items' => $courses->items(),
                'filters' => [
                    'types' => $this->myCoursesService->typeFilters(),
                ],
                'pagination' => [
                    'current_page' => $courses->currentPage(),
                    'last_page' => $courses->lastPage(),
                    'per_page' => $courses->perPage(),
                    'total' => $courses->total(),
                ],
            ],
        ]);
    }

    public function show(Request $request, CourseBooking $courseBooking): JsonResponse
    {
        $this->ensureAccessiblePaidBooking($request, $courseBooking);

        return response()->json([
            'message' => 'My course loaded successfully.',
            'status' => 200,
            'data' => $this->myCoursesService->detailForBooking($courseBooking),
        ]);
    }

    private function ensureAccessiblePaidBooking(Request $request, CourseBooking $courseBooking): void
    {
        if ($courseBooking->user_id !== $request->user()?->id) {
            throw new HttpResponseException(response()->json([
                'message' => 'This course booking does not belong to the authenticated user.',
                'status' => 403,
            ], 403));
        }

        if ($courseBooking->status !== 'paid_successfully') {
            throw new HttpResponseException(response()->json([
                'message' => 'This course booking is not available yet.',
                'status' => 422,
            ], 422));
        }
    }
}
