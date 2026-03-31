<?php

namespace App\Filament\Resources\CertificateRequests\Tables;

use App\Filament\Resources\CertificateRequests\CertificateRequestResource;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Certificates\Models\CertificateRequest;

class CertificateRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'certificate', 'userAddress.province', 'order']))
            ->columns([
                TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('User'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('certificate_id')
                    ->label(__('Certificate'))
                    ->getStateUsing(fn (CertificateRequest $record): string => $record->certificate
                        ? (string) $record->certificate->getTranslation('name', app()->getLocale())
                        : '-')
                    ->searchable(),
                TextColumn::make('delivery_method')
                    ->label(__('Delivery Method'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'digital' => __('Digital'),
                        'delivery' => __('Delivery'),
                        null, '' => '-',
                        default => $state,
                    })
                    ->color(fn (?string $state) => $state === 'delivery' ? 'info' : 'gray'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => CertificateRequest::statusOptions()[$state] ?? $state ?? '-')
                    ->color(fn (?string $state) => static::statusColor($state))
                    ->sortable(),
                TextColumn::make('delivery_status')
                    ->label(__('Delivery Status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? (CertificateRequest::deliveryStatusOptions()[$state] ?? $state) : '-')
                    ->color(fn (?string $state) => static::deliveryStatusColor($state))
                    ->toggleable(),
                TextColumn::make('total_amount')
                    ->label(__('Total Amount'))
                    ->money('EGP')
                    ->sortable(),
                TextColumn::make('order.payment_method')
                    ->label(__('Payment Method'))
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(CertificateRequest::statusOptions()),
                SelectFilter::make('delivery_status')
                    ->label(__('Delivery Status'))
                    ->options(CertificateRequest::deliveryStatusOptions()),
            ])
            ->recordUrl(fn (CertificateRequest $record): string => CertificateRequestResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function statusColor(?string $status): string
    {
        return match ($status) {
            CertificateRequest::STATUS_PENDING_PAYMENT => 'warning',
            CertificateRequest::STATUS_PAID_SUCCESSFULLY => 'info',
            CertificateRequest::STATUS_PROCESSING => 'primary',
            CertificateRequest::STATUS_COMPLETED => 'success',
            CertificateRequest::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }

    private static function deliveryStatusColor(?string $status): string
    {
        return match ($status) {
            CertificateRequest::DELIVERY_STATUS_PENDING => 'warning',
            CertificateRequest::DELIVERY_STATUS_PREPARING => 'info',
            CertificateRequest::DELIVERY_STATUS_SHIPPED => 'primary',
            CertificateRequest::DELIVERY_STATUS_DELIVERED => 'success',
            CertificateRequest::DELIVERY_STATUS_FAILED,
            CertificateRequest::DELIVERY_STATUS_RETURNED => 'danger',
            default => 'gray',
        };
    }
}
