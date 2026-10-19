<?php

namespace App\Filament\Resources\AdminNotifications;

use App\Filament\Resources\AdminNotifications\Pages\CreateAdminNotification;
use App\Filament\Resources\AdminNotifications\Pages\EditAdminNotification;
use App\Filament\Resources\AdminNotifications\Pages\ListAdminNotifications;
use App\Filament\Resources\AdminNotifications\Pages\ViewAdminNotification;
use App\Filament\Resources\AdminNotifications\Schemas\AdminNotificationForm;
use App\Filament\Resources\AdminNotifications\Schemas\AdminNotificationInfolist;
use App\Filament\Resources\AdminNotifications\Tables\AdminNotificationsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Notifications\Models\AdminNotification;

class AdminNotificationResource extends Resource
{
    protected static ?string $model = AdminNotification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BellAlert;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 109;

    public static function getModelLabel(): string
    {
        return __('Notification');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Notifications');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return null;
    }

    public static function form(Schema $schema): Schema
    {
        return AdminNotificationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminNotificationsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AdminNotificationInfolist::configure($schema);
    }

    // A sent notification is a record of what went out; only drafts change.
    public static function canEdit(Model $record): bool
    {
        return $record->isDraft() && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return $record->isDraft() && parent::canDelete($record);
    }

    public static function channelLabel(string $channel): string
    {
        return match ($channel) {
            AdminNotification::CHANNEL_FCM => __('App notification (Firebase)'),
            AdminNotification::CHANNEL_SMS => __('SMS'),
            default => $channel,
        };
    }

    public static function audienceLabel(?string $audience): string
    {
        return match ($audience) {
            AdminNotification::AUDIENCE_ALL => __('All users'),
            AdminNotification::AUDIENCE_USERS => __('Specific users'),
            AdminNotification::AUDIENCE_REGISTRATION_NUMBERS => __('Registration numbers list'),
            default => (string) $audience,
        };
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            AdminNotification::STATUS_DRAFT => __('Draft'),
            AdminNotification::STATUS_QUEUED => __('Queued'),
            AdminNotification::STATUS_SENDING => __('Sending'),
            AdminNotification::STATUS_COMPLETED => __('Completed'),
            default => (string) $status,
        };
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            AdminNotification::STATUS_DRAFT => 'gray',
            AdminNotification::STATUS_QUEUED => 'warning',
            AdminNotification::STATUS_SENDING => 'info',
            AdminNotification::STATUS_COMPLETED => 'success',
            default => 'gray',
        };
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminNotifications::route('/'),
            'create' => CreateAdminNotification::route('/create'),
            'view' => ViewAdminNotification::route('/{record}'),
            'edit' => EditAdminNotification::route('/{record}/edit'),
        ];
    }
}
