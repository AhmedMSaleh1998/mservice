<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Core\Models\Banner;
use Modules\Core\Resources\BannerResource;

class BannersController extends Controller
{
    public function index()
    {
        $banners = Banner::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();

        return BannerResource::collection($banners);
    }
}
