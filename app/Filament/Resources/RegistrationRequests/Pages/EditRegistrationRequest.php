<?php

namespace App\Filament\Resources\RegistrationRequests\Pages;

use App\Filament\Resources\RegistrationRequests\RegistrationRequestResource;
use App\Models\Admin;
use App\Models\RegistrationRequest;
use App\Services\Oracle\OracleDoctorExportService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Grade;
use Modules\Core\Models\MedicalUniversity;
use Modules\Core\Models\Nationality;
use Modules\Core\Models\Province;

class EditRegistrationRequest extends EditRecord
{
    protected static string $resource = RegistrationRequestResource::class;

    private const AUTO_ACTION_NONE = 'none';
    private const AUTO_ACTION_REVIEW_APPROVE = 'review_approve';
    private const AUTO_ACTION_FINAL_APPROVE = 'final_approve';

    private string $autoActionAfterSave = self::AUTO_ACTION_NONE;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $existingDocuments = is_array($this->record->documents) ? $this->record->documents : [];
        $submittedDocuments = data_get($data, 'documents');

        if (is_array($submittedDocuments)) {
            $submittedDocuments = array_filter(
                $submittedDocuments,
                static fn (mixed $path): bool => filled($path)
            );

            $data['documents'] = array_replace($existingDocuments, $submittedDocuments);

            if (empty($data['documents'])) {
                unset($data['documents']);
            }
        }

        $data['license_image'] = $data['license_image']
            ?? data_get($data, 'documents.license_image')
            ?? ($existingDocuments['license_image'] ?? null);

        return $data;
    }

    protected function getSaveFormAction(): Action
    {
        $action = parent::getSaveFormAction();

        if ($this->shouldUseSaveAndApprove()) {
            $action->label(__('Save and Approve'));
        } elseif ($this->shouldUseSaveAndFinalApprove()) {
            $action->label(__('Save and Final Approve'));
        }

        return $action;
    }

    protected function getSubmitFormLivewireMethodName(): string
    {
        if ($this->shouldUseSaveAndApprove()) {
            return 'saveAndApprove';
        }

        if ($this->shouldUseSaveAndFinalApprove()) {
            return 'saveAndFinalApprove';
        }

        return parent::getSubmitFormLivewireMethodName();
    }

    public function saveAndApprove(): void
    {
        $this->autoActionAfterSave = self::AUTO_ACTION_REVIEW_APPROVE;

        try {
            $this->save();
        } finally {
            $this->autoActionAfterSave = self::AUTO_ACTION_NONE;
        }
    }

    public function saveAndFinalApprove(): void
    {
        $this->autoActionAfterSave = self::AUTO_ACTION_FINAL_APPROVE;

        try {
            $this->save();
        } finally {
            $this->autoActionAfterSave = self::AUTO_ACTION_NONE;
        }
    }

    protected function afterSave(): void
    {
        if ($this->autoActionAfterSave === self::AUTO_ACTION_REVIEW_APPROVE) {
            if (! $this->canSendToFinalApproval()) {
                return;
            }

            $this->record->update([
                'status' => RegistrationRequest::STATUS_PENDING_FINAL_APPROVAL,
                'active' => false,
            ]);

            Notification::make()
                ->title(__('Request approved and moved to final approval'))
                ->success()
                ->send();

            return;
        }

        if ($this->autoActionAfterSave !== self::AUTO_ACTION_FINAL_APPROVE) {
            return;
        }

        if (! $this->canFinalApprove()) {
            return;
        }

        if (! $this->hasCompleteLicenseData()) {
            Notification::make()
                ->title(__('License data is required before final approval'))
                ->danger()
                ->send();

            return;
        }

        if (! $this->isOracleExportEnabled()) {
            $this->record->update([
                'status' => RegistrationRequest::STATUS_APPROVED,
                'active' => true,
                'oracle_register_no' => null,
            ]);

            Notification::make()
                ->title(__('Registration approved (Oracle export is disabled).'))
                ->warning()
                ->send();

            return;
        }

        try {
            $oracleRegisterNo = app(OracleDoctorExportService::class)
                ->exportRegistrationRequest($this->record);
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title(__('There is a problem exporting data to Oracle. Please try again.'))
                ->danger()
                ->send();

            return;
        }

        $this->record->update([
            'status' => RegistrationRequest::STATUS_APPROVED,
            'active' => true,
            'oracle_register_no' => $oracleRegisterNo,
        ]);

        Notification::make()
            ->title(__('Registration approved and exported successfully'))
            ->body(__('Oracle register number: :number', ['number' => $oracleRegisterNo]))
            ->success()
            ->send();
    }

    protected function getSavedNotificationTitle(): ?string
    {
        if ($this->autoActionAfterSave !== self::AUTO_ACTION_NONE) {
            return null;
        }

        return parent::getSavedNotificationTitle();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = $this->normalizeLookupCodeToId($data, 'nationality', Nationality::class);
        $data = $this->normalizeLookupCodeToId($data, 'governorate', Province::class);
        $data = $this->normalizeLookupCodeToId($data, 'birth_governorate', Province::class);
        $data = $this->normalizeLookupCodeToId($data, 'residence_governorate', Province::class);
        $data = $this->normalizeLookupCodeToId($data, 'university', MedicalUniversity::class);
        $data = $this->normalizeLookupCodeToId($data, 'grade', Grade::class);

        return $data;
    }

    /**
     * Normalize legacy lookup values that may have been stored as `code` instead of `id`.
     */
    private function normalizeLookupCodeToId(array $data, string $field, string $modelClass): array
    {
        $value = $data[$field] ?? null;

        if (! is_numeric($value)) {
            return $data;
        }

        $id = (int) $value;

        /** @var Model $model */
        $model = new $modelClass();
        $table = $model->getTable();

        if ($modelClass::query()->whereKey($id)->exists()) {
            return $data;
        }

        if (! Schema::hasColumn($table, 'code')) {
            return $data;
        }

        $resolvedId = $modelClass::query()
            ->where('code', $id)
            ->value($model->getKeyName());

        if ($resolvedId !== null) {
            $data[$field] = (int) $resolvedId;
        }

        return $data;
    }

    private function shouldUseSaveAndApprove(): bool
    {
        return $this->canSendToFinalApproval();
    }

    private function shouldUseSaveAndFinalApprove(): bool
    {
        return $this->canFinalApprove();
    }

    private function canSendToFinalApproval(): bool
    {
        return $this->hasRole('reviewer')
            && $this->record->status === RegistrationRequest::STATUS_PENDING_REVIEW;
    }

    private function canFinalApprove(): bool
    {
        return $this->hasRole('review-supervisor')
            && $this->record->status === RegistrationRequest::STATUS_PENDING_FINAL_APPROVAL;
    }

    private function hasCompleteLicenseData(): bool
    {
        $record = $this->record->fresh();

        return filled($record?->license_number)
            && filled($record?->license_date)
            && filled($record?->license_image);
    }

    private function hasRole(string $role): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof Admin && $user->hasRole($role);
    }

    private function isOracleExportEnabled(): bool
    {
        return (bool) config('services.oracle.export_enabled', true);
    }
}
