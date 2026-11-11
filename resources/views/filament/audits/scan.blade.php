@php
    use App\Enums\AuditResult;

    $counts = $this->counts();
    $tones = [
        'success' => 'bg-success-50 text-success-800 ring-success-600/20 dark:bg-success-500/10 dark:text-success-300',
        'warning' => 'bg-warning-50 text-warning-800 ring-warning-600/25 dark:bg-warning-500/10 dark:text-warning-300',
        'gray' => 'bg-gray-100 text-gray-800 ring-gray-500/20 dark:bg-white/5 dark:text-gray-200',
        'danger' => 'bg-danger-50 text-danger-800 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-300',
    ];
@endphp

<x-filament-panels::page>
    <div class="grid gap-8 lg:grid-cols-2">
        <div class="flex flex-col gap-4">
            <div
                wire:ignore
                x-data="{
                    active: false,
                    starting: false,
                    error: null,
                    controls: null,
                    async start() {
                        this.error = null
                        this.starting = true
                        try {
                            const { startScanner } = await import(@js(Vite::asset('resources/js/audit-scanner.js')))
                            this.controls = await startScanner(this.$refs.video, (code) => $wire.scan(code))
                            this.active = true
                        } catch (error) {
                            this.error = @js(__('Kamera tidak bisa dibuka. Izinkan akses kamera di browser (alamatnya harus HTTPS), atau ketik kode di kolom bawah.'))
                        } finally {
                            this.starting = false
                        }
                    },
                    stop() {
                        this.controls?.stop()
                        this.controls = null
                        this.active = false
                    },
                }"
                x-on:pagehide.window="stop()"
                class="flex flex-col gap-3"
            >
                <div x-show="active || starting" x-cloak class="overflow-hidden rounded-xl bg-gray-950">
                    <video x-ref="video" class="aspect-[4/3] w-full object-cover" muted playsinline></video>
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-filament::button x-show="! active" x-on:click="start()" x-bind:disabled="starting" icon="heroicon-o-camera">
                        {{ __('Buka kamera') }}
                    </x-filament::button>
                    <x-filament::button x-show="active" x-cloak x-on:click="stop()" color="gray" icon="heroicon-o-x-mark">
                        {{ __('Tutup kamera') }}
                    </x-filament::button>
                </div>

                <p x-show="error" x-cloak x-text="error" class="text-sm text-danger-600 dark:text-danger-400"></p>
            </div>

            @if ($lastResult)
                <div role="status" class="rounded-lg p-4 ring-1 ring-inset {{ $tones[$lastResult['tone']] }}">
                    <p class="font-serif text-2xl font-semibold leading-tight">{{ $lastResult['title'] }}</p>
                    <p class="mt-1 text-sm">{{ $lastResult['body'] }}</p>
                </div>
            @endif

            <form wire:submit="submitCode" class="flex gap-2">
                <x-filament::input.wrapper class="flex-1">
                    <x-filament::input
                        type="text"
                        wire:model="code"
                        autocomplete="off"
                        maxlength="255"
                        :placeholder="__('Ketik kode atau pakai scanner barcode')"
                        :aria-label="__('Kode aset')"
                    />
                </x-filament::input.wrapper>
                <x-filament::button type="submit">
                    {{ __('Catat') }}
                </x-filament::button>
            </form>
        </div>

        <div class="flex flex-col gap-6">
            <dl class="grid grid-cols-3 gap-4">
                @foreach ([AuditResult::Ditemukan, AuditResult::SalahTempat, AuditResult::Menunggu] as $result)
                    <div class="border-t border-gray-300 pt-2 dark:border-white/15">
                        <dt class="text-sm text-gray-600 dark:text-gray-400">{{ $result->getLabel() }}</dt>
                        <dd class="font-serif text-3xl font-semibold text-gray-950 dark:text-white">{{ $counts[$result->value] }}</dd>
                    </div>
                @endforeach
            </dl>

            <section>
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Scan terakhir') }}</h2>
                <ul class="mt-2 divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($this->recentItems() as $item)
                        <li class="flex items-center justify-between gap-3 py-2 text-sm">
                            <span class="min-w-0 truncate">
                                <span class="font-medium text-gray-950 dark:text-white">{{ $item->asset->kode }}</span>
                                <span class="text-gray-600 dark:text-gray-400">{{ $item->asset->nama }}</span>
                            </span>
                            <x-filament::badge :color="$item->hasil->getColor()">
                                {{ $item->hasil->getLabel() }}
                            </x-filament::badge>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Belum ada aset yang discan.') }}</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-filament-panels::page>
