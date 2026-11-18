@extends('mail.layout-text')

@section('content')
{{ __('Kami menerima permintaan atur ulang kata sandi untuk akun Anda. Masukkan kode berikut di halaman Lupa password:') }}

{{ $code }}

{{ __('Kode ini berlaku :menit menit dan hanya bisa dipakai sekali. Demi keamanan akun Anda, jangan berikan kode ini kepada siapa pun, termasuk orang yang mengaku dari tim IT.', ['menit' => $minutes]) }}

{{ __('Kalau Anda tidak meminta atur ulang kata sandi, abaikan email ini. Kata sandi Anda tidak berubah.') }}

@endsection
