<?php

namespace App\Filament\Resources\RegistrationRequests;

use App\Filament\Resources\RegistrationRequests\Pages\ListRegistrationRequests;
use App\Filament\Resources\RegistrationRequests\Pages\CreateRegistrationRequest;
use App\Filament\Resources\RegistrationRequests\Pages\ViewRegistrationRequest;
use App\Filament\Resources\RegistrationRequests\Schemas\RegistrationRequestForm;
use App\Filament\Resources\RegistrationRequests\Schemas\RegistrationRequestInfolist;
use App\Filament\Resources\RegistrationRequests\Tables\RegistrationRequestsTable;
use App\Models\RegistrationRequest;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class RegistrationRequestResource extends Resource
{
    protected static ?string $model = RegistrationRequest::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Registration Requests';

    public static function getModelLabel(): string
    {
        return __('Registration Request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Registration Requests');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('Services');
    }

    public static function form(Schema $schema): Schema
    {
        return RegistrationRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RegistrationRequestsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RegistrationRequestInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegistrationRequests::route('/'),
            'create' => CreateRegistrationRequest::route('/create'),
            'view' => ViewRegistrationRequest::route('/{record}'),
        ];
    }
}
