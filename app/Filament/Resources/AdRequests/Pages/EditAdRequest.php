<?php

namespace App\Filament\Resources\AdRequests\Pages;

use App\Filament\Resources\AdRequests\AdRequestResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Ads\Services\AdRequestService;

class EditAdRequest extends EditRecord
{
    protected static string $resource = AdRequestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Approving or rejecting from the status field follows the same rules as
     * the approve/reject buttons: approval starts the display period and
     * rejection frees the ad space.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $newStatus = $data['status'] ?? $record->status;

        if ($newStatus === $record->status || ! in_array($newStatus, ['approved', 'rejected'], true)) {
            return parent::handleRecordUpdate($record, $data);
        }

        unset($data['status']);
        $record = parent::handleRecordUpdate($record, $data);

        $adRequestService = app(AdRequestService::class);
        $applied = $newStatus === 'approved'
            ? $adRequestService->approve($record)
            : $adRequestService->reject($record);

        if (! $applied) {
            Notification::make()
                ->title(__('Only paid requests can be approved or rejected.'))
                ->danger()
                ->send();
        }

        return $record->refresh();
    }
}
