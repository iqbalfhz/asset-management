<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Masuk untuk melihat aset') }} · {{ config('app.name') }}</title>
    @vite('resources/css/filament/admin/theme.css')
    <style>
        :root {
            --ground: oklch(0.956 0.009 105);
            --ink: oklch(0.225 0.008 110);
            --quiet: oklch(0.455 0.012 110);
            --pine: oklch(0.40 0.07 165);
        }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--ground); color: var(--ink); font-family: 'Plus Jakarta Sans Variable', ui-sans-serif, system-ui, sans-serif; }
        main { width: 100%; max-width: 26rem; padding: 2rem 1rem; }
        h1 { font-family: 'Newsreader Variable', Georgia, serif; font-weight: 600; font-size: 1.75rem; line-height: 1.2; margin: 0 0 0.5rem; }
        p { color: var(--quiet); margin: 0 0 1.75rem; }
        .code { font-weight: 600; color: var(--ink); }
        a { display: block; text-align: center; padding: 0.75rem 1rem; border-radius: 999px; text-decoration: none; font-weight: 600; margin-bottom: 0.75rem; }
        .primary { background: var(--pine); color: #fff; }
        .secondary { border: 1px solid var(--pine); color: var(--pine); }
    </style>
</head>
<body>
    <main>
        <h1>{{ __('Masuk untuk melihat aset') }}</h1>
        <p>{{ __('Label ini milik aset') }} <span class="code">{{ $kode }}</span>. {{ __('Detail aset hanya bisa dilihat setelah masuk.') }}</p>
        <a class="primary" href="{{ $adminUrl }}">{{ __('Masuk sebagai admin') }}</a>
        <a class="secondary" href="{{ $employeeUrl }}">{{ __('Masuk sebagai karyawan') }}</a>
    </main>
</body>
</html>
