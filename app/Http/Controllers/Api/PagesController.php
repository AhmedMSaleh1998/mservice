<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Pages\Models\Page;
use Modules\Pages\Resources\PageResource;

class PagesController extends Controller
{
    public function index()
    {
        return PageResource::collection(Page::query()->orderBy('slug')->get());
    }

    public function show(string $slug)
    {
        $page = Page::query()->where('slug', $slug)->first();

        abort_if(! $page, 404);

        return PageResource::make($page);
    }
}
