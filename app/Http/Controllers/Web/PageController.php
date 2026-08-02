<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Pages\Models\Page;

class PageController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $locale = in_array($request->query('lang'), ['ar', 'en'], true)
            ? $request->query('lang')
            : 'ar';

        app()->setLocale($locale);

        $page = Page::query()->where('slug', $slug)->first();

        abort_if(! $page, 404);

        return view('pages.show', [
            'page' => $page,
            'locale' => $locale,
        ]);
    }
}
