<?php

namespace App\Filament\Resources\RestUnitBookings;

use App\Filament\Resources\RestUnitBookings\Pages;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Services\Models\RestUnitBooking;

class RestUnitBookingResource extends Resource
{
    protected static ?string $model = RestUnitBooking::class;

    protected static string|\BackedEnum|null $navigationIcon = null; // Setting to null to avoid enum issues or import Heroicon if needed. 
    // Or just string 'heroicon-o-calendar' if passing string is supported (type hint says string|BackedEnum|null).
    // Reviewing 'ProvinceResource': protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    // I don't have Heroicon imported or know the Enum values. I'll stick to string if allowed, or null.
    // 'heroicon-o-calendar' string works in v3. If v4 allows string, it's fine.
    
    protected static string|\UnitEnum|null $navigationGroup = 'Services';

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
                DatePicker::make('start_date')
                    ->required(),
                DatePicker::make('end_date')
                    ->required(),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->action(
                        Action::make('viewUser')
                            ->modalHeading('User Details')
                            ->modalSubmitAction(false)
                            ->modalCancelAction(fn (Action $action) => $action->label('Close'))
                            ->infolist(fn (Schema $schema) => $schema
                                ->components([
                                    Section::make('User Info')
                                        ->schema([
                                            TextEntry::make('user.name')->label('Name'),
                                            TextEntry::make('user.email')->label('Email'),
                                            TextEntry::make('user.phone')->label('Phone'),
                                        ])->columns(3),
                                ])
                            )
                    ),
                TextColumn::make('restUnit.name')
                    ->label('Rest Unit')
                    ->searchable()
                    ->action(
                        Action::make('viewRestUnit')
                            ->modalHeading('Rest Unit Details')
                            ->modalSubmitAction(false)
                            ->modalCancelAction(fn (Action $action) => $action->label('Close'))
                            ->infolist(fn (Schema $schema) => $schema
                                ->components([
                                    Section::make('General Info')
                                        ->schema([
                                            TextEntry::make('restUnit.name')->label('Name'),
                                            TextEntry::make('restUnit.address')->label('Address'),
                                            TextEntry::make('restUnit.province.name')->label('Province'),
                                        ])->columns(3),
                                    Section::make('Capacity')
                                        ->schema([
                                            TextEntry::make('restUnit.single_rooms')->label('Single Rooms'),
                                            TextEntry::make('restUnit.double_rooms')->label('Double Rooms'),
                                            TextEntry::make('restUnit.single_bed')->label('Single Beds'),
                                        ])->columns(3),
                                    Section::make('Pricing (Per Night)')
                                        ->schema([
                                            TextEntry::make('restUnit.single_room_price')->label('Single Room Price')->money('USD'),
                                            TextEntry::make('restUnit.double_room_price')->label('Double Room Price')->money('USD'),
                                            TextEntry::make('restUnit.single_bed_price')->label('Single Bed Price')->money('USD'),
                                        ])->columns(3),
                                ])
                            )
                    ),
                TextColumn::make('unit_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'single_rooms' => 'Single Room',
                        'double_rooms' => 'Double Room',
                        'single_bed' => 'Single Bed',
                        default => $state,
                    })
                    ->color('info'),
                TextColumn::make('total_price')
                    ->money(currency: 'USD') // Or project default currency
                    ->sortable(),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'active' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('approve')
                    ->action(fn (RestUnitBooking $record) => $record->update(['status' => 'active']))
                    ->requiresConfirmation()
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->visible(fn (RestUnitBooking $record) => $record->status === 'pending'),
                Action::make('reject')
                    ->action(fn (RestUnitBooking $record) => $record->update(['status' => 'cancelled']))
                    ->requiresConfirmation()
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->visible(fn (RestUnitBooking $record) => $record->status === 'pending'),
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
}
