{{-- Kerangka email versi teks biasa; {!! !!} karena isinya bukan HTML. --}}
{!! $name ? __('Halo :name,', ['name' => $name]) : __('Halo,') !!}

@yield('content')
{{ __('Salam,') }}
{!! $appName !!}

--
{{ __('Email ini dikirim otomatis, jadi balasan ke alamat ini tidak dibaca. Butuh bantuan? Hubungi tim IT kantor Anda.') }}
