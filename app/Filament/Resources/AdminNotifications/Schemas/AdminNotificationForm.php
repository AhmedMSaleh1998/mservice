<?php

namespace App\Filament\Resources\AdminNotifications\Schemas;

use App\Filament\Resources\AdminNotifications\AdminNotificationResource;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Modules\Notifications\Models\AdminNotification;
use Modules\Notifications\Services\AdminNotificationService;
use Modules\Notifications\Services\FcmService;
use Modules\Users\Models\User;

class AdminNotificationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Notification Content'))
                    ->schema([
                        TextInput::make('title')
                            ->label(__('Title'))
                            ->required()
                            ->maxLength(100),
                        Textarea::make('body')
                            ->label(__('Message'))
                            ->required()
                            ->rows(4)
                            ->maxLength(500)
                            ->helperText(__('SMS recipients receive the message text only, without the title.')),
                    ])
                    ->columnSpanFull(),
                Section::make(__('Sending Options'))
                    ->schema([
                        CheckboxList::make('channels')
                            ->label(__('Channels'))
                            ->options([
                                AdminNotification::CHANNEL_FCM => AdminNotificationResource::channelLabel(AdminNotification::CHANNEL_FCM),
                                AdminNotification::CHANNEL_SMS => AdminNotificationResource::channelLabel(AdminNotification::CHANNEL_SMS),
                            ])
                            ->disableOptionWhen(fn (string $value): bool => $value === AdminNotification::CHANNEL_FCM
                                && ! app(FcmService::class)->isEnabled())
                            ->helperText(fn (): ?string => app(FcmService::class)->isEnabled()
                                ? null
                                : __('App notifications are disabled until Firebase is configured.'))
                            ->required(),
                        Radio::make('audience')
                            ->label(__('Recipients'))
                            ->options([
                                AdminNotification::AUDIENCE_ALL => AdminNotificationResource::audienceLabel(AdminNotification::AUDIENCE_ALL),
                                AdminNotification::AUDIENCE_USERS => AdminNotificationResource::audienceLabel(AdminNotification::AUDIENCE_USERS),
                                AdminNotification::AUDIENCE_REGISTRATION_NUMBERS => AdminNotificationResource::audienceLabel(AdminNotification::AUDIENCE_REGISTRATION_NUMBERS),
                            ])
                            ->default(AdminNotification::AUDIENCE_ALL)
                            ->required()
                            ->live(),
                        Select::make('user_ids')
                            ->label(__('Users'))
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => User::query()
                                ->where('active', true)
                                ->where(fn ($query) => $query
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%")
                                    ->orWhere('reg_number', 'like', "%{$search}%"))
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (User $user): array => [$user->id => static::userLabel($user)])
                                ->all())
                            ->getOptionLabelsUsing(fn (array $values): array => User::query()
                                ->whereIn('id', $values)
                                ->get()
                                ->mapWithKeys(fn (User $user): array => [$user->id => static::userLabel($user)])
                                ->all())
                            ->helperText(__('Search by name, phone or registration number.'))
                            ->visible(fn (Get $get): bool => $get('audience') === AdminNotification::AUDIENCE_USERS)
                            ->required(fn (Get $get): bool => $get('audience') === AdminNotification::AUDIENCE_USERS),
                        Textarea::make('registration_numbers')
                            ->label(__('Registration numbers'))
                            ->rows(6)
                            ->helperText(__('One registration number per line, or separated by commas.'))
                            ->formatStateUsing(fn ($state): ?string => is_array($state) ? implode("\n", $state) : $state)
                            ->dehydrateStateUsing(fn ($state): array => app(AdminNotificationService::class)->parseRegistrationNumbers($state))
                            ->visible(fn (Get $get): bool => $get('audience') === AdminNotification::AUDIENCE_REGISTRATION_NUMBERS)
                            ->required(fn (Get $get): bool => $get('audience') === AdminNotification::AUDIENCE_REGISTRATION_NUMBERS),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Keeps only the recipient list that matches the chosen audience, so a
     * notification switched from one audience to another carries no stale list.
     */
    public static function normalizeRecipients(array $data): array
    {
        $audience = $data['audience'] ?? AdminNotification::AUDIENCE_ALL;

        $data['user_ids'] = $audience === AdminNotification::AUDIENCE_USERS
            ? array_values(array_map('intval', $data['user_ids'] ?? []))
            : null;

        $data['registration_numbers'] = $audience === AdminNotification::AUDIENCE_REGISTRATION_NUMBERS
            ? array_values($data['registration_numbers'] ?? [])
            : null;

        return $data;
    }

    private static function userLabel(User $user): string
    {
        return collect([$user->name, $user->reg_number, $user->phone])->filter()->implode(' - ');
    }
}
