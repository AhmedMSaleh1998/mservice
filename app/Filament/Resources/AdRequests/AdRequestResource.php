<?php

namespace App\Filament\Resources\AdRequests;

use App\Filament\Resources\AdSpaces\AdSpaceResource;
use App\Filament\Resources\AdRequests\Pages\EditAdRequest;
use App\Filament\Resources\AdRequests\Pages\ListAdRequests;
use App\Filament\Resources\AdRequests\Pages\ViewAdRequest;
use App\Filament\Resources\AdRequests\Schemas\AdRequestForm;
use App\Filament\Resources\AdRequests\Schemas\AdRequestInfolist;
use App\Filament\Resources\AdRequests\Tables\AdRequestsTable;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Ads\Models\AdSpace;
use Modules\Ads\Models\AdRequest;
use Modules\Ads\Services\AdRequestService;
use Modules\Core\Models\PaymentMethod;

class AdRequestResource extends Resource
{
    protected static ?string $model = AdRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?int $navigationSort = 31;

    public static function getModelLabel(): string
    {
        return __('Ad Request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Ad Requests');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return null;
    }

    public static function getNavigationParentItem(): ?string
    {
        return AdSpaceResource::getNavigationLabel();
    }

    public static function getAdSpaceLabel(AdSpace|null $adSpace): string
    {
        $service = $adSpace?->service;

        if (! $service) {
            return '-';
        }

        return $service->getTranslation('title', app()->getLocale())
            ?: ($service->getTranslation('title', 'en') ?: ($service->key ?? '-'));
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('Approve'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('The ad starts showing now and runs for its paid duration.'))
            ->action(function (AdRequest $record, AdRequestService $adRequestService): void {
                if (! $adRequestService->approve($record)) {
                    Notification::make()
                        ->title(__('This request is no longer awaiting approval.'))
                        ->danger()
                        ->send();

                    return;
                }

                $record->refresh();

                Notification::make()
                    ->title(__('Approved'))
                    ->body(__('The ad is shown until :date.', ['date' => $record->ends_at?->format('Y-m-d H:i')]))
                    ->success()
                    ->send();
            })
            ->visible(fn (AdRequest $record): bool => $record->status === 'paid_successfully');
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('Reject'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('The ad space becomes available again. Refund the payment to the doctor separately.'))
            ->action(function (AdRequest $record, AdRequestService $adRequestService): void {
                if (! $adRequestService->reject($record)) {
                    Notification::make()
                        ->title(__('This request is no longer awaiting approval.'))
                        ->danger()
                        ->send();

                    return;
                }

                $record->refresh();

                Notification::make()
                    ->title(__('Rejected'))
                    ->warning()
                    ->send();
            })
            ->visible(fn (AdRequest $record): bool => $record->status === 'paid_successfully');
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'pending_payment' => __('Pending Payment'),
            'payment_expired' => __('Payment Expired'),
            'pending_review' => __('Pending Review'),
            'paid_successfully' => __('Paid Successfully'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
            'completed' => __('Completed'),
            'cancelled' => __('Cancelled'),
            default => (string) $status,
        };
    }

    public static function paymentMethodLabel(?string $key): ?string
    {
        if (! $key) {
            return null;
        }

        static $labels = [];

        if (! array_key_exists($key, $labels)) {
            $method = PaymentMethod::query()->where('key', $key)->first();
            $labels[$key] = $method ? $method->getTranslation('name', app()->getLocale()) : $key;
        }

        return $labels[$key];
    }

    public static function form(Schema $schema): Schema
    {
        return AdRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdRequestsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AdRequestInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdRequests::route('/'),
            'view' => ViewAdRequest::route('/{record}'),
            'edit' => EditAdRequest::route('/{record}/edit'),
        ];
    }
}
