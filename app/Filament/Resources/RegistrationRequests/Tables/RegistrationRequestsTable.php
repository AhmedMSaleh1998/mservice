<?php

namespace App\Filament\Resources\RegistrationRequests\Tables;

use App\Models\Admin;
use App\Models\RegistrationRequest;
use App\Support\CountryCodeOptions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RegistrationRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('residence_mobile_1')
                    ->label(__('Phone'))
                    ->searchable()
                    ->copyable()
                    ->formatStateUsing(fn ($state, RegistrationRequest $record) => $record->residence_mobile_1_country_code
                        ? trim((CountryCodeOptions::shortLabel($record->residence_mobile_1_country_code) ?? $record->residence_mobile_1_country_code) . ' ' . $state)
                        : $state),
                TextColumn::make('national_id')
                    ->label(__('National ID'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('reg_code')
                    ->label(__('Registration Code'))
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state) => RegistrationRequest::statusLabel($state))
                    ->color(fn (?string $state): string => static::statusColor($state)),
                TextColumn::make('license_number')
                    ->label(__('License Number'))
                    ->searchable()
                    ->visible(fn (): bool => static::canViewLicenseData())
                    ->toggleable(),
                TextColumn::make('license_date')
                    ->label(__('License Date'))
                    ->date()
                    ->sortable()
                    ->visible(fn (): bool => static::canViewLicenseData())
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('Submitted At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(RegistrationRequest::statusOptions()),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn (RegistrationRequest $record): bool => static::canEditRecord($record)),
                    Action::make('review_approve')
                        ->label(__('Review Approve'))
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(function (RegistrationRequest $record) {
                            $record->update([
                                'status' => RegistrationRequest::STATUS_PENDING_FINAL_APPROVAL,
                                'active' => false,
                            ]);

                            Notification::make()
                                ->title(__('Request approved and moved to final approval'))
                                ->success()
                                ->send();
                        })
                        ->visible(fn (RegistrationRequest $record): bool => static::canSendToFinalApproval($record)),
                    Action::make('final_approve')
                        ->label(__('Final Approve'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (RegistrationRequest $record) {
                            $hasLicenseData = filled($record->license_number)
                                && filled($record->license_date)
                                && filled($record->license_image);

                            if (! $hasLicenseData) {
                                Notification::make()
                                    ->title(__('License data is required before final approval'))
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $record->update([
                                'status' => RegistrationRequest::STATUS_APPROVED,
                                'active' => true,
                            ]);

                            Notification::make()
                                ->title(__('Registration approved successfully'))
                                ->success()
                                ->send();
                        })
                        ->visible(fn (RegistrationRequest $record): bool => static::canFinalApprove($record)),
                ]),
            ])
            ->toolbarActions([
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected static function statusColor(?string $status): string
    {
        return match ($status) {
            RegistrationRequest::STATUS_PENDING_REVIEW => 'warning',
            RegistrationRequest::STATUS_PENDING_FINAL_APPROVAL => 'info',
            RegistrationRequest::STATUS_APPROVED => 'success',
            default => 'gray',
        };
    }

    protected static function canEditRecord(RegistrationRequest $record): bool
    {
        if (static::isSuperAdmin()) {
            return true;
        }
        
        if (static::hasRole('reviewer') && $record->status === RegistrationRequest::STATUS_PENDING_REVIEW) {
            return true;
        }

        if (static::hasRole('review-supervisor') && $record->status === RegistrationRequest::STATUS_PENDING_FINAL_APPROVAL) {
            return true;
        }

        return false;
    }

    protected static function canSendToFinalApproval(RegistrationRequest $record): bool
    {
        if ($record->status !== RegistrationRequest::STATUS_PENDING_REVIEW) {
            return false;
        }

        return static::isSuperAdmin() || static::hasRole('reviewer');
    }

    protected static function canFinalApprove(RegistrationRequest $record): bool
    {
        if ($record->status !== RegistrationRequest::STATUS_PENDING_FINAL_APPROVAL) {
            return false;
        }

        return static::isSuperAdmin() || static::hasRole('review-supervisor');
    }

    protected static function canViewLicenseData(): bool
    {
        return static::isSuperAdmin() || static::hasRole('review-supervisor');
    }

    protected static function isSuperAdmin(): bool
    {
        return static::hasRole('super_admin');
    }

    protected static function hasRole(string $role): bool
    {
        $admin = static::currentAdmin();

        return $admin?->hasRole($role) ?? false;
    }

    protected static function currentAdmin(): ?Admin
    {
        $user = Filament::auth()->user();

        return $user instanceof Admin ? $user : null;
    }
}
