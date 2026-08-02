<?php

namespace App\Filament\Resources\SmsMessages;

use App\Filament\Resources\SmsMessages\Pages\ListSmsMessages;
use App\Filament\Resources\SmsMessages\Pages\ViewSmsMessage;
use App\Filament\Resources\SmsMessages\Schemas\SmsMessageInfolist;
use App\Filament\Resources\SmsMessages\Tables\SmsMessagesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Models\SmsMessage;

class SmsMessageResource extends Resource
{
    protected static ?string $model = SmsMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChatBubbleLeftRight;

    protected static ?int $navigationSort = 110;

    public static function getModelLabel(): string
    {
        return __('SMS Message');
    }

    public static function getPluralModelLabel(): string
    {
        return __('SMS Messages');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return null;
    }

    public static function table(Table $table): Table
    {
        return SmsMessagesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SmsMessageInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSmsMessages::route('/'),
            'view' => ViewSmsMessage::route('/{record}'),
        ];
    }
}
