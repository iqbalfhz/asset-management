{{-- Versi teks biasa; {!! !!} karena isinya bukan HTML. --}}
{!! $name ? __('Halo :name,', ['name' => $name]) : __('Halo,') !!}

{{ __('Kami menerima permintaan atur ulang kata sandi untuk akun Anda. Masukkan kode berikut di halaman Lupa password:') }}

{{ $code }}

{{ __('Kode ini berlaku :menit menit dan hanya bisa dipakai sekali. Demi keamanan akun Anda, jangan berikan kode ini kepada siapa pun, termasuk orang yang mengaku dari tim IT.', ['menit' => $minutes]) }}

{{ __('Kalau Anda tidak meminta atur ulang kata sandi, abaikan email ini. Kata sandi Anda tidak berubah.') }}

{{ __('Salam,') }}
{!! $appName !!}

--
{{ __('Email ini dikirim otomatis, jadi balasan ke alamat ini tidak dibaca. Butuh bantuan? Hubungi tim IT kantor Anda.') }}
