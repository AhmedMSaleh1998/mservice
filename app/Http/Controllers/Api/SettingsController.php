<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SettingUpdateRequest;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function update(SettingUpdateRequest $request)
    {
        $user = auth()->user();
        $user->fill($request->only([
            'notification_enabled',
            'lang',
        ]));
        $user->save();
        $user->fresh();
        return response()->json([
            'success' => true,
            'status' => 200,
            'data' => [
                'settings' => [
                    'lang' => $user->lang,
                    'notification_enabled' => $user->notification_enabled,
                ]
            ],
        ]);
    }
}
