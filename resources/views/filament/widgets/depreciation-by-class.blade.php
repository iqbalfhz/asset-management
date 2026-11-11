@php
    $rows = $this->rows();
    $rupiah = fn (int $value): string => \Illuminate\Support\Number::currency($value, in: 'IDR', locale: 'id', precision: 0);
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('Penyusutan per kelas')"
        :description="__('Garis lurus per bulan. Aset BYOD dan yang sudah dihapuskan tidak dihitung.')"
    >
        @if ($rows->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Belum ada aset dengan harga beli dan kelas penyusutan.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="py-2 pe-4 font-medium">{{ __('Kelas') }}</th>
                            <th class="py-2 pe-4 text-right font-medium">{{ __('Aset') }}</th>
                            <th class="py-2 pe-4 text-right font-medium">{{ __('Nilai perolehan') }}</th>
                            <th class="py-2 text-right font-medium">{{ __('Nilai buku') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $kelas => $row)
                            <tr class="border-t border-gray-200 dark:border-white/10">
                                <td class="py-2 pe-4">{{ $kelas }}</td>
                                <td class="py-2 pe-4 text-right tabular-nums">{{ $row['jumlah'] }}</td>
                                <td class="py-2 pe-4 text-right tabular-nums">{{ $rupiah($row['perolehan']) }}</td>
                                <td class="py-2 text-right tabular-nums">{{ $rupiah($row['buku']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 font-semibold dark:border-white/20">
                            <td class="py-2 pe-4">{{ __('Total') }}</td>
                            <td class="py-2 pe-4 text-right tabular-nums">{{ $rows->sum('jumlah') }}</td>
                            <td class="py-2 pe-4 text-right tabular-nums">{{ $rupiah($rows->sum('perolehan')) }}</td>
                            <td class="py-2 text-right tabular-nums">{{ $rupiah($rows->sum('buku')) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
