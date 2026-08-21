<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    {{--
        Judul cadangan untuk peramban yang menerima HTML sebelum React berjalan
        dan untuk perayap yang tidak mengeksekusi JavaScript. Halaman yang
        memasang <Head> sendiri akan menimpanya lewat @inertiaHead.
    --}}
    <title inertia>{{ config('app.name') }}</title>

    @routes
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
