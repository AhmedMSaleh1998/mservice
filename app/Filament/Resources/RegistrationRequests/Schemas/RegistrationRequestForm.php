<?php

namespace App\Filament\Resources\RegistrationRequests\Schemas;

use App\Models\Admin;
use App\Models\RegistrationRequest;
use Carbon\Carbon;
use App\Support\CountryCodeOptions;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Modules\Core\Enums\GenderEnum;
use Modules\Core\Models\Grade;
use Modules\Core\Models\Language;
use Modules\Core\Models\MedicalUniversity;
use Modules\Core\Models\Nationality;
use Modules\Core\Models\Province;
use Modules\Core\Models\Religion;
use Modules\Users\Services\RegistrationRequestPdfService;

class RegistrationRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(function (?RegistrationRequest $record): array {
                $components = [
                Section::make(__('Generated PDF Documents'))
                    ->visible(fn (?RegistrationRequest $record): bool => filled($record?->reg_code))
                    ->schema([
                        Placeholder::make('generated_pdfs_reg_code')
                            ->label(__('Registration Code'))
                            ->content(fn (?RegistrationRequest $record): string => (string) ($record?->reg_code ?? '-')),
                        Placeholder::make('generated_pdfs_registration_request')
                            ->label(__('Registration request form'))
                            ->content(fn (?RegistrationRequest $record): HtmlString|string => static::registrationPdfPlaceholderContent(
                                $record,
                                RegistrationRequestPdfService::DOCUMENT_REGISTRATION_REQUEST,
                                __('Download Registration Request PDF')
                            )),
                        Placeholder::make('generated_pdfs_license_request')
                            ->label(__('Practice license request form'))
                            ->content(fn (?RegistrationRequest $record): HtmlString|string => static::registrationPdfPlaceholderContent(
                                $record,
                                RegistrationRequestPdfService::DOCUMENT_LICENSE_REQUEST,
                                __('Download License Request PDF')
                            )),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('Personal Information'))
                    ->collapsible()
                    ->disabled(fn (?RegistrationRequest $record): bool => ! static::canEditMainData($record))
                    ->schema([
                        TextInput::make('national_id')
                            ->label(__('National ID'))
                            ->required()
                            ->maxLength(50)
                            ->rule(
                                'regex:/^\d{14}$/',
                                fn (Get $get): bool => static::isEgyptianNationalityId($get('nationality'))
                            ),
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
                    ->disabled(fn (?RegistrationRequest $record): bool => ! static::canEditMainData($record))
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
                        Select::make('residence_mobile_1_country_code')
                            ->label(__('Mobile 1 Country Code'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->options(CountryCodeOptions::options()),
                        TextInput::make('residence_mobile_1')
                            ->label(__('Mobile 1 Number'))
                            ->tel()
                            ->required()
                            ->minLength(11)
                            ->maxLength(11)
                            ->rule('digits:11')
                            ->placeholder(__('Enter 11-digit mobile number.'))
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn ($rule, Get $get) => $rule->where(
                                    'residence_mobile_1_country_code',
                                    $get('residence_mobile_1_country_code')
                                )
                            ),
                        Select::make('residence_mobile_2_country_code')
                            ->label(__('Mobile 2 Country Code'))
                            ->nullable()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->requiredWith('residence_mobile_2')
                            ->options(CountryCodeOptions::options()),
                        TextInput::make('residence_mobile_2')
                            ->label(__('Mobile 2 Number'))
                            ->tel()
                            ->nullable()
                            ->minLength(11)
                            ->maxLength(11)
                            ->rule('digits:11')
                            ->placeholder(__('Enter 11-digit mobile number.'))
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn ($rule, Get $get) => $rule->where(
                                    'residence_mobile_2_country_code',
                                    $get('residence_mobile_2_country_code')
                                )
                            ),
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
                    ->disabled(fn (?RegistrationRequest $record): bool => ! static::canEditMainData($record))
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
                    ->disabled(fn (?RegistrationRequest $record): bool => ! static::canEditMainData($record))
                    ->schema([
                        FileUpload::make('documents.personal_image')
                            ->label(__('Personal Photo'))
                            ->image()
                            ->required(fn (?RegistrationRequest $record): bool => static::shouldRequireSubmittedDocuments($record))
                            ->disk('public')
                            ->directory('documents')
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->maxSize(5120)
                            ->extraAttributes(['data-image-popup-preview' => '1'])
                            ->openable()
                            ->downloadable(),
                        FileUpload::make('documents.national_id_image')
                            ->label(__('National ID Photo'))
                            ->image()
                            ->required(fn (?RegistrationRequest $record): bool => static::shouldRequireSubmittedDocuments($record))
                            ->disk('public')
                            ->directory('documents')
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->maxSize(5120)
                            ->extraAttributes(['data-image-popup-preview' => '1'])
                            ->openable()
                            ->downloadable(),
                        FileUpload::make('documents.graduation_certificate_image')
                            ->label(__('Graduation Certificate'))
                            ->image()
                            ->required(fn (?RegistrationRequest $record): bool => static::shouldRequireSubmittedDocuments($record))
                            ->disk('public')
                            ->directory('documents')
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->maxSize(5120)
                            ->extraAttributes(['data-image-popup-preview' => '1'])
                            ->openable()
                            ->downloadable(),
                        FileUpload::make('documents.internship_certificate_image')
                            ->label(__('Internship Certificate'))
                            ->image()
                            ->required(fn (?RegistrationRequest $record): bool => static::shouldRequireSubmittedDocuments($record))
                            ->disk('public')
                            ->directory('documents')
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->maxSize(5120)
                            ->extraAttributes(['data-image-popup-preview' => '1'])
                            ->openable()
                            ->downloadable(),
                        FileUpload::make('documents.criminal_record_certificate_image')
                            ->label(__('Criminal Record Certificate'))
                            ->image()
                            ->required(fn (?RegistrationRequest $record): bool => static::shouldRequireSubmittedDocuments($record))
                            ->disk('public')
                            ->directory('documents')
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->maxSize(5120)
                            ->extraAttributes(['data-image-popup-preview' => '1'])
                            ->openable()
                            ->downloadable(),
                        FileUpload::make('documents.dob_image')
                            ->label(__('Date of Birth Certificate'))
                            ->image()
                            ->required(fn (?RegistrationRequest $record): bool => static::shouldRequireSubmittedDocuments($record))
                            ->disk('public')
                            ->directory('documents')
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->maxSize(5120)
                            ->extraAttributes(['data-image-popup-preview' => '1'])
                            ->openable()
                            ->downloadable(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('License Information'))
                    ->collapsible()
                    ->collapsed(fn (?RegistrationRequest $record): bool => ! static::shouldPrioritizeLicenseSection($record))
                    ->visible(fn () => static::canViewLicenseData())
                    ->disabled(fn (?RegistrationRequest $record): bool => ! static::canEditLicenseData($record))
                    ->schema([
                        TextInput::make('license_number')
                            ->label(__('License Number'))
                            ->numeric()
                            ->required(fn (?RegistrationRequest $record): bool => static::shouldRequireLicenseData($record))
                            ->maxLength(20)
                            ->rule('regex:/^\d+$/'),
                        DatePicker::make('license_date')
                            ->label(__('License Date'))
                            ->required(fn (?RegistrationRequest $record): bool => static::shouldRequireLicenseData($record)),
                        FileUpload::make('license_image')
                            ->label(__('License Image'))
                            ->required(
                                fn (?RegistrationRequest $record): bool => static::shouldRequireLicenseData($record)
                                    && blank($record?->license_image)
                            )
                            ->disk('public')
                            ->directory('documents')
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'application/pdf'])
                            ->maxSize(5120)
                            ->extraAttributes(['data-image-popup-preview' => '1'])
                            ->openable()
                            ->downloadable(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                ];

                if (static::shouldPrioritizeLicenseSection($record)) {
                    $licenseSection = array_pop($components);

                    if ($licenseSection !== null) {
                        array_unshift($components, $licenseSection);
                    }
                }

                return $components;
            });
    }

    private static function canEditMainData(?RegistrationRequest $record): bool
    {
        if (static::isSuperAdmin()) {
            return true;
        }

        if (! static::hasRole('reviewer')) {
            return false;
        }

        if (! $record) {
            return true;
        }

        return $record->status === RegistrationRequest::STATUS_PENDING_REVIEW;
    }

    private static function canViewLicenseData(): bool
    {
        return static::isSuperAdmin() || static::hasRole('review-supervisor');
    }

    private static function canEditLicenseData(?RegistrationRequest $record): bool
    {
        if (static::isSuperAdmin()) {
            return true;
        }

        if (! static::hasRole('review-supervisor')) {
            return false;
        }

        if (! $record) {
            return false;
        }

        return $record->status === RegistrationRequest::STATUS_PENDING_FINAL_APPROVAL;
    }

    private static function shouldRequireLicenseData(?RegistrationRequest $record): bool
    {
        if (! $record) {
            return false;
        }

        return $record->status === RegistrationRequest::STATUS_PENDING_FINAL_APPROVAL
            && (static::isSuperAdmin() || static::hasRole('review-supervisor'));
    }

    private static function shouldRequireSubmittedDocuments(?RegistrationRequest $record): bool
    {
        return static::canEditMainData($record);
    }

    private static function shouldPrioritizeLicenseSection(?RegistrationRequest $record): bool
    {
        if (! $record) {
            return false;
        }

        return $record->status === RegistrationRequest::STATUS_PENDING_FINAL_APPROVAL;
    }

    private static function isSuperAdmin(): bool
    {
        return static::hasRole('super_admin');
    }

    private static function hasRole(string $role): bool
    {
        $admin = static::currentAdmin();

        return $admin?->hasRole($role) ?? false;
    }

    private static function currentAdmin(): ?Admin
    {
        $user = Filament::auth()->user();

        return $user instanceof Admin ? $user : null;
    }

    private static function registrationPdfPlaceholderContent(
        ?RegistrationRequest $record,
        string $document,
        string $linkLabel
    ): HtmlString|string {
        $url = static::signedPdfUrl($record, $document);

        if (! $url) {
            return '-';
        }

        return new HtmlString(
            '<a class="registration-pdf-download-btn" href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . e($linkLabel) . '</a>'
        );
    }

    private static function signedPdfUrl(?RegistrationRequest $record, string $document): ?string
    {
        if (! $record || blank($record->reg_code)) {
            return null;
        }

        $expiration = now()->addMinutes((int) config('services.registration_documents.signed_url_ttl', 60));

        return URL::temporarySignedRoute('register-pdf-document', $expiration, [
            'reg_code' => $record->reg_code,
            'document' => $document,
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
        $query = $modelClass::query();
        $table = (new $modelClass())->getTable();

        if (DatabaseSchema::hasColumn($table, 'code')) {
            $query
                ->whereNotNull('code')
                ->orderBy('code');
        } else {
            $query->orderBy('id');
        }

        return $query
            ->get()
            ->mapWithKeys(function ($model) {
                $name = method_exists($model, 'getTranslation')
                    ? $model->getTranslation('name', app()->getLocale())
                    : ($model->name ?? (string) $model->id);

                return [$model->id => $name];
            })
            ->all();
    }

    private static function isEgyptianNationalityId(mixed $nationalityId): bool
    {
        if (! is_numeric($nationalityId)) {
            return false;
        }

        $nationality = Nationality::query()->find((int) $nationalityId);

        if (! $nationality) {
            return false;
        }

        if (in_array((int) $nationality->code, [1, 214], true)) {
            return true;
        }

        $nameAr = (string) $nationality->getTranslation('name', 'ar');
        $nameEn = (string) $nationality->getTranslation('name', 'en');

        return str_contains($nameAr, 'مصر') || stripos($nameEn, 'egypt') !== false;
    }
}
