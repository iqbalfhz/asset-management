<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-2">
        @foreach ($types as $type)
            <x-filament::badge :color="$type->getColor()">{{ $type->getLabel() }}</x-filament::badge>
        @endforeach
    </div>

    @if (! $entries->keys()->contains(fn (string $date): bool => str_starts_with($date, $month->format('Y-m'))))
        <p class="text-sm text-gray-500 md:hidden dark:text-gray-400">{{ __('Tidak ada jadwal di bulan ini.') }}</p>
    @endif

    {{-- Layar lebar: kisi Senin–Minggu. Layar kecil: hanya tanggal bulan ini yang berisi jadwal, berurutan ke bawah. --}}
    <div class="grid gap-px overflow-hidden rounded-[var(--kertas-radius-box)] bg-gray-950/[0.06] ring-1 ring-gray-950/[0.06] md:grid-cols-7 dark:bg-white/10 dark:ring-white/10">
        @foreach ($days->take(7) as $day)
            <div class="hidden bg-gray-50 px-3 py-2 text-xs font-medium text-gray-500 md:block dark:bg-gray-900 dark:text-gray-400">
                {{ $day->translatedFormat('D') }}
            </div>
        @endforeach

        @foreach ($days as $day)
            @php
                $dayEntries = $entries->get($day->toDateString(), collect());
                $inMonth = $day->isSameMonth($month);
            @endphp

            <div @class([
                'min-h-28 space-y-1.5 bg-white p-2 dark:bg-gray-900',
                'hidden md:block' => $dayEntries->isEmpty() || ! $inMonth,
                'md:bg-gray-50 dark:md:bg-gray-950' => ! $inMonth,
            ])>
                <div @class([
                    'text-sm tabular-nums',
                    'text-gray-400 dark:text-gray-500' => ! $inMonth,
                    'text-gray-700 dark:text-gray-200' => $inMonth,
                ])>
                    <span @class([
                        'inline-flex min-w-6 justify-center rounded-full px-1.5',
                        'bg-primary-600 font-semibold text-white dark:bg-primary-500' => $day->isToday(),
                    ])>{{ $day->day }}</span>
                    <span class="md:hidden">{{ $day->translatedFormat('l, F') }}</span>
                </div>

                <ul class="space-y-1">
                    @foreach ($dayEntries as $entry)
                        <li class="flex min-w-0 flex-wrap items-center gap-x-2">
                            <x-filament::badge
                                tag="a"
                                :href="$entry['url']"
                                :color="$entry['type']->getColor()"
                                :tooltip="$entry['type']->getLabel().' · '.$entry['detail']"
                                class="max-w-full truncate"
                            >
                                {{ $entry['label'] }}
                            </x-filament::badge>
                            <span class="text-xs text-gray-500 md:hidden dark:text-gray-400">{{ $entry['detail'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
