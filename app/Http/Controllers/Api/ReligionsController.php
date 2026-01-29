<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Core\Models\Religion;
use Modules\Core\Resources\ReligionResource;

class ReligionsController extends Controller
{
    public function index()
    {
        return ReligionResource::collection(Religion::orderBy('id')->get());
    }
}
