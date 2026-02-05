<?php

namespace App\Filament\Resources\ContactInfos\Pages;

use App\Filament\Resources\ContactInfos\ContactInfoResource;
use App\Models\ContactInfo;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListContactInfos extends ListRecords
{
    protected static string $resource = ContactInfoResource::class;

    protected Width | string | null $maxContentWidth = Width::Full;

    public function mount(): void
    {
        $record = ContactInfo::query()->firstOrCreate([]);

        $this->redirect(ContactInfoResource::getUrl('edit', ['record' => $record]));
    }
}
