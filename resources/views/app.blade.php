@php
    $component = $page['component'] ?? '';
    $isHomepage = $component === 'welcome';
    $isServicePage = $component === 'services/show';
    $isProjectsIndex = $component === 'projects/index';
    $projectMetadata = $component === 'projects/show' ? data_get($page, 'props.project') : null;
    $serviceMetadata = $isServicePage ? data_get($page, 'props.service') : null;
    $legalDocument = $component === 'legal/show' ? data_get($page, 'props.document') : null;
    $isPublicWebsite = $isHomepage || $isServicePage || $isProjectsIndex || filled($projectMetadata) || filled($legalDocument);
    $locale = app()->getLocale();
    $isSpanish = $locale === 'es';
    $siteUrl = rtrim(config('app.url'), '/');
    $siteTitle = $isSpanish
        ? 'Arevantes · Energía, electricidad y seguridad'
        : 'Arevantes · Energy, electrical installations and security';
    $siteDescription = $isSpanish
        ? 'Arevantes diseña, instala y mantiene soluciones de energía, instalaciones eléctricas y sistemas de seguridad en Córdoba y Andalucía.'
        : 'Arevantes designs, installs and maintains energy solutions, electrical installations and security systems in Córdoba and Andalusia.';
    $projectsTitle = $isSpanish ? 'Proyectos realizados · Arevantes' : 'Completed projects · Arevantes';
    $projectsDescription = $isSpanish
        ? 'Una selección de proyectos de energía, electricidad y seguridad realizados por Arevantes en Andalucía.'
        : 'A selection of energy, electrical and security projects completed by Arevantes in Andalusia.';
    $serviceCatalog = $isSpanish ? [
        'Energía y movilidad' => ['Fotovoltaica', 'Almacenamiento', 'Puntos de recarga'],
        'Instalaciones eléctricas' => ['Instalaciones', 'Adecuaciones', 'Certificación'],
        'Alarmas y seguridad' => ['Alarmas', 'Videovigilancia', 'Control de accesos'],
    ] : [
        'Energy and mobility' => ['Solar energy', 'Energy storage', 'EV charging points'],
        'Electrical installations' => ['Installations', 'Upgrades', 'Certification'],
        'Alarms and security' => ['Alarms', 'Video surveillance', 'Access control'],
    ];
    $legalMetadata = [
        'notice' => [
            'path' => 'aviso-legal',
            'title' => $isSpanish ? 'Aviso legal' : 'Legal notice',
            'description' => $isSpanish ? 'Información identificativa y condiciones de uso de este sitio web.' : 'Company information and terms of use for this website.',
        ],
        'privacy' => [
            'path' => 'privacidad',
            'title' => $isSpanish ? 'Política de privacidad' : 'Privacy policy',
            'description' => $isSpanish ? 'Cómo tratamos los datos personales que nos facilitas.' : 'How we process the personal data you provide.',
        ],
        'cookies' => [
            'path' => 'cookies',
            'title' => $isSpanish ? 'Política de cookies' : 'Cookie policy',
            'description' => $isSpanish ? 'Información sobre las cookies técnicas utilizadas en este sitio.' : 'Information about the technical cookies used on this website.',
        ],
    ];
    $currentLegalMetadata = $legalDocument ? ($legalMetadata[$legalDocument] ?? null) : null;
    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => 'Arevantes',
        'legalName' => config('company.legal_name'),
        'url' => $siteUrl,
        'logo' => $siteUrl.'/brand/arevantes-logo-light.svg',
        'email' => config('company.email'),
        'telephone' => config('company.phone'),
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => config('company.address'),
            'postalCode' => config('company.postal_code'),
            'addressLocality' => config('company.city'),
            'addressRegion' => config('company.province'),
            'addressCountry' => 'ES',
        ],
        'areaServed' => $isSpanish ? 'Andalucía' : 'Andalusia',
        'hasOfferCatalog' => [
            '@type' => 'OfferCatalog',
        'name' => $isSpanish ? 'Servicios Arevantes' : 'Arevantes services',
            'itemListElement' => collect($serviceCatalog)->map(fn (array $services, string $line): array => [
                '@type' => 'OfferCatalog',
                'name' => $line,
                'itemListElement' => collect($services)->map(fn (string $service): array => [
                    '@type' => 'Offer',
                    'itemOffered' => [
                        '@type' => 'Service',
                        'name' => $service,
                    ],
                ])->values()->all(),
            ])->values()->all(),
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" @class(['dark' => ! $isPublicWebsite && ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#18181b">
        <link rel="manifest" href="/commercial-field.webmanifest">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="AREVANTES">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (! @json($isPublicWebsite) && appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico?v=20261026" sizes="16x16 32x32 48x48">
        <link rel="icon" href="/favicon.svg?v=20261026" type="image/svg+xml" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon-152.png?v=20261026" sizes="152x152">
        <link rel="apple-touch-icon" href="/apple-touch-icon-167.png?v=20261026" sizes="167x167">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=20261026" sizes="180x180">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        <x-inertia::head>
            @if ($isHomepage)
                <title data-inertia="title">{{ $siteTitle }}</title>
                <meta data-inertia="description" name="description" content="{{ $siteDescription }}">
                <meta data-inertia="robots" name="robots" content="index, follow">
                <link data-inertia="canonical" rel="canonical" href="{{ $siteUrl }}">
                <meta data-inertia="og-type" property="og:type" content="website">
                <meta data-inertia="og-locale" property="og:locale" content="{{ $isSpanish ? 'es_ES' : 'en_GB' }}">
                <meta data-inertia="og-site-name" property="og:site_name" content="Arevantes">
                <meta data-inertia="og-title" property="og:title" content="{{ $siteTitle }}">
                <meta data-inertia="og-description" property="og:description" content="{{ $siteDescription }}">
                <meta data-inertia="og-url" property="og:url" content="{{ $siteUrl }}">
                <meta data-inertia="og-image" property="og:image" content="{{ $siteUrl }}/og-multirramo-v2.png">
                <meta data-inertia="og-image-width" property="og:image:width" content="1200">
                <meta data-inertia="og-image-height" property="og:image:height" content="630">
                <meta data-inertia="og-image-alt" property="og:image:alt" content="{{ $siteTitle }}">
                <meta data-inertia="twitter-card" name="twitter:card" content="summary_large_image">
                <meta data-inertia="twitter-title" name="twitter:title" content="{{ $siteTitle }}">
                <meta data-inertia="twitter-description" name="twitter:description" content="{{ $siteDescription }}">
                <meta data-inertia="twitter-image" name="twitter:image" content="{{ $siteUrl }}/og-multirramo-v2.png">
                <script data-inertia="structured-data" type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
            @elseif ($serviceMetadata)
                @php
                    $serviceUrl = $siteUrl.'/servicios/'.$serviceMetadata['slug'];
                    $serviceStructuredData = [
                        '@context' => 'https://schema.org',
                        '@type' => 'Service',
                        'name' => $serviceMetadata['name'],
                        'description' => $serviceMetadata['seo_description'],
                        'url' => $serviceUrl,
                        'image' => $siteUrl.$serviceMetadata['image'],
                        'areaServed' => 'Andalucía',
                        'provider' => [
                            '@type' => 'LocalBusiness',
                            'name' => 'Arevantes',
                            'url' => $siteUrl,
                        ],
                    ];
                    $faqStructuredData = [
                        '@context' => 'https://schema.org',
                        '@type' => 'FAQPage',
                        'mainEntity' => collect($serviceMetadata['faqs'] ?? [])->map(fn (array $faq): array => [
                            '@type' => 'Question',
                            'name' => $faq['question'],
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text' => $faq['answer'],
                            ],
                        ])->values()->all(),
                    ];
                @endphp
                <title data-inertia="title">{{ $serviceMetadata['seo_title'] }}</title>
                <meta data-inertia="description" name="description" content="{{ $serviceMetadata['seo_description'] }}">
                <meta data-inertia="robots" name="robots" content="index, follow">
                <link data-inertia="canonical" rel="canonical" href="{{ $serviceUrl }}">
                <meta data-inertia="og-type" property="og:type" content="website">
                <meta data-inertia="og-locale" property="og:locale" content="{{ $isSpanish ? 'es_ES' : 'en_GB' }}">
                <meta data-inertia="og-title" property="og:title" content="{{ $serviceMetadata['seo_title'] }}">
                <meta data-inertia="og-description" property="og:description" content="{{ $serviceMetadata['seo_description'] }}">
                <meta data-inertia="og-url" property="og:url" content="{{ $serviceUrl }}">
                <meta data-inertia="og-image" property="og:image" content="{{ $siteUrl }}{{ $serviceMetadata['image'] }}">
                <meta data-inertia="og-image-alt" property="og:image:alt" content="{{ $serviceMetadata['image_alt'] }}">
                <meta data-inertia="twitter-card" name="twitter:card" content="summary_large_image">
                <meta data-inertia="twitter-title" name="twitter:title" content="{{ $serviceMetadata['seo_title'] }}">
                <meta data-inertia="twitter-description" name="twitter:description" content="{{ $serviceMetadata['seo_description'] }}">
                <meta data-inertia="twitter-image" name="twitter:image" content="{{ $siteUrl }}{{ $serviceMetadata['image'] }}">
                <script data-inertia="structured-data" type="application/ld+json">{!! json_encode($serviceStructuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
                <script data-inertia="faq-structured-data" type="application/ld+json">{!! json_encode($faqStructuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
            @elseif ($isProjectsIndex)
                <title data-inertia="title">{{ $projectsTitle }}</title>
                <meta data-inertia="description" name="description" content="{{ $projectsDescription }}">
                <meta data-inertia="robots" name="robots" content="index, follow">
                <link data-inertia="canonical" rel="canonical" href="{{ $siteUrl }}/proyectos">
                <meta data-inertia="og-type" property="og:type" content="website">
                <meta data-inertia="og-locale" property="og:locale" content="{{ $isSpanish ? 'es_ES' : 'en_GB' }}">
                <meta data-inertia="og-title" property="og:title" content="{{ $projectsTitle }}">
                <meta data-inertia="og-description" property="og:description" content="{{ $projectsDescription }}">
                <meta data-inertia="og-url" property="og:url" content="{{ $siteUrl }}/proyectos">
                <meta data-inertia="og-image" property="og:image" content="{{ $siteUrl }}/og-multirramo-v2.png">
                <meta data-inertia="twitter-card" name="twitter:card" content="summary_large_image">
                <meta data-inertia="twitter-title" name="twitter:title" content="{{ $projectsTitle }}">
                <meta data-inertia="twitter-description" name="twitter:description" content="{{ $projectsDescription }}">
                <meta data-inertia="twitter-image" name="twitter:image" content="{{ $siteUrl }}/og-multirramo-v2.png">
            @elseif ($projectMetadata)
                @php
                    $projectUrl = $siteUrl.$projectMetadata['href'];
                    $projectTitle = $projectMetadata['title'].($isSpanish ? ' · Proyectos Arevantes' : ' · Arevantes projects');
                    $projectImage = data_get($projectMetadata, 'images.0');
                    $projectStructuredData = [
                        '@context' => 'https://schema.org',
                        '@type' => 'CreativeWork',
                        'name' => $projectMetadata['title'],
                        'description' => $projectMetadata['summary'],
                        'url' => $projectUrl,
                        'image' => $projectImage ? $siteUrl.$projectImage['url'] : null,
                        'locationCreated' => $projectMetadata['location'],
                        'dateCreated' => $projectMetadata['completed_on'],
                        'creator' => ['@type' => 'Organization', 'name' => 'Arevantes', 'url' => $siteUrl],
                    ];
                @endphp
                <title data-inertia="title">{{ $projectTitle }}</title>
                <meta data-inertia="description" name="description" content="{{ $projectMetadata['summary'] }}">
                <meta data-inertia="robots" name="robots" content="index, follow">
                <link data-inertia="canonical" rel="canonical" href="{{ $projectUrl }}">
                <meta data-inertia="og-type" property="og:type" content="article">
                <meta data-inertia="og-locale" property="og:locale" content="{{ $isSpanish ? 'es_ES' : 'en_GB' }}">
                <meta data-inertia="og-title" property="og:title" content="{{ $projectTitle }}">
                <meta data-inertia="og-description" property="og:description" content="{{ $projectMetadata['summary'] }}">
                <meta data-inertia="og-url" property="og:url" content="{{ $projectUrl }}">
                @if ($projectImage)
                    <meta data-inertia="og-image" property="og:image" content="{{ $siteUrl }}{{ $projectImage['url'] }}">
                    <meta data-inertia="og-image-alt" property="og:image:alt" content="{{ $projectImage['alt'] }}">
                @endif
                <meta data-inertia="twitter-card" name="twitter:card" content="summary_large_image">
                <meta data-inertia="twitter-title" name="twitter:title" content="{{ $projectTitle }}">
                <meta data-inertia="twitter-description" name="twitter:description" content="{{ $projectMetadata['summary'] }}">
                @if ($projectImage)
                    <meta data-inertia="twitter-image" name="twitter:image" content="{{ $siteUrl }}{{ $projectImage['url'] }}">
                @endif
                <script data-inertia="project-structured-data" type="application/ld+json">{!! json_encode($projectStructuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
            @elseif ($currentLegalMetadata)
                <title data-inertia="title">{{ $currentLegalMetadata['title'] }} · Arevantes</title>
                <meta data-inertia="description" name="description" content="{{ $currentLegalMetadata['description'] }}">
                <meta data-inertia="robots" name="robots" content="index, follow">
                <link data-inertia="canonical" rel="canonical" href="{{ $siteUrl }}/{{ $currentLegalMetadata['path'] }}">
            @else
                <title>Arevantes</title>
            @endif
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
