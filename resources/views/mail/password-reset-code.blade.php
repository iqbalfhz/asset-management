@extends('mail.layout')

@section('title', __('Kode atur ulang kata sandi'))

@section('preheader', __('Kode Anda :code, berlaku :menit menit.', ['code' => $code, 'menit' => $minutes]))

@section('content')
    <p style="margin:0 0 24px;">{{ __('Kami menerima permintaan atur ulang kata sandi untuk akun Anda. Masukkan kode berikut di halaman Lupa password:') }}</p>
    <table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 24px;">
        <tr>
            {{-- Padding kanan lebih kecil karena letter-spacing menambah jarak setelah digit terakhir. --}}
            <td bgcolor="#ecf9f3" style="background-color:#ecf9f3;border-radius:999px;padding:14px 26px 14px 36px;font-family:{!! $sans !!};font-size:32px;font-weight:700;letter-spacing:10px;line-height:1;color:#1a533e;">{{ $code }}</td>
        </tr>
    </table>
    <p style="margin:0 0 16px;">{{ __('Kode ini berlaku :menit menit dan hanya bisa dipakai sekali. Demi keamanan akun Anda, jangan berikan kode ini kepada siapa pun, termasuk orang yang mengaku dari tim IT.', ['menit' => $minutes]) }}</p>
    <p style="margin:0 0 24px;">{{ __('Kalau Anda tidak meminta atur ulang kata sandi, abaikan email ini. Kata sandi Anda tidak berubah.') }}</p>
@endsection
