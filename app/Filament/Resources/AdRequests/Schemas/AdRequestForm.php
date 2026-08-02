<?php

namespace App\Filament\Resources\AdRequests\Schemas;

use App\Filament\Resources\AdRequests\AdRequestResource;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Ads\Models\AdRequest;

class AdRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Ad Request'))
                    ->schema([
                        Placeholder::make('user_name')
                            ->label(__('User'))
                            ->content(fn ($record) => $record?->user?->name ?? '-'),
                        Placeholder::make('ad_space_name')
                            ->label(__('Ad Space'))
                            ->content(fn (?AdRequest $record): string => AdRequestResource::getAdSpaceLabel($record?->adSpace)),
                        TextInput::make('duration_months')
                            ->label(__('Duration Months'))
                            ->numeric()
                            ->disabled(),
                        TextInput::make('price_per_month')
                            ->label(__('Price Per Month'))
                            ->disabled(),
                        TextInput::make('total_amount')
                            ->label(__('Total Amount'))
                            ->disabled(),
                        Textarea::make('ad_text')
                            ->label(__('Ad Text'))
                            ->disabled()
                            ->columnSpanFull(),
                        TextInput::make('design_image_path')
                            ->label(__('Design Image Path'))
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make(__('Status'))
                    ->schema([
                        Select::make('status')
                            ->label(__('Status'))
                            ->options([
                                'pending_payment' => __('Pending Payment'),
                                'payment_expired' => __('Payment Expired'),
                                'paid_successfully' => __('Paid Successfully'),
                                'completed' => __('Completed'),
                                'approved' => __('Approved'),
                                'rejected' => __('Rejected'),
                            ])
                            ->required(),
                        Placeholder::make('order_payment_method')
                            ->label(__('Payment Method'))
                            ->content(fn ($record) => $record?->order?->payment_method ?? '-'),
                        Placeholder::make('starts_at')
                            ->label(__('Starts At'))
                            ->content(fn ($record) => optional($record?->starts_at)->format('Y-m-d H:i:s') ?? '-'),
                        Placeholder::make('ends_at')
                            ->label(__('Ends At'))
                            ->content(fn ($record) => optional($record?->ends_at)->format('Y-m-d H:i:s') ?? '-'),
                    ])
                    ->columns(2),
            ]);
    }
}
