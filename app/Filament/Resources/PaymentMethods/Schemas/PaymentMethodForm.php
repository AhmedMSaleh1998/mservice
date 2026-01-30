<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PaymentMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableTabs::make(__('Name'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required(),
                    ])
                    ->columnSpanFull(),
                TextInput::make('key')
                    ->label(__('Key'))
                    ->hidden()
                    ->dehydrated(false),
                Checkbox::make('is_active')
                    ->label(__('Is Active'))
                    ->default(true),
            ]);
    }
}
