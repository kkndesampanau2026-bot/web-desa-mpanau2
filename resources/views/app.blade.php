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

    {{-- Meta Dasar SEO & Perayapan --}}
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="description" content="Website Resmi Profil dan Pelayanan Digital Desa Mpanau, Kecamatan Sigi Biromaru, Kabupaten Sigi, Provinsi Sulawesi Tengah.">
    <meta name="keywords" content="Desa Mpanau, Sigi Biromaru, Kabupaten Sigi, Sulawesi Tengah, Profil Desa, APBDes, Informasi Desa, Layanan Mandiri Desa">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="author" content="Pemerintah Desa Mpanau">

    {{-- Verifikasi Google Search Console --}}
    @if (config('services.google.site_verification'))
        <meta name="google-site-verification" content="{{ config('services.google.site_verification') }}">
    @endif

    {{-- Open Graph / Facebook --}}
    <meta property="og:locale" content="id_ID">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Desa Mpanau">
    <meta property="og:title" content="{{ config('app.name') }}">
    <meta property="og:description" content="Portal Informasi Publik, Statistik Desa, dan Layanan Mandiri Digital Desa Mpanau, Kec. Sigi Biromaru, Kab. Sigi.">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('favicon-32.png') }}">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ config('app.name') }}">
    <meta name="twitter:description" content="Portal Informasi Publik, Statistik Desa, dan Layanan Mandiri Digital Desa Mpanau, Kec. Sigi Biromaru, Kab. Sigi.">
    <meta name="twitter:image" content="{{ asset('favicon-32.png') }}">

    {{-- Structured Data (JSON-LD) Global untuk AEO & Rich Snippets --}}
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'GovernmentOrganization',
                '@id' => url('/') . '/#organization',
                'name' => 'Pemerintah Desa Mpanau',
                'alternateName' => 'Desa Mpanau',
                'url' => url('/'),
                'logo' => asset('logo_desa.png'),
                'image' => asset('favicon-32.png'),
                'description' => 'Pemerintah Desa Mpanau, Kecamatan Sigi Biromaru, Kabupaten Sigi, Provinsi Sulawesi Tengah.',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => 'Sigi Biromaru',
                    'addressRegion' => 'Sulawesi Tengah',
                    'addressCountry' => 'ID',
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => url('/') . '/#website',
                'url' => url('/'),
                'name' => 'Website Resmi Desa Mpanau',
                'publisher' => [
                    '@id' => url('/') . '/#organization',
                ],
                'inLanguage' => 'id-ID',
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>

    @routes
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
