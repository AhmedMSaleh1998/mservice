<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Models\Province;
use Modules\Core\Resources\ProvinceResource;

class ProvincesController extends Controller
{
    public function index()
    {
        return ProvinceResource::collection(Province::orderBy('id')->get());
    }
}
