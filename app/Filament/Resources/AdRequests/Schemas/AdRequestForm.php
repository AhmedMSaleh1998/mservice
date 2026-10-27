<?php

namespace App\Filament\Resources\AdRequests\Schemas;

use App\Filament\Resources\AdRequests\AdRequestResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\Ads\Enums\AdRequestStatusEnum;
use Modules\Ads\Models\AdRequest;

class AdRequestForm
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
                                    SpatieMediaLibraryFileUpload::make('design_image_media')
                                        ->label(__('Design Image'))
                                        ->collection('design_image')
                                        ->image()
                                        ->imagePreviewHeight('280')
                                        ->openable()
                                        ->downloadable()
                                        ->maxSize(5120)
                                        ->helperText(__('Uploading a new image replaces the current one.')),
                                    Textarea::make('ad_text')
                                        ->label(__('Ad Text'))
                                        ->rows(4)
                                        ->disabled(),
                                ]),
                            Section::make(__('Request Details'))
                                ->icon(Heroicon::OutlinedClipboardDocumentList)
                                ->schema([
                                    TextEntry::make('user.name')
                                        ->label(__('User'))
                                        ->placeholder('-'),
                                    TextEntry::make('user.reg_number')
                                        ->label(__('Registration Number'))
                                        ->placeholder('-'),
                                    TextEntry::make('ad_space_name')
                                        ->label(__('Ad Space'))
                                        ->state(fn (?AdRequest $record): string => AdRequestResource::getAdSpaceLabel($record?->adSpace)),
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
                                    Select::make('status')
                                        ->label(__('Status'))
                                        ->options(AdRequestStatusEnum::options([
                                            AdRequestStatusEnum::PENDING_PAYMENT,
                                            AdRequestStatusEnum::PAYMENT_EXPIRED,
                                            AdRequestStatusEnum::PAID_SUCCESSFULLY,
                                            AdRequestStatusEnum::COMPLETED,
                                            AdRequestStatusEnum::APPROVED,
                                            AdRequestStatusEnum::REJECTED,
                                        ]))
                                        ->native(false)
                                        ->required(),
                                    Toggle::make('is_visible')
                                        ->label(__('Visible in app'))
                                        ->helperText(__('Turn off to hide the ad from the app. Its display period is not extended.'))
                                        ->onColor('success')
                                        ->visible(fn (?AdRequest $record): bool => $record?->isRunning() ?? false),
                                    TextEntry::make('display_state')
                                        ->label(__('Ad State'))
                                        ->badge()
                                        ->state(fn (AdRequest $record): string => $record->displayState()->label())
                                        ->color(fn (AdRequest $record): string => $record->displayState()->color())
                                        ->helperText(fn (AdRequest $record): ?string => AdRequestResource::daysLeftLabel($record))
                                        ->visible(fn (?AdRequest $record): bool => $record?->isPublished() ?? false),
                                    Grid::make(2)
                                        ->schema([
                                            TextEntry::make('starts_at')
                                                ->label(__('Starts At'))
                                                ->dateTime('Y-m-d H:i')
                                                ->placeholder('-'),
                                            TextEntry::make('ends_at')
                                                ->label(__('Ends At'))
                                                ->dateTime('Y-m-d H:i')
                                                ->placeholder('-'),
                                        ]),
                                ]),
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
