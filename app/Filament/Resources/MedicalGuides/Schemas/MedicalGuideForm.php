<?php

namespace App\Filament\Resources\MedicalGuides\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Core\Models\Province;
use Modules\MedicalGuide\Models\MedicalSpecialty;

class MedicalGuideForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableTabs::make('Translations')
                    ->schema([
                        TextInput::make("title")->label(__('Doctor Name'))->required(),
                    ])
                    ->columnSpanFull(),
                Select::make('province_id')
                    ->label(__('Province'))
                    ->options(function () {
                        return Province::query()
                            ->orderBy('id')
                            ->get()
                            ->mapWithKeys(function (Province $province) {
                                return [$province->id => $province->getTranslation('name', app()->getLocale())];
                            })
                            ->all();
                    })
                    ->columnSpanFull()
                    ->searchable()
                    ->required(),
                Select::make('specialty_id')
                    ->label(__('Specialty'))
                    ->relationship('specialty', 'name', fn ($query) => $query->where('is_active', true))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        TranslatableTabs::make(__('Specialty'))
                            ->schema([
                                TextInput::make('name')->label(__('Name'))->required(),
                            ]),
                        Checkbox::make('is_active')->label(__('Is Active'))->default(true),
                    ])
                    ->createOptionUsing(function (array $data): int {
                        return MedicalSpecialty::query()->create($data)->getKey();
                    })
                    ->editOptionForm([
                        TranslatableTabs::make(__('Specialty'))
                            ->schema([
                                TextInput::make('name')->label(__('Name'))->required(),
                            ]),
                        Checkbox::make('is_active')->label(__('Is Active'))->default(true),
                    ])
                    ->updateOptionUsing(function (array $data, Select $component): void {
                        $component->getSelectedRecord()?->update($data);
                    })
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('image')
                    ->label(__('Image'))
                    ->collection('image')
                    ->disk('public')
                    ->visibility('public')
                    ->directory('medical-guides')
                    ->required()
                    ->columnSpanFull(),
                Checkbox::make('is_active')->label(__('Is Active')),
                Checkbox::make('is_featured')->label(__('Is Featured'))
            ]);
    }
}
