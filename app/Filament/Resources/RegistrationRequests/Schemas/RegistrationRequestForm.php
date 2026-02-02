<?php

namespace App\Filament\Resources\RegistrationRequests\Schemas;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;
use Modules\Core\Enums\GenderEnum;
use Modules\Core\Models\Grade;
use Modules\Core\Models\Language;
use Modules\Core\Models\MedicalUniversity;
use Modules\Core\Models\Nationality;
use Modules\Core\Models\Province;
use Modules\Core\Models\Religion;

class RegistrationRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Personal Information'))
                    ->collapsible()
                    ->schema([
                        TextInput::make('national_id')
                            ->label(__('National ID'))
                            ->required()
                            ->minLength(14)
                            ->maxLength(14)
                            ->unique(table: 'users', column: 'national_id', ignoreRecord: true),
                        TextInput::make('full_name_ar')
                            ->label(__('Full Name (AR)'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('full_name_en')
                            ->label(__('Full Name (EN)'))
                            ->required()
                            ->maxLength(255),
                        Select::make('gender')
                            ->label(__('Gender'))
                            ->options(static::genderOptions())
                            ->required()
                            ->rules([Rule::in(GenderEnum::values())]),
                        Select::make('nationality')
                            ->label(__('Nationality'))
                            ->options(static::lookupOptions(Nationality::class))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->rules(['integer', 'exists:nationalities,id']),
                        Select::make('religion')
                            ->label(__('Religion'))
                            ->options(static::lookupOptions(Religion::class))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->rules(['integer', 'exists:religions,id']),
                        TextInput::make('issued_from')
                            ->label(__('Issued From'))
                            ->required()
                            ->maxLength(100),
                        Select::make('governorate')
                            ->label(__('Governorate'))
                            ->options(static::lookupOptions(Province::class))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->rules(['integer', 'exists:provinces,id']),
                        Select::make('birth_governorate')
                            ->label(__('Birth Governorate'))
                            ->options(static::lookupOptions(Province::class))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->rules(['integer', 'exists:provinces,id']),
                        DatePicker::make('birth_date')
                            ->label(__('Birth Date'))
                            ->required()
                            ->maxDate(now()->subYears(23))
                            ->rules([
                                function ($attribute, $value, $fail) {
                                    if ($value && Carbon::parse($value)->age < 23) {
                                        $fail(__('Minimum age for graduates is 23 years.'));
                                    }
                                },
                            ]),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('Residence Information'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextInput::make('residence_house_number')
                            ->label(__('House Number'))
                            ->required()
                            ->maxLength(10),
                        TextInput::make('residence_street')
                            ->label(__('Street'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('residence_center')
                            ->label(__('Center'))
                            ->required()
                            ->maxLength(100),
                        Select::make('residence_governorate')
                            ->label(__('Residence Governorate'))
                            ->options(static::lookupOptions(Province::class))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->rules(['integer', 'exists:provinces,id']),
                        TextInput::make('residence_phone')
                            ->label(__('Residence Phone'))
                            ->tel()
                            ->required()
                            ->maxLength(10)
                            ->rule('regex:/^([0-9\\s\\-\\+\\(\\)]*)$/')
                            ->unique(ignoreRecord: true),
                        TextInput::make('residence_mobile_1')
                            ->label(__('Mobile 1'))
                            ->tel()
                            ->required()
                            ->minLength(10)
                            ->rule('regex:/^([0-9\\s\\-\\+\\(\\)]*)$/')
                            ->unique(ignoreRecord: true),
                        TextInput::make('residence_mobile_2')
                            ->label(__('Mobile 2'))
                            ->tel()
                            ->nullable()
                            ->minLength(10)
                            ->rule('regex:/^([0-9\\s\\-\\+\\(\\)]*)$/')
                            ->unique(ignoreRecord: true),
                        TextInput::make('email')
                            ->label(__('Email'))
                            ->email()
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('Academic Information'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextInput::make('faculty')
                            ->label(__('Faculty'))
                            ->required()
                            ->maxLength(255),
                        Select::make('university')
                            ->label(__('University'))
                            ->options(static::lookupOptions(MedicalUniversity::class))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->rules(['integer', 'exists:medical_universities,id']),
                        TextInput::make('graduation_year')
                            ->label(__('Graduation Year'))
                            ->required()
                            ->maxLength(10),
                        TextInput::make('graduation_month')
                            ->label(__('Graduation Month'))
                            ->required()
                            ->maxLength(2),
                        Select::make('grade')
                            ->label(__('Grade'))
                            ->options(static::lookupOptions(Grade::class))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->rules(['integer', 'exists:grades,id']),
                        Select::make('first_foreign_language')
                            ->label(__('First Foreign Language'))
                            ->options(static::lookupOptions(Language::class))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->rules(['integer', 'exists:languages,id']),
                        Select::make('second_foreign_language')
                            ->label(__('Second Foreign Language'))
                            ->options(static::lookupOptions(Language::class))
                            ->nullable()
                            ->searchable()
                            ->preload()
                            ->rules(['nullable', 'integer', 'exists:languages,id']),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('Submitted Documents'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        FileUpload::make('documents.personal_image')
                            ->label(__('Personal Photo'))
                            ->image()
                            ->required()
                            ->disk('public')
                            ->directory('documents')
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->maxSize(5120),
                        FileUpload::make('documents.national_id_image')
                            ->label(__('National ID Photo'))
                            ->image()
                            ->required()
                            ->disk('public')
                            ->directory('documents')
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->maxSize(5120),
                        FileUpload::make('documents.graduation_certificate_image')
                            ->label(__('Graduation Certificate'))
                            ->image()
                            ->required()
                            ->disk('public')
                            ->directory('documents')
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->maxSize(5120),
                        FileUpload::make('documents.internship_certificate_image')
                            ->label(__('Internship Certificate'))
                            ->image()
                            ->required()
                            ->disk('public')
                            ->directory('documents')
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->maxSize(5120),
                        FileUpload::make('documents.criminal_record_certificate_image')
                            ->label(__('Criminal Record Certificate'))
                            ->image()
                            ->required()
                            ->disk('public')
                            ->directory('documents')
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->maxSize(5120),
                        FileUpload::make('documents.dob_image')
                            ->label(__('Date of Birth Certificate'))
                            ->image()
                            ->required()
                            ->disk('public')
                            ->directory('documents')
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->maxSize(5120),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }

    private static function genderOptions(): array
    {
        return collect(GenderEnum::cases())
            ->mapWithKeys(fn (GenderEnum $case) => [$case->value => $case->label()])
            ->all();
    }

    private static function lookupOptions(string $modelClass): array
    {
        return $modelClass::query()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function ($model) {
                $name = method_exists($model, 'getTranslation')
                    ? $model->getTranslation('name', app()->getLocale())
                    : ($model->name ?? (string) $model->id);

                return [$model->id => $name];
            })
            ->all();
    }
}
