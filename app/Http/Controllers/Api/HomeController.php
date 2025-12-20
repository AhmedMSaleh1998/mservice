<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Blog\Models\Blog;
use Modules\Blog\Resources\BlogResource;
use Modules\Services\Resources\ServicesResource;
use Modules\Services\Services\ServicesService;
use Modules\Users\Resources\NormalUserResource;

class HomeController extends Controller
{
    public function __construct(
        protected ServicesService $services
    )
    {
    }

    public function index()
    {
        $services = $this->services->getAllServices(6);

        $mainImages = [
            "placehold.co/600x400@2x.png",
            "placehold.co/600x400@2x.png",
            "placehold.co/600x400@2x.png",
        ];

        if (Auth::guard('sanctum')->check()) {
            $user = Auth::guard('sanctum')->user();
//            $featuredServices = $this->services->getFeaturedServices(6);
            return response()->json([
                'user' => NormalUserResource::make($user),
                'banner' => $mainImages,
                'services' => ServicesResource::collection($services),
                'news' => BlogResource::collection(Blog::latest()->limit(2)->get()),
            ]);
        }

        return response()->json([
            'user' => null,
            'banner' => $mainImages,
            'services' => ServicesResource::collection($services),
            'news' => BlogResource::collection(Blog::latest()->limit(2)->get()),
        ]);
    }
}
