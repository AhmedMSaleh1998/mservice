<?php

namespace App\Filament\Resources\Courses\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableTabs::make('Translations')
                    ->schema([
                        TextInput::make("title")->label(__('Title'))->required(),
                        Textarea::make("description")->label(__('Description'))->required(),
                    ])
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('image')
                    ->label(__('Image'))
                    ->collection('image')
                    ->directory('courses')
                    ->columnSpanFull(),
                DatePicker::make('start_date')->label(__('Start Date'))->required(),
                DatePicker::make('end_date')->label(__('End Date'))->required(),
                TextInput::make('price')->label(__('Price'))->numeric()->required(),
                Select::make('type')
                    ->label(__('Type'))
                    ->options([
                        'attend' => __('Attend'),
                        'online' => __('Online'),
                        'hybrid' => __('Hybrid'),
                    ])
                    ->required(),
                Checkbox::make('is_active')->label(__('Is Active')),
                Checkbox::make('is_featured')->label(__('Is Featured'))
            ]);
    }
}
