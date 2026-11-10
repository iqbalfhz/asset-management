<?php

namespace App\Filament\Resources\Employees;

use App\Filament\Resources\Employees\Pages\ManageEmployees;
use App\Filament\Schemas\Components\CompanySelect;
use App\Models\Employee;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function getModelLabel(): string
    {
        return __('Karyawan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Karyawan');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Organisasi');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                CompanySelect::make(),
                TextInput::make('nik')
                    ->label(__('NIK'))
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('company_id', $get('company_id'))),
                TextInput::make('nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('jabatan')
                    ->maxLength(255),
                Select::make('department_id')
                    ->label(__('Departemen'))
                    ->relationship('department', 'nama', modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->where('company_id', $get('company_id')))
                    ->searchable()
                    ->preload(),
                Select::make('user_id')
                    ->label(__('Akun login'))
                    ->helperText(__('Boleh kosong; tidak semua karyawan punya akun.'))
                    ->relationship('user', 'name', modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->where('company_id', $get('company_id')))
                    ->getOptionLabelFromRecordUsing(fn (User $record): string => "{$record->name} ({$record->username})")
                    ->searchable(['name', 'username'])
                    ->unique(ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->columns([
                TextColumn::make('nik')
                    ->label(__('NIK'))
                    ->searchable(),
                TextColumn::make('nama')
                    ->searchable(),
                TextColumn::make('jabatan')
                    ->searchable(),
                TextColumn::make('department.nama')
                    ->label(__('Departemen')),
                TextColumn::make('user.username')
                    ->label(__('Akun login')),
                TextColumn::make('company.kode')
                    ->label(__('Perusahaan'))
                    ->toggleable(isToggledHiddenByDefault: true),
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

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployees::route('/'),
        ];
    }
}
