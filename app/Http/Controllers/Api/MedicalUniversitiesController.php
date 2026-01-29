<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Core\Models\MedicalUniversity;
use Modules\Core\Resources\MedicalUniversityResource;

class MedicalUniversitiesController extends Controller
{
    public function index()
    {
        return MedicalUniversityResource::collection(MedicalUniversity::orderBy('id')->get());
    }
}
