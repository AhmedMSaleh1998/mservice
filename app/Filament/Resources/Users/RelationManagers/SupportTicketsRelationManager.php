<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;

class SupportTicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'supportTickets';

    protected static ?string $relatedResource = SupportTicketResource::class;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Support Tickets');
    }
}
