@extends('mail.layout-text')

@section('content')
{!! $notice->intro !!}

@foreach ($notice->sections as $heading => $lines)
{!! $heading !!}
@foreach ($lines as $line)
- {!! $line !!}
@endforeach

@endforeach
{!! $notice->actionLabel !!}: {!! $notice->actionUrl !!}

@endsection
