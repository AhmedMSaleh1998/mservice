<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Core\Models\Grade;
use Modules\Core\Resources\GradeResource;

class GradesController extends Controller
{
    public function index()
    {
        return GradeResource::collection(Grade::orderBy('id')->get());
    }
}
