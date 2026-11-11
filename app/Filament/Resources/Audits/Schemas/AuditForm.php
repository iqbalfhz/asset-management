<?php

namespace App\Filament\Resources\Audits\Schemas;

use App\Models\Room;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AuditForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('room_id')
                    ->label(__('Ruang'))
                    ->helperText(__('Aset yang tercatat di ruang ini dan Anda kelola menjadi daftar yang diharapkan.'))
                    ->options(fn (): array => Room::query()->with('floor.building')->get()
                        ->mapWithKeys(fn (Room $room): array => [$room->id => "{$room->floor->building->nama} — {$room->floor->nama} — {$room->nama}"])
                        ->all())
                    ->searchable()
                    ->required(),
                Textarea::make('catatan')
                    ->label(__('Catatan (opsional)'))
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }
}
