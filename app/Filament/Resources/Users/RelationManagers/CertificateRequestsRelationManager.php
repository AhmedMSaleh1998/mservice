<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\CertificateRequests\CertificateRequestResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CertificateRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'certificateRequests';

    protected static ?string $relatedResource = CertificateRequestResource::class;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Certificate Requests');
    }

    public function table(Table $table): Table
    {
        // Show the member's full request history, not just the paid ones.
        $table->getFilter('status')?->default(null);

        return $table;
    }
}
