<?php

namespace App\Http\Controllers;

use App\Support\BusinessLineCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $section = $request->route()->defaults['section'];
        $pages = [
            'servicios' => ['Servicios', 'Cuatro áreas de servicio, una visión común. Soluciones de energía, electricidad, seguridad y redes para viviendas y empresas.', '/servicios'],
            'empresa' => ['La técnica, al servicio de las personas.', 'Conoce AREVANTES y nuestra forma de abordar cada proyecto: criterio técnico, compromiso y atención personalizada.', '/empresa'],
            'proceso' => ['Del análisis al resultado.', 'Te acompañamos desde el estudio inicial hasta la puesta en servicio, con un alcance claro y atención en cada fase.', '/proceso'],
            'contacto' => ['Contacto', 'Estudiaremos tu caso y te propondremos la solución más adecuada. Cuéntanos qué necesitas para empezar.', '/contacto'],
            'redes' => ['Redes y sistemas', 'Infraestructura de comunicaciones, cableado estructurado y conectividad para viviendas, comercios y empresas.', '/servicios/redes'],
        ];
        [$title, $description, $path] = $pages[$section];

        return Inertia::render($section === 'contacto' ? 'public/contact' : 'public/page', [
            'section' => $section,
            'siteUrl' => rtrim((string) config('app.url'), '/'),
            'metadata' => compact('title', 'description', 'path'),
            ...($section === 'contacto' ? [
                'contactServices' => BusinessLineCatalog::WEBSITE_SERVICES,
                'selectedService' => array_key_exists((string) $request->query('servicio'), BusinessLineCatalog::WEBSITE_SERVICES) ? $request->query('servicio') : '',
            ] : []),
        ]);
    }
}
