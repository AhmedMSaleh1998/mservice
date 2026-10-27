<?php

namespace App\Filament\Resources\AdRequests\Schemas;

use App\Filament\Resources\AdRequests\AdRequestResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\Ads\Enums\AdRequestStatusEnum;
use Modules\Ads\Models\AdRequest;

class AdRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        Group::make([
                            Section::make(__('Ad Content'))
                                ->icon(Heroicon::OutlinedPhoto)
                                ->schema([
                                    SpatieMediaLibraryImageEntry::make('design_image')
                                        ->label(__('Design Image'))
                                        ->collection('design_image')
                                        ->imageHeight(320)
                                        ->extraImgAttributes(['class' => 'rounded-lg object-contain'])
                                        ->url(fn (AdRequest $record): ?string => $record->getFirstMediaUrl('design_image') ?: null)
                                        ->openUrlInNewTab()
                                        ->placeholder(__('No image uploaded.')),
                                    TextEntry::make('ad_text')
                                        ->label(__('Ad Text'))
                                        ->placeholder('-')
                                        ->prose(),
                                ]),
                            Section::make(__('Request Details'))
                                ->icon(Heroicon::OutlinedClipboardDocumentList)
                                ->schema([
                                    TextEntry::make('user.name')
                                        ->label(__('User'))
                                        ->placeholder('-')
                                        ->color('info')
                                        ->url(fn (AdRequest $record): ?string => $record->user_id
                                            ? UserResource::getUrl('edit', ['record' => $record->user_id])
                                            : null)
                                        ->openUrlInNewTab(),
                                    TextEntry::make('user.reg_number')
                                        ->label(__('Registration Number'))
                                        ->placeholder('-'),
                                    TextEntry::make('adSpace.name')
                                        ->label(__('Ad Space'))
                                        ->getStateUsing(fn (AdRequest $record): string => AdRequestResource::getAdSpaceLabel($record->adSpace)),
                                    TextEntry::make('duration_months')
                                        ->label(__('Duration Months')),
                                    TextEntry::make('price_per_month')
                                        ->label(__('Price Per Month'))
                                        ->money('EGP'),
                                    TextEntry::make('total_amount')
                                        ->label(__('Total Amount'))
                                        ->money('EGP')
                                        ->weight('bold'),
                                ])
                                ->columns(['default' => 1, 'sm' => 2, 'xl' => 3]),
                        ])
                            ->columnSpan(['lg' => 2]),
                        Group::make([
                            Section::make(__('Status'))
                                ->icon(Heroicon::OutlinedSignal)
                                ->schema([
                                    TextEntry::make('status')
                                        ->label(__('Status'))
                                        ->badge()
                                        ->formatStateUsing(fn (?string $state): string => AdRequestStatusEnum::labelFor($state))
                                        ->color(fn (?string $state): string => AdRequestStatusEnum::colorFor($state)),
                                    TextEntry::make('display_state')
                                        ->label(__('Ad State'))
                                        ->badge()
                                        ->getStateUsing(fn (AdRequest $record): string => $record->displayState()->label())
                                        ->color(fn (AdRequest $record): string => $record->displayState()->color())
                                        ->helperText(fn (AdRequest $record): ?string => AdRequestResource::daysLeftLabel($record))
                                        ->visible(fn (AdRequest $record): bool => $record->isPublished()),
                                    IconEntry::make('is_visible')
                                        ->label(__('Visible in app'))
                                        ->boolean()
                                        ->visible(fn (AdRequest $record): bool => $record->isRunning()),
                                    TextEntry::make('starts_at')
                                        ->label(__('Starts At'))
                                        ->dateTime('Y-m-d H:i')
                                        ->placeholder('-'),
                                    TextEntry::make('ends_at')
                                        ->label(__('Ends At'))
                                        ->dateTime('Y-m-d H:i')
                                        ->placeholder('-'),
                                ])
                                ->columns(2),
                            Section::make(__('Payment'))
                                ->icon(Heroicon::OutlinedCreditCard)
                                ->schema([
                                    TextEntry::make('order.payment_method')
                                        ->label(__('Payment Method'))
                                        ->formatStateUsing(fn ($state) => AdRequestResource::paymentMethodLabel($state))
                                        ->placeholder('-'),
                                    TextEntry::make('created_at')
                                        ->label(__('Created At'))
                                        ->dateTime('Y-m-d H:i'),
                                ])
                                ->columns(2),
                        ])
                            ->columnSpan(['lg' => 1]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
