<?php

namespace App\Filament\Resources\ResetUnits\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;
use App\Filament\Pages\RestUnitBedView;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Modules\Services\Models\RestUnit;
use Modules\Services\Models\RestUnitBed;

class BedsRelationManager extends RelationManager
{
    protected static string $relationship = 'beds';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Beds');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof RestUnit && $ownerRecord->isBeds();
    }

    public function form(Schema $schema): Schema
    {
        $ownerId = $this->getOwnerRecord()?->getKey();

        return $schema->components([
            TextInput::make('label')
                ->label(__('Bed name / number'))
                ->required()
                ->unique(
                    table: 'rest_unit_beds',
                    column: 'label',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule): Unique => $rule
                        ->where('rest_unit_id', $ownerId)
                        ->whereNull('deleted_at'),
                )
                ->validationMessages([
                    'unique' => __('This name/number already exists in this rest unit.'),
                ])
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('label')->label(__('Bed')),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => RestUnitBed::statusOptions()[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => $state === RestUnitBed::STATUS_MAINTENANCE ? 'warning' : 'success'),
                TextColumn::make('maintenance_note')->label(__('Maintenance note'))->placeholder('—')->wrap(),
                TextColumn::make('maintenance_started_at')->label(__('Since'))->dateTime()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(RestUnitBed::statusOptions()),
            ])
            ->headerActions([
                CreateAction::make()->label(__('Add bed')),
                Action::make('generate')
                    ->label(__('Generate beds'))
                    ->icon('heroicon-o-plus-circle')
                    ->modalDescription(__('Beds are named automatically (Bed 1, Bed 2, …) and you can edit the names later — this helps you add a large number of beds quickly.'))
                    ->schema([
                        TextInput::make('count')->label(__('How many beds?'))->numeric()->minValue(1)->default(1)->required(),
                    ])
                    ->action(function (array $data): void {
                        $existing = $this->getOwnerRecord()->beds()->count();
                        $count = max((int) ($data['count'] ?? 0), 0);
                        for ($i = 1; $i <= $count; $i++) {
                            $this->getOwnerRecord()->beds()->create([
                                'label' => __('Bed :number', ['number' => $existing + $i]),
                                'status' => RestUnitBed::STATUS_IN_SERVICE,
                            ]);
                        }
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->label(__('View'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (RestUnitBed $record): string => RestUnitBedView::getUrl(['record' => $record->getKey()])),
                EditAction::make(),
                Action::make('toMaintenance')
                    ->label(__('Send to maintenance'))
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->visible(fn (RestUnitBed $record): bool => $record->isInService())
                    ->schema([
                        Textarea::make('maintenance_note')->label(__('Maintenance note')),
                    ])
                    ->action(fn (RestUnitBed $record, array $data) => $record->sendToMaintenance($data['maintenance_note'] ?? null)),
                Action::make('returnToService')
                    ->label(__('Return to service'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (RestUnitBed $record): bool => $record->isUnderMaintenance())
                    ->action(fn (RestUnitBed $record) => $record->returnToService()),
                DeleteAction::make()
                    ->requiresConfirmation(fn (RestUnitBed $record): bool => ! $record->bookings()->exists())
                    ->before(function (RestUnitBed $record, DeleteAction $action): void {
                        if ($record->bookings()->exists()) {
                            Notification::make()
                                ->title(__('This bed cannot be deleted because it has bookings.'))
                                ->danger()
                                ->send();
                            $action->halt();
                        }
                    }),
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
