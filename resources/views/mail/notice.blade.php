@extends('mail.layout')

@section('title', $notice->subject)

@section('preheader', $notice->intro)

@section('content')
    <p style="margin:0 0 24px;">{{ $notice->intro }}</p>
    @foreach ($notice->sections as $heading => $lines)
        <p style="margin:0 0 8px;font-weight:700;color:#1a533e;">{{ $heading }}</p>
        <ul style="margin:0 0 24px;padding-left:20px;">
            @foreach ($lines as $line)
                <li style="margin:0 0 4px;">{{ $line }}</li>
            @endforeach
        </ul>
    @endforeach
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
        <tr>
            <td bgcolor="#1a533e" style="background-color:#1a533e;border-radius:999px;">
                <a href="{{ $notice->actionUrl }}" style="display:inline-block;padding:12px 24px;font-family:{!! $sans !!};font-size:15px;font-weight:600;line-height:1;color:#ffffff;text-decoration:none;">{{ $notice->actionLabel }}</a>
            </td>
        </tr>
    </table>
@endsection
