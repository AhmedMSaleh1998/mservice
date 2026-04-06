<?php

namespace App\Filament\Resources\CertificateRequests;

use App\Filament\Resources\Certificates\CertificateResource;
use App\Filament\Resources\CertificateRequests\Pages\EditCertificateRequest;
use App\Filament\Resources\CertificateRequests\Pages\ListCertificateRequests;
use App\Filament\Resources\CertificateRequests\Schemas\CertificateRequestForm;
use App\Filament\Resources\CertificateRequests\Tables\CertificateRequestsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Certificates\Models\CertificateRequest;

class CertificateRequestResource extends Resource
{
    protected static ?string $model = CertificateRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::QueueList;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?int $navigationSort = 41;

    public static function getModelLabel(): string
    {
        return __('Certificate Request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Certificate Requests');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return null;
    }

    public static function getNavigationParentItem(): ?string
    {
        return CertificateResource::getNavigationLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return CertificateRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CertificateRequestsTable::configure($table);
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
            'index' => ListCertificateRequests::route('/'),
            'edit' => EditCertificateRequest::route('/{record}/edit'),
        ];
    }
}
