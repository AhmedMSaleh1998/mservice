<?php

namespace App\Filament\Resources\SmsMessages\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Modules\Core\Models\SmsMessage;

class SmsMessageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('SMS Message'))
                    ->schema([
                        TextEntry::make('id')
                            ->label(__('ID')),
                        TextEntry::make('type')
                            ->label(__('Type'))
                            ->badge()
                            ->placeholder('-'),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->color(fn (?string $state): string => static::statusColor($state)),
                        TextEntry::make('provider_status')
                            ->label(__('Provider Status'))
                            ->badge()
                            ->placeholder('-'),
                        TextEntry::make('message_id')
                            ->label(__('SMS ID'))
                            ->copyable()
                            ->columnSpan(2),
                        TextEntry::make('sender')
                            ->label(__('Sender'))
                            ->placeholder('-'),
                        TextEntry::make('receiver')
                            ->label(__('Receiver'))
                            ->copyable()
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime(),
                        TextEntry::make('sent_at')
                            ->label(__('Sent At'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('last_status_at')
                            ->label(__('Last Status At'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('delivered_at')
                            ->label(__('Delivered At'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('failed_at')
                            ->label(__('Failed At'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('message')
                            ->label(__('Message'))
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('Metadata'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('metadata')
                            ->label(__('Metadata'))
                            ->getStateUsing(fn (SmsMessage $record): ?HtmlString => static::prettyJson($record->metadata))
                            ->placeholder(__('No metadata available.'))
                            ->copyable()
                            ->html()
                            ->columnSpanFull(),
                        TextEntry::make('dlr_payload')
                            ->label(__('DLR Payload'))
                            ->getStateUsing(fn (SmsMessage $record): ?HtmlString => static::prettyJson($record->dlr_payload))
                            ->placeholder(__('No DLR payload available.'))
                            ->copyable()
                            ->html()
                            ->columnSpanFull(),
                        TextEntry::make('response_body')
                            ->label(__('Provider Response'))
                            ->placeholder(__('No provider response available.'))
                            ->copyable()
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    private static function statusColor(?string $state): string
    {
        return match ($state) {
            'pending' => 'warning',
            'accepted' => 'info',
            'delivered' => 'success',
            'failed' => 'danger',
            'reported' => 'gray',
            default => 'gray',
        };
    }

    private static function prettyJson(mixed $value): ?HtmlString
    {
        if (blank($value)) {
            return null;
        }

        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return null;
        }

        return new HtmlString('<pre style="white-space: pre-wrap;">' . e($json) . '</pre>');
    }
}
