<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; color: #1c1e21; background: #fafafa; }
        main { max-width: 44rem; margin: 12vh auto; padding: 0 1.25rem; }
        code { background: #eee; padding: .1rem .35rem; border-radius: 4px; }
    </style>
    @stack('head')
</head>
<body>
<main>@yield('content')</main>
@stack('scripts')
</body>
</html>
