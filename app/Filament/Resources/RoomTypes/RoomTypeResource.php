<?php

namespace App\Filament\Resources\RoomTypes;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use App\Filament\Resources\RoomTypes\Pages\CreateRoomType;
use App\Filament\Resources\RoomTypes\Pages\EditRoomType;
use App\Filament\Resources\RoomTypes\Pages\ListRoomTypes;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Modules\Services\Models\RoomType;

class RoomTypeResource extends Resource
{
    protected static ?string $model = RoomType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Tag;

    protected static ?int $navigationSort = 22;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Room Type');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Room Types');
    }

    public static function getNavigationLabel(): string
    {
        return __('Room Types');
    }

    public static function getNavigationParentItem(): ?string
    {
        return \App\Filament\Resources\ResetUnits\ResetUnitResource::getNavigationLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TranslatableTabs::make(__('Room Type'))
                ->schema([
                    TextInput::make('name')->label(__('Name'))->required(),
                ])
                ->columnSpanFull(),
            Textarea::make('description')
                ->label(__('Description'))
                ->columnSpanFull(),
            Toggle::make('is_active')
                ->label(__('Is Active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->formatStateUsing(fn ($state, RoomType $record): string => (string) $record->getTranslation('name', app()->getLocale()))
                    ->searchable(),
                TextColumn::make('rooms_count')
                    ->label(__('Used in'))
                    ->counts('rooms'),
                ToggleColumn::make('is_active')->label(__('Active')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoomTypes::route('/'),
            'create' => CreateRoomType::route('/create'),
            'edit' => EditRoomType::route('/{record}/edit'),
        ];
    }
}
