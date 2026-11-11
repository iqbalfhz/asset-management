<?php

namespace App\Filament\Pages;

use App\Enums\LabelField;
use App\Enums\LabelSize;
use App\Models\Audit;
use App\Models\Company;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 99;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Administrasi');
    }

    public static function getNavigationLabel(): string
    {
        return __('Pengaturan');
    }

    public function getTitle(): string
    {
        return __('Pengaturan');
    }

    public function mount(): void
    {
        $this->fillForCompany(auth()->user()->company_id);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('Semua perusahaan'))
                    ->schema([
                        Toggle::make('company_separation')
                            ->label(__('Pemisahan perusahaan'))
                            ->helperText(__('Kalau nyala, user hanya melihat data perusahaannya, kecuali yang punya izin lihat semua perusahaan.')),
                        Toggle::make('telegram_enabled')
                            ->label(__('Notifikasi Telegram')),
                    ]),
                Section::make(__('Per perusahaan'))
                    ->schema([
                        Select::make('company_id')
                            ->label(__('Perusahaan'))
                            ->options(fn (): array => Company::query()->orderBy('nama')->pluck('nama', 'id')->all())
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (?string $state) => $this->fillForCompany((int) $state)),
                        FileUpload::make('logo')
                            ->image()
                            // Tanpa SVG: SVG bisa membawa skrip saat dibuka di browser.
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->directory('logos')
                            ->maxSize(1024),
                        Textarea::make('kop_bast')
                            ->label(__('Kop BAST'))
                            ->rows(4),
                        TextInput::make('format_nomor_bast')
                            ->label(__('Format nomor BAST'))
                            ->helperText(__('Penanda yang tersedia: {KODE}, {TAHUN}, {BULAN}, {NOMOR}.'))
                            ->required()
                            ->regex('/\{NOMOR\}/')
                            ->validationMessages(['regex' => __('Format harus memuat {NOMOR} supaya setiap BAST punya nomor sendiri.')])
                            ->maxLength(100),
                        Select::make('label_ukuran')
                            ->label(__('Ukuran label aset'))
                            ->options(LabelSize::class)
                            ->required(),
                        CheckboxList::make('label_isi')
                            ->label(__('Isi label aset'))
                            ->helperText(__('Kode aset selalu tercetak. Untuk label kecil, pilih lebih sedikit.'))
                            ->options(LabelField::class)
                            ->columns(3),
                        TextInput::make('audit_interval_bulan')
                            ->label(__('Interval stock opname'))
                            ->helperText(__('Jadwal audit berikutnya = tanggal sesi ditutup + interval ini.'))
                            ->integer()
                            ->minValue(1)
                            ->maxValue(60)
                            ->suffix(__('bulan'))
                            ->required(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('Simpan'))
                                ->submit('save'),
                        ])->key('form-actions'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::put('company_separation', (bool) $data['company_separation']);
        Setting::put('telegram_enabled', (bool) $data['telegram_enabled']);

        foreach (['logo', 'kop_bast', 'format_nomor_bast', 'label_ukuran', 'label_isi'] as $key) {
            Setting::put($key, $data[$key] ?? null, (int) $data['company_id']);
        }

        Setting::put('audit_interval_bulan', (int) $data['audit_interval_bulan'], (int) $data['company_id']);

        Notification::make()->title(__('Pengaturan sudah disimpan.'))->success()->send();
    }

    // Ganti isian per perusahaan tanpa mengubah saklar global yang sedang diisi.
    private function fillForCompany(int $companyId): void
    {
        $this->form->fill([
            'company_separation' => $this->data['company_separation'] ?? (bool) Setting::get('company_separation', false),
            'telegram_enabled' => $this->data['telegram_enabled'] ?? (bool) Setting::get('telegram_enabled', false),
            'company_id' => $companyId,
            'logo' => Setting::get('logo', null, $companyId),
            'kop_bast' => Setting::get('kop_bast', null, $companyId),
            'format_nomor_bast' => Setting::get('format_nomor_bast', 'BAST/{KODE}/{TAHUN}/{NOMOR}', $companyId),
            'label_ukuran' => Setting::get('label_ukuran', LabelSize::Thermal50x25->value, $companyId),
            'label_isi' => Setting::get('label_isi', LabelField::defaults(), $companyId),
            'audit_interval_bulan' => Setting::get('audit_interval_bulan', Audit::DEFAULT_INTERVAL_MONTHS, $companyId),
        ]);
    }
}
