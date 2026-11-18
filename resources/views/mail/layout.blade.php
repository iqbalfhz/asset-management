{{-- Kerangka email bertema Kertas. Tabel dan style inline supaya tampil sama di Outlook dan Gmail. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('title')</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f1ea;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">@yield('preheader')</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f1f1ea" style="background-color:#f1f1ea;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="max-width:520px;background-color:#ffffff;border:1px solid #e0e0da;border-radius:16px;border-collapse:separate;overflow:hidden;">
                    <tr>
                        <td bgcolor="#1a533e" style="background-color:#1a533e;padding:24px 32px;font-family:'Newsreader',Georgia,'Times New Roman',serif;font-size:24px;font-weight:600;line-height:1.2;color:#ffffff;">
                            {{ $appName }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;font-family:{!! $sans !!};font-size:15px;line-height:1.65;color:#41423b;">
                            <p style="margin:0 0 16px;">{{ $name ? __('Halo :name,', ['name' => $name]) : __('Halo,') }}</p>
                            @yield('content')
                            <p style="margin:0;">{{ __('Salam,') }}<br>{{ $appName }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top:1px solid #e0e0da;padding:20px 32px;font-family:{!! $sans !!};font-size:12px;line-height:1.6;color:#73746c;">
                            {{ __('Email ini dikirim otomatis, jadi balasan ke alamat ini tidak dibaca. Butuh bantuan? Hubungi tim IT kantor Anda.') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
