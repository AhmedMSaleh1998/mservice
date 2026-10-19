<?php

namespace App\Filament\Resources\AdRequests\Schemas;

use App\Filament\Resources\AdRequests\AdRequestResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Ads\Models\AdRequest;

class AdRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Ad Request'))
                    ->schema([
                        TextEntry::make('user.name')
                            ->label(__('User')),
                        TextEntry::make('adSpace.name')
                            ->label(__('Ad Space'))
                            ->getStateUsing(fn (AdRequest $record): string => AdRequestResource::getAdSpaceLabel($record->adSpace)),
                        TextEntry::make('price_per_month')
                            ->label(__('Price Per Month')),
                        TextEntry::make('duration_months')
                            ->label(__('Duration Months')),
                        TextEntry::make('total_amount')
                            ->label(__('Total Amount')),
                        TextEntry::make('ad_text')
                            ->label(__('Ad Text'))
                            ->columnSpanFull(),
                        ViewEntry::make('design_image')
                            ->label(__('Design Image'))
                            ->view('filament.infolists.ad-request-image')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make(__('Status'))
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => AdRequestResource::statusLabel($state)),
                        TextEntry::make('order.payment_method')
                            ->label(__('Payment Method'))
                            ->formatStateUsing(fn ($state) => AdRequestResource::paymentMethodLabel($state)),
                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime(),
                        TextEntry::make('starts_at')
                            ->label(__('Starts At'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('ends_at')
                            ->label(__('Ends At'))
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(3),
            ]);
    }
}
