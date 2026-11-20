{{-- Footer semua panel: nama aplikasi, versi yang sedang jalan, dan kontak IT dari Pengaturan. --}}
@php
    $version = substr((string) config('app.version'), 0, 7);
    $itContact = \App\Models\Setting::get('kontak_it');
@endphp

<footer class="kertas-footer">
    <p>
        {{ config('app.name') }}
        @if ($version !== '')
            · {{ __('versi :versi', ['versi' => $version]) }}
        @endif
        · © {{ now()->year }}
    </p>

    @if (filled($itContact))
        <p>{{ __('Ada kendala? Hubungi Tim IT: :kontak', ['kontak' => $itContact]) }}</p>
    @endif
</footer>
