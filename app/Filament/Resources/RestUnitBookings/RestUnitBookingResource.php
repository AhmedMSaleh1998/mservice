<?php

namespace App\Filament\Resources\RestUnitBookings;

use App\Filament\Resources\RestUnitBookings\Pages;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Services\Models\RestUnit;
use Modules\Services\Models\RestUnitBooking;

class RestUnitBookingResource extends Resource
{
    protected static ?string $model = RestUnitBooking::class;

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Rest units';

    public static function getModelLabel(): string
    {
        return __('Rest Unit Booking');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Rest Unit Bookings');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('Rest Units');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Select::make('rest_unit_id')
                    ->relationship('restUnit', 'name')
                    ->required(),
                Select::make('unit_type')
                    ->options(self::roomTypeOptions())
                    ->required(),
                DatePicker::make('start_date')
                    ->required(),
                DatePicker::make('end_date')
                    ->required(),
                Select::make('status')
                    ->options(self::statusOptions())
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.name')
                    ->label(__('User'))
                    ->searchable()
                    ->action(
                        Action::make('viewUser')
                            ->modalHeading(__('User Details'))
                            ->modalSubmitAction(false)
                            ->modalCancelAction(fn (Action $action) => $action->label(__('Close')))
                            ->infolist(fn (Infolist $infolist) => $infolist
                                ->schema([
                                    Section::make(__('User Info'))
                                        ->schema([
                                            TextEntry::make('user.name')->label(__('Name')),
                                            TextEntry::make('user.email')->label(__('Email')),
                                            TextEntry::make('user.phone')->label(__('Phone')),
                                        ])
                                        ->columns(3),
                                ]))
                    ),
                TextColumn::make('restUnit.name')
                    ->label(__('Rest Unit'))
                    ->searchable()
                    ->action(
                        Action::make('viewRestUnit')
                            ->modalHeading(__('Rest Unit Details'))
                            ->modalSubmitAction(false)
                            ->modalCancelAction(fn (Action $action) => $action->label(__('Close')))
                            ->infolist(fn (Infolist $infolist) => $infolist
                                ->schema([
                                    Section::make(__('General Info'))
                                        ->schema([
                                            TextEntry::make('restUnit.name')->label(__('Name')),
                                            TextEntry::make('restUnit.address')->label(__('Address')),
                                            TextEntry::make('restUnit.province.name')->label(__('Province')),
                                        ])
                                        ->columns(3),
                                    Section::make(__('Capacity'))
                                        ->schema([
                                            TextEntry::make('restUnit.single_rooms')->label(__('Single Rooms')),
                                            TextEntry::make('restUnit.double_rooms')->label(__('Double Rooms')),
                                            TextEntry::make('restUnit.triple_rooms')->label(__('Triple Rooms')),
                                        ])
                                        ->columns(3),
                                    Section::make(__('Pricing (Per Night)'))
                                        ->schema([
                                            TextEntry::make('restUnit.single_room_price')->label(__('Single Room Price'))->money('EGP'),
                                            TextEntry::make('restUnit.double_room_price')->label(__('Double Room Price'))->money('EGP'),
                                            TextEntry::make('restUnit.triple_room_price')->label(__('Triple Room Price'))->money('EGP'),
                                        ])
                                        ->columns(3),
                                ]))
                    ),
                TextColumn::make('unit_type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::roomTypeOptions()[$state ?? ''] ?? (string) $state)
                    ->color('info'),
                TextColumn::make('total_price')
                    ->money(currency: 'EGP')
                    ->sortable(),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::statusOptions()[$state ?? ''] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        RestUnitBooking::STATUS_PENDING_PAYMENT => 'warning',
                        RestUnitBooking::STATUS_PAID_SUCCESSFULLY => 'success',
                        RestUnitBooking::STATUS_PAYMENT_EXPIRED => 'gray',
                        RestUnitBooking::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(self::statusOptions()),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('cancel')
                    ->label(__('Cancel'))
                    ->action(fn (RestUnitBooking $record) => $record->update(['status' => RestUnitBooking::STATUS_CANCELLED]))
                    ->requiresConfirmation()
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->visible(fn (RestUnitBooking $record) => $record->status === RestUnitBooking::STATUS_PENDING_PAYMENT),
            ]);
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
            'index' => Pages\ListRestUnitBookings::route('/'),
            'create' => Pages\CreateRestUnitBooking::route('/create'),
            'edit' => Pages\EditRestUnitBooking::route('/{record}/edit'),
        ];
    }

    private static function roomTypeOptions(): array
    {
        return [
            RestUnit::TYPE_SINGLE_ROOM => __('Single room'),
            RestUnit::TYPE_DOUBLE_ROOM => __('Double room'),
            RestUnit::TYPE_TRIPLE_ROOM => __('Triple room'),
            'single_rooms' => __('Single room'),
            'double_rooms' => __('Double room'),
            'single_bed' => __('Triple room'),
        ];
    }

    private static function statusOptions(): array
    {
        return [
            RestUnitBooking::STATUS_PENDING_PAYMENT => __('Pending Payment'),
            RestUnitBooking::STATUS_PAID_SUCCESSFULLY => __('Paid Successfully'),
            RestUnitBooking::STATUS_PAYMENT_EXPIRED => __('Payment Expired'),
            RestUnitBooking::STATUS_CANCELLED => __('Cancelled'),
        ];
    }
}
