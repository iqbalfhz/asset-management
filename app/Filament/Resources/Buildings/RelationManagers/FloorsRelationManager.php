<?php

namespace App\Filament\Resources\Buildings\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

class FloorsRelationManager extends RelationManager
{
    protected static string $relationship = 'floors';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Lantai');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('building_id', $this->getOwnerRecord()->getKey())),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->modelLabel(__('Lantai'))
            ->pluralModelLabel(__('Lantai'))
            ->columns([
                TextColumn::make('nama')
                    ->searchable(),
                TextColumn::make('rooms_count')
                    ->label(__('Jumlah ruang'))
                    ->counts('rooms'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
