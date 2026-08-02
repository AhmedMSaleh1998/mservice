<?php

namespace App\Filament\Resources\ResetUnits\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Modules\Services\Models\RestUnit;
use Modules\Services\Models\RestUnitRoom;
use Modules\Services\Models\RoomType;


class RoomsRelationManager extends RelationManager
{
    protected static string $relationship = 'rooms';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Rooms');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof RestUnit && $ownerRecord->isRooms();
    }

    public function form(Schema $schema): Schema
    {
        $ownerId = $this->getOwnerRecord()?->getKey();

        return $schema->components([
            Select::make('room_type_id')
                ->label(__('Room type'))
                ->options(fn (): array => RoomType::query()->where('is_active', true)->get()
                    ->mapWithKeys(fn (RoomType $rt): array => [$rt->id => $rt->getTranslation('name', app()->getLocale())])
                    ->all())
                ->searchable()
                ->required()
                ->native(false)
                ->createOptionForm([
                    TextInput::make('name_ar')->label(__('Name (Arabic)'))->required(),
                    TextInput::make('name_en')->label(__('Name (English)'))->required(),
                ])
                ->createOptionUsing(fn (array $data): int => RoomType::create([
                    'name' => ['ar' => $data['name_ar'], 'en' => $data['name_en']],
                    'is_active' => true,
                ])->id)
                ->createOptionModalHeading(__('Add room type')),
            TextInput::make('name')
                ->label(__('Room name / number'))
                ->required()
                ->unique(
                    table: 'rest_unit_rooms',
                    column: 'name',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule): Unique => $rule
                        ->where('rest_unit_id', $ownerId)
                        ->whereNull('deleted_at'),
                )
                ->validationMessages([
                    'unique' => __('This name/number already exists in this rest unit.'),
                ]),
            TextInput::make('price')->label(__('Price / night'))->numeric()->prefix('EGP')->default(0)->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label(__('Room')),
                TextColumn::make('roomType.name')
                    ->label(__('Room type'))
                    ->formatStateUsing(fn ($state, RestUnitRoom $record): ?string => $record->typeName()),
                TextColumn::make('price')->label(__('Price / night'))->money('EGP'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => RestUnitRoom::statusOptions()[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => $state === RestUnitRoom::STATUS_MAINTENANCE ? 'warning' : 'success'),
                TextColumn::make('maintenance_note')->label(__('Maintenance note'))->placeholder('—')->wrap(),
            ])
            ->filters([
                SelectFilter::make('status')->options(RestUnitRoom::statusOptions()),
                SelectFilter::make('room_type_id')
                    ->label(__('Room type'))
                    ->options(fn (): array => RoomType::query()->get()
                        ->mapWithKeys(fn (RoomType $rt): array => [$rt->id => $rt->getTranslation('name', app()->getLocale())])
                        ->all()),
            ])
            ->headerActions([
                CreateAction::make()->label(__('Add room')),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),

                    Action::make('toMaintenance')
                        ->label(__('Send to maintenance'))
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->color('warning')
                        ->visible(fn (RestUnitRoom $record): bool => $record->isInService())
                        ->schema([
                            Textarea::make('maintenance_note')
                                ->label(__('Maintenance note')),
                        ])
                        ->action(fn (RestUnitRoom $record, array $data) =>
                            $record->sendToMaintenance($data['maintenance_note'] ?? null)
                        ),

                    Action::make('returnToService')
                        ->label(__('Return to service'))
                        ->icon('heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->visible(fn (RestUnitRoom $record): bool => $record->isUnderMaintenance())
                        ->action(fn (RestUnitRoom $record) =>
                            $record->returnToService()
                        ),

                    DeleteAction::make()
                        ->requiresConfirmation(fn (RestUnitRoom $record): bool => ! $record->bookings()->exists())
                        ->before(function (RestUnitRoom $record, DeleteAction $action): void {
                            if ($record->bookings()->exists()) {
                                Notification::make()
                                    ->title(__('This room cannot be deleted because it has bookings.'))
                                    ->danger()
                                    ->send();
                                $action->halt();
                            }
                        }),
                ])
                ->label('')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button(), // Optional: renders as a button instead of just an icon
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkMaintenance')
                        ->label(__('Send to maintenance'))
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->schema([
                            Textarea::make('maintenance_note')->label(__('Maintenance note')),
                        ])
                        ->action(fn (Collection $records, array $data) => $records->each->sendToMaintenance($data['maintenance_note'] ?? null))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('bulkReturn')
                        ->label(__('Return to service'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->returnToService())
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
