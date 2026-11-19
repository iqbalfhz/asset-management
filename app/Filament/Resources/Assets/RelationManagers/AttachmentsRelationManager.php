<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Models\Attachment;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return trans_choice('Lampiran|Lampiran', 2);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('path')
                    ->label(__('File'))
                    ->helperText(__('Faktur, kartu garansi, atau dokumen lain. Maksimal 10 MB.'))
                    ->disk(Attachment::DISK)
                    ->directory('attachments')
                    ->visibility('private')
                    ->storeFileNamesIn('nama_file')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'image/jpeg',
                        'image/png',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->maxSize(10240)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_file')
            ->modelLabel(__('Lampiran'))
            ->pluralModelLabel(trans_choice('Lampiran|Lampiran', 2))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nama_file')
                    ->label(__('Nama file'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('ukuran')
                    ->label(__('Ukuran'))
                    ->sortable()
                    ->formatStateUsing(fn (?int $state): string => Number::fileSize($state ?? 0)),
                TextColumn::make('uploader.name')
                    ->label(__('Diunggah oleh'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Tanggal'))
                    ->sortable()
                    ->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Unggah lampiran'))
                    ->mutateDataUsing(function (array $data): array {
                        $data['ukuran'] = Storage::disk(Attachment::DISK)->size($data['path']);
                        $data['diunggah_oleh'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('Unduh'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (Attachment $record): string => route('filament.admin.attachments.download', $record))
                    ->openUrlInNewTab(),
                DeleteAction::make(),
            ]);
    }
}
