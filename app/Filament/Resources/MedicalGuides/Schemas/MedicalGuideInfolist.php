<?php

namespace App\Filament\Resources\MedicalGuides\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;

class MedicalGuideInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Image'))
                    ->schema([
                        SpatieMediaLibraryImageEntry::make('image')
                            ->label(__('Image'))
                            ->collection('image')
                            ->placeholder(__('No image uploaded.'))
                            ->imageSize(160)
                            ->url(fn ($record) => $record->getFirstMediaUrl('image'))
                            ->openUrlInNewTab()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make(__('Medical Guide'))
                    ->schema([
                        TextEntry::make('title')
                            ->label(__('Doctor Name'))
                            ->formatStateUsing(fn ($state, $record) => $record->getTranslation('title', app()->getLocale())),
                        TextEntry::make('specialty_id')
                            ->label(__('Specialty'))
                            ->formatStateUsing(function ($state, $record) {
                                $specialty = $record->specialty?->getTranslation('name', app()->getLocale());
                                return $specialty ?: $record->getTranslation('description', app()->getLocale());
                            }),
                        TextEntry::make('province_id')
                            ->label(__('Province'))
                            ->formatStateUsing(function ($state, $record) {
                                return $record->province?->getTranslation('name', app()->getLocale());
                            }),
                        IconEntry::make('is_active')
                            ->label(__('Is Active'))
                            ->boolean()
                            ->trueColor('success')
                            ->falseColor('danger'),
                        IconEntry::make('is_featured')
                            ->label(__('Is Featured'))
                            ->boolean()
                            ->trueColor('success')
                            ->falseColor('danger'),
                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
