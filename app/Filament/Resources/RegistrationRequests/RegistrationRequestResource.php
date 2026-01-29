<?php

namespace App\Filament\Resources\RegistrationRequests;

use App\Filament\Resources\RegistrationRequests\Pages\ListRegistrationRequests;
use App\Filament\Resources\RegistrationRequests\Pages\ViewRegistrationRequest;
use App\Models\RegistrationRequest;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Modules\Core\Models\Grade;
use Modules\Core\Models\Language;
use Modules\Core\Models\MedicalUniversity;
use Modules\Core\Models\Nationality;
use Modules\Core\Models\Province;

class RegistrationRequestResource extends Resource
{
    protected static ?string $model = RegistrationRequest::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Registration Requests';

    public static function getModelLabel(): string
    {
        return __('Registration Request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Registration Requests');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('Services');
    }

    public static function form(Form|\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->schema([
                Section::make(__('Basic Information'))
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label(__('Phone Number'))
                            ->tel()
                            ->required()
                            ->disabled(),

                        Forms\Components\TextInput::make('national_id')
                            ->label(__('National ID'))
                            ->required()
                            ->disabled(),

                        Forms\Components\TextInput::make('reg_code')
                            ->label(__('Registration Code'))
                            ->disabled(),

                        Forms\Components\Toggle::make('active')
                            ->label(__('Active Status'))
                            ->inline(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('national_id')
                    ->label(__('National ID'))
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('reg_code')
                    ->label(__('Registration Code'))
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('active')
                    ->label(__('Status'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Submitted At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label(__('Status'))
                    ->placeholder(__('All requests'))
                    ->trueLabel(__('Active only'))
                    ->falseLabel(__('Inactive only')),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('activate')
                        ->label(__('Activate'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (RegistrationRequest $record) {
                            $record->update(['active' => true]);

                            \Filament\Notifications\Notification::make()
                                ->title(__('Registration Activated'))
                                ->success()
                                ->send();
                        })
                        ->visible(fn(RegistrationRequest $record) => !$record->active),
                    Action::make('deactivate')
                        ->label(__('Deactivate'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (RegistrationRequest $record) {
                            $record->update(['active' => false]);

                            \Filament\Notifications\Notification::make()
                                ->title(__('Registration Deactivated'))
                                ->warning()
                                ->send();
                        })
                        ->visible(fn(RegistrationRequest $record) => $record->active),
                ]),
            ])
//            ->bulkActions([
//                Tables\Actions\BulkActionGroup::make([
//                    Tables\Actions\DeleteBulkAction::make(),
//
//                    Tables\Actions\BulkAction::make('activate')
//                        ->label('Activate Selected')
//                        ->icon('heroicon-o-check-circle')
//                        ->color('success')
//                        ->requiresConfirmation()
//                        ->action(function ($records) {
//                            $records->each->update(['active' => true]);
//
//                            \Filament\Notifications\Notification::make()
//                                ->title('Registrations Activated')
//                                ->success()
//                                ->send();
//                        }),
//                ]),
//            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->schema([
                Section::make(__('Personal Information'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Infolists\Components\TextEntry::make('national_id')
                            ->label(__('National ID'))
                            ->copyable(),

                        Infolists\Components\TextEntry::make('full_name_ar')
                            ->label(__('Full Name (AR)')),

                        Infolists\Components\TextEntry::make('full_name_en')
                            ->label(__('Full Name (EN)')),

                        Infolists\Components\TextEntry::make('gender')
                            ->label(__('Gender')),

                        Infolists\Components\TextEntry::make('nationality')
                            ->label(__('Nationality'))
                            ->formatStateUsing(fn ($state) => static::getLookupName($state, Nationality::class)),

                        Infolists\Components\TextEntry::make('religion')
                            ->label(__('Religion')),

                        Infolists\Components\TextEntry::make('issued_from')
                            ->label(__('Issued From')),

                        Infolists\Components\TextEntry::make('governorate')
                            ->label(__('Governorate'))
                            ->formatStateUsing(fn ($state) => static::getLookupName($state, Province::class)),

                        Infolists\Components\TextEntry::make('birth_governorate')
                            ->label(__('Birth Governorate'))
                            ->formatStateUsing(fn ($state) => static::getLookupName($state, Province::class)),

                        Infolists\Components\TextEntry::make('birth_date')
                            ->label(__('Birth Date'))
                            ->date(),

                        Infolists\Components\IconEntry::make('active')
                            ->label(__('Status'))
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label(__('Submitted At'))
                            ->dateTime(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make(__('Residence Information'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Infolists\Components\TextEntry::make('residence_house_number')
                            ->label(__('House Number')),
                        Infolists\Components\TextEntry::make('residence_street')
                            ->label(__('Street')),
                        Infolists\Components\TextEntry::make('residence_center')
                            ->label(__('Center')),
                        Infolists\Components\TextEntry::make('residence_governorate')
                            ->label(__('Residence Governorate'))
                            ->formatStateUsing(fn ($state) => static::getLookupName($state, Province::class)),
                        Infolists\Components\TextEntry::make('residence_phone')
                            ->label(__('Phone')),
                        Infolists\Components\TextEntry::make('residence_mobile_1')
                            ->label(__('Mobile 1')),
                        Infolists\Components\TextEntry::make('residence_mobile_2')
                            ->label(__('Mobile 2')),
                        Infolists\Components\TextEntry::make('email')
                            ->label(__('Email')),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make(__('Academic Information'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Infolists\Components\TextEntry::make('faculty')
                            ->label(__('Faculty')),
                        Infolists\Components\TextEntry::make('university')
                            ->label(__('University'))
                            ->formatStateUsing(fn ($state) => static::getLookupName($state, MedicalUniversity::class)),
                        Infolists\Components\TextEntry::make('graduation_year')
                            ->label(__('Graduation Year')),
                        Infolists\Components\TextEntry::make('graduation_month')
                            ->label(__('Graduation Month')),
                        Infolists\Components\TextEntry::make('grade')
                            ->label(__('Grade'))
                            ->formatStateUsing(fn ($state) => static::getLookupName($state, Grade::class)),
                        Infolists\Components\TextEntry::make('first_foreign_language')
                            ->label(__('First Foreign Language'))
                            ->formatStateUsing(fn ($state) => static::getLookupName($state, Language::class)),
                        Infolists\Components\TextEntry::make('second_foreign_language')
                            ->label(__('Second Foreign Language'))
                            ->formatStateUsing(fn ($state) => static::getLookupName($state, Language::class)),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make(__('Submitted Documents'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Infolists\Components\ViewEntry::make('documents')
                            ->label('')
                            ->view('filament.infolists.document-links')
                    ])
                ->columnSpanFull()
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

    protected static function getDocumentLabel($state): string
    {
        $labels = [
            'personal_image' => 'Personal Photo',
            'national_id_image' => 'National ID Photo',
            'graduation_certificate_image' => 'Graduation Certificate',
            'internship_certificate_image' => 'Internship Certificate',
            'criminal_record_certificate_image' => 'Criminal Record Certificate',
            'dob_image' => 'Date of Birth Certificate',
        ];

        foreach ($labels as $key => $label) {
            if (str_contains($state, $key)) {
                return $label;
            }
        }

        return 'Document';
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
            'index' => ListRegistrationRequests::route('/'),
            'view' => ViewRegistrationRequest::route('/{record}'),
        ];
    }
}
