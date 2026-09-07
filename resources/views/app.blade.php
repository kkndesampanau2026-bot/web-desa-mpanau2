<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{--
        Favicon disajikan sebagai berkas PERSEGI berukuran baku, bukan menunjuk
        langsung ke logo desa.

        `logo_desa.png` berbentuk potret 410x609 dan berbobot 241 KB. Peramban
        memasukkan favicon ke kotak persegi, sehingga lambang potret menyusut
        mengikuti tingginya dan menyisakan ruang kosong di kiri-kanan — persis
        yang membuatnya tampak kecil di tab. Berkas di bawah sudah didudukkan
        pada kanvas persegi dan diperkecil ke ukuran yang benar-benar dipakai
        peramban, sehingga ikonnya mengisi kotak tab dan bobotnya turun dari
        241 KB menjadi ~2 KB.

        `favicon.ico` tetap disediakan meski sudah ada varian PNG: peramban dan
        perayap meminta `/favicon.ico` secara otomatis walau tidak dideklarasikan,
        dan sebelumnya berkas itu ada tetapi BERUKURAN 0 byte — terkirim sebagai
        respons rusak.
    --}}
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">

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
