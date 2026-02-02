<?php

namespace App\Filament\Resources\RegistrationRequests\Schemas;

use App\Support\CountryCodeOptions;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Core\Enums\GenderEnum;
use Modules\Core\Models\Grade;
use Modules\Core\Models\Language;
use Modules\Core\Models\MedicalUniversity;
use Modules\Core\Models\Nationality;
use Modules\Core\Models\Province;
use Modules\Core\Models\Religion;

class RegistrationRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Personal Information'))
                    ->collapsible()
                    ->schema([
                        TextEntry::make('national_id')
                            ->label(__('National ID'))
                            ->copyable(),
                        TextEntry::make('full_name_ar')
                            ->label(__('Full Name (AR)')),
                        TextEntry::make('full_name_en')
                            ->label(__('Full Name (EN)')),
                        TextEntry::make('gender')
                            ->label(__('Gender'))
                            ->formatStateUsing(fn ($state) => GenderEnum::labelFor($state)),
                        TextEntry::make('nationality')
                            ->label(__('Nationality'))
                            ->formatStateUsing(fn ($state) => static::getLookupName($state, Nationality::class)),
                        TextEntry::make('religion')
                            ->label(__('Religion'))
                            ->formatStateUsing(fn ($state) => static::getLookupName($state, Religion::class)),
                        TextEntry::make('issued_from')
                            ->label(__('Issued From')),
                        TextEntry::make('governorate')
                            ->label(__('Governorate'))
                            ->formatStateUsing(fn ($state) => static::getLookupName($state, Province::class)),
                        TextEntry::make('birth_governorate')
                            ->label(__('Birth Governorate'))
                            ->formatStateUsing(fn ($state) => static::getLookupName($state, Province::class)),
                        TextEntry::make('birth_date')
                            ->label(__('Birth Date'))
                            ->date(),
                        IconEntry::make('active')
                            ->label(__('Status'))
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                        TextEntry::make('created_at')
                            ->label(__('Submitted At'))
                            ->dateTime(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('Residence Information'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('residence_house_number')
                            ->label(__('House Number')),
                        TextEntry::make('residence_street')
                            ->label(__('Street')),
                        TextEntry::make('residence_center')
                            ->label(__('Center')),
                        TextEntry::make('residence_governorate')
                            ->label(__('Residence Governorate'))
                            ->formatStateUsing(fn ($state) => static::getLookupName($state, Province::class)),
                        TextEntry::make('residence_phone')
                            ->label(__('Phone')),
                        TextEntry::make('residence_mobile_1_country_code')
                            ->label(__('Mobile 1 Country Code'))
                            ->formatStateUsing(fn ($state) => CountryCodeOptions::label($state)),
                        TextEntry::make('residence_mobile_1')
                            ->label(__('Mobile 1 Number')),
                        TextEntry::make('residence_mobile_2_country_code')
                            ->label(__('Mobile 2 Country Code'))
                            ->formatStateUsing(fn ($state) => CountryCodeOptions::label($state)),
                        TextEntry::make('residence_mobile_2')
                            ->label(__('Mobile 2 Number')),
                        TextEntry::make('email')
                            ->label(__('Email')),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('Academic Information'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('faculty')
                            ->label(__('Faculty')),
                        TextEntry::make('university')
                            ->label(__('University'))
                            ->formatStateUsing(fn ($state) => static::getLookupName($state, MedicalUniversity::class)),
                        TextEntry::make('graduation_year')
                            ->label(__('Graduation Year')),
                        TextEntry::make('graduation_month')
                            ->label(__('Graduation Month')),
                        TextEntry::make('grade')
                            ->label(__('Grade'))
                            ->formatStateUsing(fn ($state) => static::getLookupName($state, Grade::class)),
                        TextEntry::make('first_foreign_language')
                            ->label(__('First Foreign Language'))
                            ->formatStateUsing(fn ($state) => static::getLookupName($state, Language::class)),
                        TextEntry::make('second_foreign_language')
                            ->label(__('Second Foreign Language'))
                            ->formatStateUsing(fn ($state) => static::getLookupName($state, Language::class)),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('Submitted Documents'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        ViewEntry::make('documents')
                            ->label('')
                            ->view('filament.infolists.document-links')
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected static function getLookupName($id, string $modelClass): ?string
    {
        if (! $id) {
            return null;
        }

        $model = $modelClass::query()->find($id);

        if (! $model) {
            return (string) $id;
        }

        if (method_exists($model, 'getTranslation')) {
            return $model->getTranslation('name', app()->getLocale());
        }

        return $model->name ?? (string) $id;
    }
}
