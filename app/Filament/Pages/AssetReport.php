<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Department;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AssetReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?int $navigationSort = 13;

    protected static ?string $slug = 'laporan';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Asset::class) ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('Laporan');
    }

    public function getTitle(): string
    {
        return __('Laporan aset');
    }

    public function getSubheading(): string
    {
        return __('Jumlah, harga beli, dan nilai buku aset per kategori, status, dan departemen. Isinya sebatas aset yang boleh Anda lihat.');
    }

    public function mount(): void
    {
        $this->form->fill([
            'perusahaan' => auth()->user()->company_id,
            'tanggal' => today()->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(3)
            ->components([
                Select::make('perusahaan')
                    ->label(__('Perusahaan'))
                    ->options(fn (): array => Company::query()->visibleToCurrentUser()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->required(),
                Select::make('departemen')
                    ->label(__('Departemen'))
                    ->placeholder(__('Semua departemen'))
                    ->options(fn (): array => Department::query()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->searchable(),
                DatePicker::make('tanggal')
                    ->label(__('Nilai buku per tanggal'))
                    ->required(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('download')
                    ->footer([
                        Actions::make([
                            Action::make('download')
                                ->label(__('Unduh PDF'))
                                ->icon(Heroicon::OutlinedArrowDownTray)
                                ->submit('download'),
                        ])->key('form-actions'),
                    ]),
            ]);
    }

    public function download(): void
    {
        $this->redirect(route('filament.admin.reports.assets', array_filter($this->form->getState())));
    }
}
