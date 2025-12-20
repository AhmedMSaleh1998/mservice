<?php

namespace App\Filament\Resources\RegistrationRequests;

use App\Filament\Resources\RegistrationRequests\Pages\ListRegistrationRequests;
use App\Filament\Resources\RegistrationRequests\Pages\ViewRegistrationRequest;
use App\Models\RegistrationRequest;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Illuminate\Support\Facades\Storage;

class RegistrationRequestResource extends Resource
{
    protected static ?string $model = RegistrationRequest::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Registration Requests';

    public static function form(Form|\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->required()
                            ->disabled(),

                        Forms\Components\TextInput::make('national_id')
                            ->label('National ID')
                            ->required()
                            ->disabled(),

                        Forms\Components\TextInput::make('reg_code')
                            ->label('Registration Code')
                            ->disabled(),

                        Forms\Components\Toggle::make('active')
                            ->label('Active Status')
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
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('national_id')
                    ->label('National ID')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('reg_code')
                    ->label('Registration Code')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Status')
                    ->placeholder('All requests')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (RegistrationRequest $record) {
                        $record->update(['active' => true]);

                        \Filament\Notifications\Notification::make()
                            ->title('Registration Activated')
                            ->success()
                            ->send();
                    })
                    ->visible(fn(RegistrationRequest $record) => !$record->active),

                Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (RegistrationRequest $record) {
                        $record->update(['active' => false]);

                        \Filament\Notifications\Notification::make()
                            ->title('Registration Deactivated')
                            ->warning()
                            ->send();
                    })
                    ->visible(fn(RegistrationRequest $record) => $record->active),
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
                Section::make('Registration Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('phone')
                            ->label('Phone Number')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('national_id')
                            ->label('National ID')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('reg_code')
                            ->label('Registration Code')
                            ->badge()
                            ->color('primary'),

                        Infolists\Components\IconEntry::make('active')
                            ->label('Status')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Submitted At')
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Submitted Documents')
                    ->schema([
                        Infolists\Components\ViewEntry::make('documents')
                            ->label('')
                            ->view('filament.infolists.document-links')
                    ])
                ->columnSpanFull()
            ]);
    }

    protected static function getDocumentLabel($state): string
    {
        $labels = [
            'dob_image' => 'Date of Birth Certificate',
            'personal_image' => 'Personal Photo',
            'practice_exam_result_image' => 'Practice Exam Result',
            'practice_license_form_image' => 'Practice License Form',
            'graduation_certificate_image' => 'Graduation Certificate',
            'internship_certificate_image' => 'Internship Certificate',
            'military_service_status_image' => 'Military Service Status',
            'criminal_record_certificate_image' => 'Criminal Record Certificate',
            'syndicate_registration_form_image' => 'Syndicate Registration Form',
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