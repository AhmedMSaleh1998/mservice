<?php

namespace App\Filament\Resources\RestUnitBookings;

use App\Filament\Resources\ResetUnits\ResetUnitResource;
use App\Filament\Resources\RestUnitBookings\Pages;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\RestUnitBookings\Schemas\RestUnitBookingInfolist;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Modules\Services\Models\RestUnit;
use Modules\Services\Models\RestUnitBed;
use Modules\Services\Models\RestUnitBooking;
use Modules\Services\Models\RestUnitRoom;

class RestUnitBookingResource extends Resource
{
    protected static ?string $model = RestUnitBooking::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::CalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 21;

    public static function getModelLabel(): string
    {
        return __('Rest Unit Booking');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Rest Unit Bookings');
    }

    public static function getNavigationLabel(): string
    {
        return __('Bookings');
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return null;
    }

    public static function getNavigationParentItem(): ?string
    {
        return ResetUnitResource::getNavigationLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Beneficiary'))
                    ->schema([
                        Select::make('beneficiary_type')
                            ->label(__('Beneficiary type'))
                            ->options(RestUnitBooking::beneficiaryTypeOptions())
                            ->default(RestUnitBooking::BENEFICIARY_MEMBER)
                            ->required()
                            ->live()
                            ->native(false)
                            ->helperText(__('Martyr families are booked by national ID without picking a member.'))
                            ->columnSpanFull(),

                        Select::make('user_id')
                            ->label(__('Member'))
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('beneficiary_type') !== RestUnitBooking::BENEFICIARY_MARTYR_FAMILY)
                            ->required(fn (Get $get): bool => $get('beneficiary_type') !== RestUnitBooking::BENEFICIARY_MARTYR_FAMILY)
                            ->columnSpanFull(),

                        TextInput::make('beneficiary_card_number')
                            ->label(__('National ID'))
                            ->visible(fn (Get $get): bool => $get('beneficiary_type') === RestUnitBooking::BENEFICIARY_MARTYR_FAMILY)
                            ->required(fn (Get $get): bool => $get('beneficiary_type') === RestUnitBooking::BENEFICIARY_MARTYR_FAMILY)
                            ->columnSpanFull(),
                        TextInput::make('beneficiary_name')
                            ->label(__('Beneficiary name (optional)'))
                            ->visible(fn (Get $get): bool => $get('beneficiary_type') === RestUnitBooking::BENEFICIARY_MARTYR_FAMILY)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make(__('Booking'))
                    ->schema([
                        DatePicker::make('start_date')
                            ->label(__('Start Date'))
                            ->required()
                            ->native(false)
                            ->displayFormat('d-m-Y')
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('rest_unit_room_ids', []);
                                $set('rest_unit_bed_ids', []);
                                $set('total_price', 0);
                            }),
                        DatePicker::make('end_date')
                            ->label(__('End Date'))
                            ->required()
                            ->native(false)
                            ->displayFormat('d-m-Y')
                            ->after('start_date')
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('rest_unit_room_ids', []);
                                $set('rest_unit_bed_ids', []);
                                $set('total_price', 0);
                            }),
                        Select::make('rest_unit_id')
                            ->label(__('Rest Unit'))
                            ->relationship('restUnit', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('rest_unit_room_ids', []);
                                $set('rest_unit_bed_ids', []);
                                $set('total_price', 0);
                            })
                            ->helperText(__('Choose the dates first, then available units appear below.'))
                            ->columnSpanFull(),
                        Select::make('rest_unit_room_ids')
                            ->label(__('Rooms'))
                            ->multiple()
                            ->options(fn (Get $get, ?RestUnitBooking $record): array => self::roomOptions($get('rest_unit_id'), $get('start_date'), $get('end_date'), $record?->id))
                            ->visible(fn (Get $get): bool => self::unitType($get('rest_unit_id')) === RestUnit::TYPE_ROOMS && self::datesChosen($get('start_date'), $get('end_date')))
                            ->required(fn (Get $get): bool => self::unitType($get('rest_unit_id')) === RestUnit::TYPE_ROOMS)
                            ->searchable()->native(false)
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => $set('total_price', self::calculateAmountFromGet($get)))
                            ->helperText(__('Only rooms available in the selected period are shown — a booking is created for each.'))
                            ->columnSpanFull(),
                        Select::make('rest_unit_bed_ids')
                            ->label(__('Beds'))
                            ->multiple()
                            ->options(fn (Get $get, ?RestUnitBooking $record): array => self::bedOptions($get('rest_unit_id'), $get('start_date'), $get('end_date'), $record?->id))
                            ->visible(fn (Get $get): bool => self::unitType($get('rest_unit_id')) === RestUnit::TYPE_BEDS && self::datesChosen($get('start_date'), $get('end_date')))
                            ->required(fn (Get $get): bool => self::unitType($get('rest_unit_id')) === RestUnit::TYPE_BEDS)
                            ->searchable()->native(false)
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => $set('total_price', self::calculateAmountFromGet($get)))
                            ->helperText(__('Only beds available in the selected period are shown — a booking is created for each.'))
                            ->columnSpanFull(),
                        Placeholder::make('choose_dates_hint')
                            ->hiddenLabel()
                            ->content(__('Choose the start and end dates to see the available units.'))
                            ->visible(fn (Get $get): bool => filled($get('rest_unit_id')) && ! self::datesChosen($get('start_date'), $get('end_date')))
                            ->columnSpanFull(),
                        Select::make('payment_method')
                            ->label(__('Payment method'))
                            ->options(RestUnitBooking::paymentMethodOptions())
                            ->default(RestUnitBooking::PAYMENT_CASH)
                            ->required()
                            ->live()
                            ->native(false)
                            ->helperText(__('Cash / bank transfer are recorded as already paid.')),
                        TextInput::make('total_price')
                            ->label(__('Amount'))
                            ->numeric()
                            ->prefix('EGP')
                            ->default(0)
                            ->required()
                            ->readOnly()
                            ->helperText(__('Calculated automatically: nightly price × nights × selected units.')),
                        Select::make('status')
                            ->label(__('Status'))
                            ->options(self::statusOptions())
                            ->default(RestUnitBooking::STATUS_PENDING_PAYMENT)
                            ->required()
                            ->visibleOn('edit'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make(__('Payment proof'))
                    ->description(__('For cash / bank transfer, enter the transaction number; the transfer image is optional.'))
                    ->visible(fn (Get $get): bool => in_array($get('payment_method'), RestUnitBooking::offlinePaymentMethods(), true))
                    ->schema([
                        TextInput::make('payment_reference')
                            ->label(__('Transaction number'))
                            ->required()
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('payment_receipt')
                            ->label(__('Transfer image (optional)'))
                            ->collection(RestUnitBooking::RECEIPT_COLLECTION)
                            ->image()
                            ->downloadable()
                            ->previewable()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function unitType($restUnitId): ?string
    {
        return $restUnitId ? RestUnit::query()->find($restUnitId)?->type : null;
    }

    public static function datesChosen($from, $to): bool
    {
        return filled($from) && filled($to);
    }

    public static function calculateAmountFromGet(Get $get): float
    {
        return self::calculateAmount(
            $get('start_date'),
            $get('end_date'),
            $get('rest_unit_id'),
            (array) $get('rest_unit_room_ids'),
            (array) $get('rest_unit_bed_ids'),
        );
    }

    /** Amount = nightly price × nights × number of selected units (rooms sum their own prices). */
    public static function calculateAmount($from, $to, $restUnitId, array $roomIds = [], array $bedIds = []): float
    {
        if (! self::datesChosen($from, $to)) {
            return 0.0;
        }

        try {
            $nights = max(Carbon::parse((string) $from)->diffInDays(Carbon::parse((string) $to)), 1);
        } catch (\Throwable) {
            return 0.0;
        }

        $unit = $restUnitId ? RestUnit::query()->with('rooms')->find($restUnitId) : null;
        if (! $unit) {
            return 0.0;
        }

        if ($unit->isRooms()) {
            $ids = array_filter(array_map('intval', $roomIds));

            return (float) $unit->rooms->whereIn('id', $ids)->sum('price') * $nights;
        }

        if ($unit->isBeds()) {
            $count = count(array_filter($bedIds));

            return (float) $unit->price * $nights * $count;
        }

        return (float) $unit->price * $nights;
    }

    /** IDs of units already booked (blocking) for the given period, excluding a booking. */
    public static function occupiedUnitIds(int $restUnitId, string $column, ?string $from, ?string $to, ?int $excludeBookingId = null): array
    {
        if (! self::datesChosen($from, $to)) {
            return [];
        }

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->startOfDay();

        // Checkout day is exclusive: a stay ending on day X frees day X for the
        // next guest. A single-day range still counts as one night's stay.
        if ($toDate->lessThanOrEqualTo($fromDate)) {
            $toDate = $fromDate->copy()->addDay();
        }

        return RestUnitBooking::query()
            ->where('rest_unit_id', $restUnitId)
            ->whereNotNull($column)
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->whereNotIn('status', [RestUnitBooking::STATUS_CANCELLED, RestUnitBooking::STATUS_PAYMENT_EXPIRED])
            ->whereDate('start_date', '<', $toDate->toDateString())
            ->whereDate('end_date', '>', $fromDate->toDateString())
            ->pluck($column)
            ->filter()
            ->map(fn ($v): int => (int) $v)
            ->all();
    }

    private static function roomOptions($restUnitId, $from = null, $to = null, ?int $excludeId = null): array
    {
        $unit = $restUnitId ? RestUnit::query()->with('rooms.roomType')->find($restUnitId) : null;

        if (! $unit || ! $unit->isRooms()) {
            return [];
        }

        $occupied = self::occupiedUnitIds((int) $restUnitId, 'rest_unit_room_id', $from, $to, $excludeId);

        return $unit->rooms
            ->where('status', RestUnitRoom::STATUS_IN_SERVICE)
            ->reject(fn (RestUnitRoom $room): bool => in_array($room->id, $occupied, true))
            ->mapWithKeys(fn (RestUnitRoom $room): array => [$room->id => $room->label()])
            ->all();
    }

    private static function bedOptions($restUnitId, $from = null, $to = null, ?int $excludeId = null): array
    {
        $unit = $restUnitId ? RestUnit::query()->with('beds')->find($restUnitId) : null;

        if (! $unit || ! $unit->isBeds()) {
            return [];
        }

        $occupied = self::occupiedUnitIds((int) $restUnitId, 'rest_unit_bed_id', $from, $to, $excludeId);

        return $unit->beds
            ->where('status', RestUnitBed::STATUS_IN_SERVICE)
            ->reject(fn (RestUnitBed $bed): bool => in_array($bed->id, $occupied, true))
            ->mapWithKeys(fn (RestUnitBed $bed): array => [$bed->id => $bed->label])
            ->all();
    }

    /** Reject the save if any selected unit was taken in the meantime (prevents double-booking). */
    public static function assertUnitsAvailable(array $formState, ?int $excludeBookingId = null): void
    {
        $restUnitId = (int) ($formState['rest_unit_id'] ?? 0);
        $from = $formState['start_date'] ?? null;
        $to = $formState['end_date'] ?? null;
        $selected = self::resolveSelectedUnits($formState);

        if (! $selected['col'] || ! $restUnitId || ! self::datesChosen($from, $to)) {
            return;
        }

        $occupied = self::occupiedUnitIds($restUnitId, $selected['col'], $from, $to, $excludeBookingId);
        $clash = array_intersect($selected['ids'], $occupied);

        if ($clash !== []) {
            $field = $selected['col'] === 'rest_unit_room_id' ? 'rest_unit_room_ids' : 'rest_unit_bed_ids';
            throw ValidationException::withMessages([
                $field => __('Some of the selected units are already booked for this period. Please refresh and pick available ones.'),
            ]);
        }
    }

    public static function infolist(Schema $schema): Schema
    {
        return RestUnitBookingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label(__('ID'))->sortable(),
                TextColumn::make('user.name')
                    ->label(__('User'))
                    // One column for both: the account holder, with the actual
                    // guest underneath only when it is a different person
                    // (martyr-family bookings).
                    ->state(fn (RestUnitBooking $record): ?string => $record->user?->name ?? $record->guestName())
                    ->description(function (RestUnitBooking $record): ?string {
                        $guest = $record->guestName();
                        $shown = $record->user?->name ?? $guest;

                        return ($guest && $guest !== $shown) ? __('Guest') . ': ' . $guest : null;
                    })
                    ->searchable(query: fn ($query, string $search) => $query
                        ->where(function ($q) use ($search): void {
                            $q->whereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                                ->orWhere('beneficiary_name', 'like', "%{$search}%");
                        }))
                    ->placeholder('-')
                    ->color('info')
                    ->url(fn (RestUnitBooking $record): ?string => $record->user_id
                        ? UserResource::getUrl('edit', ['record' => $record->user_id])
                        : null)
                    ->openUrlInNewTab(),
                TextColumn::make('user.reg_number')
                    ->label(__('Registration Number'))
                    ->searchable()
                    ->sortable()
                    ->placeholder('-')
                    ->color('info')
                    ->url(fn (RestUnitBooking $record): ?string => $record->user_id
                        ? UserResource::getUrl('edit', ['record' => $record->user_id])
                        : null)
                    ->openUrlInNewTab(),
                TextColumn::make('restUnit.name')
                    ->label(__('Rest Unit'))
                    ->searchable()
                    ->placeholder('-')
                    ->color('info')
                    ->url(fn (RestUnitBooking $record): ?string => $record->rest_unit_id
                        ? ResetUnitResource::getUrl('view', ['record' => $record->rest_unit_id])
                        : null)
                    ->openUrlInNewTab(),
                TextColumn::make('beneficiary_type')
                    ->label(__('Beneficiary'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => RestUnitBooking::beneficiaryTypeOptions()[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => $state === RestUnitBooking::BENEFICIARY_MARTYR_FAMILY ? 'warning' : 'gray'),
                TextColumn::make('target')
                    ->label(__('Type'))
                    ->badge()
                    ->state(fn (RestUnitBooking $record): string => $record->targetLabel())
                    ->color('info'),
                TextColumn::make('total_price')
                    ->label(__('Total Amount'))
                    ->money(currency: 'EGP')
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label(__('Period'))
                    // Both dates always in full, with an arrow between them.
                    // The LTR-isolate marks keep the RTL page from reshuffling
                    // the digits, so day/month/year stay in fixed positions.
                    ->state(function (RestUnitBooking $record): string {
                        $start = $record->start_date;
                        $end = $record->end_date;

                        if (! $start || ! $end) {
                            $single = $start ?? $end;

                            return $single ? "\u{2066}" . $single->format('j/n/Y') . "\u{2069}" : '-';
                        }

                        return "\u{2066}" . $start->format('j/n/Y') . ' → ' . $end->format('j/n/Y') . "\u{2069}";
                    })
                    ->description(function (RestUnitBooking $record): ?string {
                        if (! $record->start_date || ! $record->end_date) {
                            return null;
                        }

                        $nights = (int) $record->start_date->diffInDays($record->end_date);

                        return $nights > 0 ? $nights . ' ' . __('Nights') : null;
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::getStatusLabel($state))
                    ->color(fn (?string $state): string => self::getStatusColor($state)),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(self::statusOptions())
                    // The page opens on successfully paid bookings; clearing
                    // the filter shows everything.
                    ->default(RestUnitBooking::STATUS_PAID_SUCCESSFULLY)
                    // A live search (e.g. by registration number) must span
                    // every status, so the filter steps aside while searching.
                    ->query(function ($query, array $data, $livewire) {
                        $value = $data['value'] ?? null;

                        if (! filled($value) || filled($livewire->getTableSearch())) {
                            return $query;
                        }

                        return $query->where('status', $value);
                    }),
                SelectFilter::make('beneficiary_type')
                    ->label(__('Beneficiary'))
                    ->options(RestUnitBooking::beneficiaryTypeOptions()),
                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('from_date')->label(__('From Date')),
                        DatePicker::make('to_date')->label(__('To Date')),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from_date'] ?? null, fn ($q, $date) => $q->where('end_date', '>=', $date))
                            ->when($data['to_date'] ?? null, fn ($q, $date) => $q->where('start_date', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from_date'] ?? null) {
                            $indicators[] = __('From: :date', ['date' => $data['from_date']]);
                        }
                        if ($data['to_date'] ?? null) {
                            $indicators[] = __('To: :date', ['date' => $data['to_date']]);
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('cancel')
                        ->label(__('Cancel booking'))
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->modalHeading(__('Cancel booking'))
                        ->modalSubmitActionLabel(__('Confirm cancellation'))
                        ->visible(fn (RestUnitBooking $record): bool => ! in_array($record->status, [RestUnitBooking::STATUS_CANCELLED, RestUnitBooking::STATUS_PAYMENT_EXPIRED], true))
                        ->schema([
                            Placeholder::make('refund_notice')
                                ->hiddenLabel()
                                ->content(fn (RestUnitBooking $record): HtmlString => new HtmlString(
                                    '<span style="color:#b45309;font-weight:600">⚠ '.e(__('This booking was paid online via :method — the amount must be refunded to the beneficiary.', ['method' => $record->paymentMethodLabel() ?? __('an online method')])).'</span>'
                                ))
                                ->visible(fn (RestUnitBooking $record): bool => $record->requiresOnlineRefund()),
                            Textarea::make('cancellation_reason')
                                ->label(__('Cancellation reason'))
                                ->required()
                                ->rows(3),
                        ])
                        ->action(fn (RestUnitBooking $record, array $data) => $record->cancel($data['cancellation_reason'] ?? null))
                        ->successNotificationTitle(__('Booking cancelled')),
                ]),
            ]);
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
            'index' => Pages\ListRestUnitBookings::route('/'),
            'create' => Pages\CreateRestUnitBooking::route('/create'),
            'view' => Pages\ViewRestUnitBooking::route('/{record}'),
            'edit' => Pages\EditRestUnitBooking::route('/{record}/edit'),
        ];
    }

    /**
     * Dashboard bookings: martyr-family clears the member; the payment method drives the status.
     * Cash / bank transfer are recorded as paid; Fawry stays pending until the online link is paid.
     */
    public static function applyBookingDefaults(array $data): array
    {
        if (($data['beneficiary_type'] ?? null) === RestUnitBooking::BENEFICIARY_MARTYR_FAMILY) {
            $data['user_id'] = null;
        }

        $method = $data['payment_method'] ?? null;

        if (in_array($method, RestUnitBooking::offlinePaymentMethods(), true)) {
            $data['status'] = RestUnitBooking::STATUS_PAID_SUCCESSFULLY;
            $data['paid_at'] = $data['paid_at'] ?? now();
        } elseif ($method === RestUnitBooking::PAYMENT_FAWRY) {
            $data['status'] = RestUnitBooking::STATUS_PENDING_PAYMENT;
            $data['paid_at'] = null;
        }

        return $data;
    }

    /**
     * @return array{col: ?string, ids: array<int, int>}
     */
    public static function resolveSelectedUnits(array $formState): array
    {
        $roomIds = array_values(array_filter(array_map('intval', (array) ($formState['rest_unit_room_ids'] ?? []))));
        $bedIds = array_values(array_filter(array_map('intval', (array) ($formState['rest_unit_bed_ids'] ?? []))));

        if ($roomIds !== []) {
            return ['col' => 'rest_unit_room_id', 'ids' => $roomIds];
        }

        if ($bedIds !== []) {
            return ['col' => 'rest_unit_bed_id', 'ids' => $bedIds];
        }

        return ['col' => null, 'ids' => []];
    }

    /**
     * Clone the main booking for each extra selected unit (amount stays on the first booking).
     *
     * @param  array<int, int>  $extraIds
     */
    public static function replicateForExtraUnits(RestUnitBooking $main, string $col, array $extraIds): void
    {
        foreach ($extraIds as $id) {
            $attributes = collect($main->getAttributes())
                ->only([
                    'rest_unit_id', 'user_id', 'beneficiary_type', 'beneficiary_name',
                    'beneficiary_card_number', 'beneficiary_reg_number', 'payment_reference',
                    'start_date', 'end_date', 'status', 'unit_type', 'paid_at',
                ])
                ->all();
            $attributes[$col] = $id;
            $attributes['total_price'] = 0;

            RestUnitBooking::query()->create($attributes);
        }
    }

    public static function statusOptions(): array
    {
        return [
            RestUnitBooking::STATUS_PENDING_PAYMENT => __('Pending Payment'),
            RestUnitBooking::STATUS_PAID_SUCCESSFULLY => __('Paid Successfully'),
            RestUnitBooking::STATUS_PAYMENT_EXPIRED => __('Payment Expired'),
            RestUnitBooking::STATUS_CANCELLED => __('Cancelled'),
        ];
    }

    public static function getStatusLabel(?string $state): string
    {
        return match ($state) {
            'checkout_pending' => __('Checkout Pending'),
            default => static::statusOptions()[$state ?? ''] ?? (string) $state,
        };
    }

    public static function getStatusColor(?string $state): string
    {
        return match ($state) {
            RestUnitBooking::STATUS_PENDING_PAYMENT => 'warning',
            'checkout_pending' => 'info',
            RestUnitBooking::STATUS_PAID_SUCCESSFULLY => 'success',
            RestUnitBooking::STATUS_PAYMENT_EXPIRED => 'gray',
            RestUnitBooking::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }
}
