<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Core\Models\Nationality;
use Modules\Core\Resources\NationalityResource;

class NationalitiesController extends Controller
{
    public function index()
    {
        return NationalityResource::collection(Nationality::orderBy('id')->get());
    }
}
