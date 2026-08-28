<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\AdRequests\AdRequestResource;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;

class AdRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'adRequests';

    protected static ?string $relatedResource = AdRequestResource::class;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Ad Requests');
    }
}
