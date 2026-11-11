<?php

namespace App\Filament\Resources\Audits\Schemas;

use App\Enums\AuditResult;
use App\Models\Audit;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuditInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Sesi'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('room.nama')
                            ->label(__('Ruang'))
                            ->formatStateUsing(fn (Audit $record): string => "{$record->room->floor->building->nama} — {$record->room->floor->nama} — {$record->room->nama}"),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('creator.name')
                            ->label(__('Dibuat oleh'))
                            ->placeholder('—'),
                        TextEntry::make('mulai_pada')
                            ->label(__('Dimulai'))
                            ->dateTime('j M Y, H:i'),
                        TextEntry::make('selesai_pada')
                            ->label(__('Ditutup'))
                            ->dateTime('j M Y, H:i')
                            ->placeholder('—'),
                        TextEntry::make('closer.name')
                            ->label(__('Ditutup oleh'))
                            ->placeholder('—'),
                        TextEntry::make('catatan')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Ringkasan'))
                    ->columns(4)
                    ->schema(collect(AuditResult::cases())
                        ->map(fn (AuditResult $result): TextEntry => TextEntry::make("ringkasan_{$result->value}")
                            ->label($result->getLabel())
                            ->state(fn (Audit $record): int => $record->resultCounts()[$result->value])
                            ->badge()
                            ->color($result->getColor()))
                        ->all()),
            ]);
    }
}
