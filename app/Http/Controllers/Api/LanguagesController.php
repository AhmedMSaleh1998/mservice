<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Core\Models\Language;
use Modules\Core\Resources\LanguageResource;

class LanguagesController extends Controller
{
    public function index()
    {
        return LanguageResource::collection(Language::orderBy('id')->get());
    }
}
