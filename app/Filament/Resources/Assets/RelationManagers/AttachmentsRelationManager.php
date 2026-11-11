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
        return __('Lampiran');
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
            ->pluralModelLabel(__('Lampiran'))
            ->columns([
                TextColumn::make('nama_file')
                    ->label(__('Nama file'))
                    ->searchable(),
                TextColumn::make('ukuran')
                    ->label(__('Ukuran'))
                    ->formatStateUsing(fn (?int $state): string => Number::fileSize($state ?? 0)),
                TextColumn::make('uploader.name')
                    ->label(__('Diunggah oleh')),
                TextColumn::make('created_at')
                    ->label(__('Tanggal'))
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
