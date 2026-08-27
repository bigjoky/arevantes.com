import catalog from '@/i18n/catalog.json';

export type Locale = 'en' | 'es';

type Translation = { en: string; es: string };

const terminologyOverrides: Record<string, Translation> = {
    'Actual cash': { en: 'Actual cash', es: 'Efectivo real' },
    'Actual cash net': { en: 'Actual net cash', es: 'Flujo de caja neto real' },
    Aprobar: { en: 'Approve', es: 'Aprobar' },
    'Assigned to': { en: 'Assigned to', es: 'Asignado a' },
    Batch: { en: 'Batch', es: 'Remesa' },
    'Company logo': { en: 'Company logo', es: 'Logotipo de la empresa' },
    Catalog: { en: 'Catalog', es: 'Catálogo' },
    CRM: { en: 'CRM', es: 'CRM' },
    Expenses: { en: 'Expenses', es: 'Gastos' },
    Expense: { en: 'Expense', es: 'Gasto' },
    Cash: { en: 'Cash', es: 'Efectivo' },
    Compare: { en: 'Compare', es: 'Comparar' },
    Close: { en: 'Close', es: 'Cerrar' },
    Contains: { en: 'Contains', es: 'Contiene' },
    Dashboard: { en: 'Dashboard', es: 'Panel de control' },
    Default: { en: 'Default', es: 'Predeterminado' },
    'Default warehouse': {
        en: 'Default warehouse',
        es: 'Almacén predeterminado',
    },
    Due: { en: 'Due', es: 'Vencimiento' },
    'Due date': { en: 'Due date', es: 'Fecha de vencimiento' },
    'Due in': { en: 'Due in', es: 'Vence en' },
    'Electrical, commissioning': {
        en: 'Electrical, commissioning',
        es: 'Puesta en servicio eléctrica',
    },
    Encryption: { en: 'Encryption', es: 'Cifrado' },
    Ended: { en: 'Ended', es: 'Finalizado' },
    'Expires soon': { en: 'Expires soon', es: 'Caduca pronto' },
    Extracted: { en: 'Extracted', es: 'Extraído' },
    'Filed on': { en: 'Filed on', es: 'Presentado el' },
    Folder: { en: 'Folder', es: 'Carpeta' },
    Forecast: { en: 'Forecast', es: 'Previsión' },
    'Goods returns': { en: 'Goods returns', es: 'Devoluciones de mercancía' },
    'Governed customer notice': {
        en: 'Governed customer notice',
        es: 'Aviso supervisado al cliente',
    },
    'Guardar ficha': { en: 'Save record', es: 'Guardar ficha' },
    'Guardar nota': { en: 'Save note', es: 'Guardar nota' },
    'Guardar política': { en: 'Save policy', es: 'Guardar política' },
    Inbound: { en: 'Inbound', es: 'Entrante' },
    Journal: { en: 'Journal', es: 'Diario contable' },
    Mailbox: { en: 'Mailbox', es: 'Buzón' },
    Margin: { en: 'Margin', es: 'Margen' },
    'Mark as filed': { en: 'Mark as filed', es: 'Marcar como presentado' },
    'Mark reviewed': { en: 'Mark reviewed', es: 'Marcar como revisado' },
    Maturities: { en: 'Maturities', es: 'Vencimientos' },
    Merchant: { en: 'Merchant', es: 'Comercio' },
    Tax: { en: 'Tax', es: 'Impuesto' },
    'Tax amount': { en: 'Tax amount', es: 'Cuota tributaria' },
    'Taxable base': { en: 'Taxable base', es: 'Base imponible' },
    'Receipt / ticket': { en: 'Receipt / ticket', es: 'Recibo / tique' },
    'Simplified invoice': {
        en: 'Simplified invoice',
        es: 'Factura simplificada',
    },
    'Full invoice': { en: 'Full invoice', es: 'Factura completa' },
    'Other supporting document': {
        en: 'Other supporting document',
        es: 'Otro justificante',
    },
    'No linked supplier': {
        en: 'No linked supplier',
        es: 'Sin proveedor vinculado',
    },
    'No linked installation': {
        en: 'No linked installation',
        es: 'Sin instalación vinculada',
    },
    Standard: { en: 'Standard', es: 'Régimen general' },
    Exempt: { en: 'Exempt', es: 'Exenta' },
    'Not subject to VAT': {
        en: 'Not subject to VAT',
        es: 'No sujeta a IVA',
    },
    'Reverse charge': {
        en: 'Reverse charge',
        es: 'Inversión del sujeto pasivo',
    },
    'Intra-Community': {
        en: 'Intra-Community',
        es: 'Intracomunitaria',
    },
    Import: { en: 'Import', es: 'Importación' },
    'Equivalence surcharge': {
        en: 'Equivalence surcharge',
        es: 'Recargo de equivalencia',
    },
    Mismatch: { en: 'Mismatch', es: 'Discrepancia' },
    'One-off': { en: 'One-off', es: 'Puntual' },
    Overdue: { en: 'Overdue', es: 'Vencido' },
    'Próximas acciones': { en: 'Next actions', es: 'Próximas acciones' },
    'Register expense': { en: 'Register expense', es: 'Registrar gasto' },
    'Register payment': { en: 'Register payment', es: 'Registrar pago' },
    'Delete expense': { en: 'Delete expense', es: 'Eliminar gasto' },
    'No attachments.': { en: 'No attachments.', es: 'Sin adjuntos.' },
    'No payments.': { en: 'No payments.', es: 'Sin pagos.' },
    Nivel: { en: 'Level', es: 'Nivel' },
    Incluida: { en: 'Included', es: 'Incluida' },
    'Sin desglose técnico. La partida seguirá funcionando como una línea tradicional.':
        {
            en: 'No technical breakdown. The line will continue to work as a standard line.',
            es: 'Sin desglose técnico. La partida seguirá funcionando como una línea tradicional.',
        },
    Withholding: { en: 'Withholding', es: 'Retención' },
    Rate: { en: 'Rate', es: 'Tipo' },
    standard: { en: 'Standard', es: 'Régimen general' },
    vat: { en: 'VAT', es: 'IVA' },
    'equivalence surcharge': {
        en: 'Equivalence surcharge',
        es: 'Recargo de equivalencia',
    },
    'Direct debit': {
        en: 'Direct debit',
        es: 'Domiciliación bancaria',
    },
    breadcrumb: { en: 'Breadcrumb', es: 'Ruta de navegación' },
    'Toggle sidebar': {
        en: 'Toggle sidebar',
        es: 'Mostrar u ocultar la barra lateral',
    },
    'Customers, delivery, pipeline and cash position at a glance.': {
        en: 'Customers, delivery, pipeline and cash position at a glance.',
        es: 'Clientes, operaciones, cartera comercial y tesorería de un vistazo.',
    },
    'Fuel and vehicle': {
        en: 'Fuel and vehicle',
        es: 'Combustible y vehículos',
    },
    'Materials and supplies': {
        en: 'Materials and supplies',
        es: 'Materiales y suministros',
    },
    'Other expenses': { en: 'Other expenses', es: 'Otros gastos' },
    'Professional services': {
        en: 'Professional services',
        es: 'Servicios profesionales',
    },
    'Tools and equipment': {
        en: 'Tools and equipment',
        es: 'Herramientas y equipos',
    },
    'Travel and meals': { en: 'Travel and meals', es: 'Viajes y dietas' },
    'Utilities and communications': {
        en: 'Utilities and communications',
        es: 'Suministros y comunicaciones',
    },
    invoice: { en: 'Invoice', es: 'Factura' },
    other: { en: 'Other', es: 'Otro' },
    overdue: { en: 'Overdue', es: 'Vencido' },
    paid: { en: 'Paid', es: 'Pagado' },
    partial: { en: 'Partially paid', es: 'Parcialmente pagado' },
    pending: { en: 'Pending', es: 'Pendiente' },
    receipt: { en: 'Receipt', es: 'Recibo' },
    'Zoom in': { en: 'Zoom in', es: 'Acercar' },
    'Zoom out': { en: 'Zoom out', es: 'Alejar' },
    Installations: { en: 'Installations', es: 'Instalaciones' },
    'New expense': { en: 'New expense', es: 'Nuevo gasto' },
    Outstanding: { en: 'Outstanding', es: 'Pendiente' },
    Supply: { en: 'Supply', es: 'Suministros' },
    Workspace: { en: 'Workspace', es: 'Espacio de trabajo' },
    'Saltar al contenido': {
        en: 'Skip to content',
        es: 'Saltar al contenido',
    },
    'Arevantes, inicio': { en: 'Arevantes, home', es: 'Arevantes, inicio' },
    'Ir al panel': { en: 'Go to dashboard', es: 'Ir al panel' },
    'Área privada': { en: 'Private area', es: 'Área privada' },
    'Energía · Electricidad · Seguridad': {
        en: 'Energy · Electrical · Security',
        es: 'Energía · Electricidad · Seguridad',
    },
    'Tres especialidades.': {
        en: 'Three specialities.',
        es: 'Tres especialidades.',
    },
    'Una ejecución impecable.': {
        en: 'Impeccable execution.',
        es: 'Una ejecución impecable.',
    },
    'Diseñamos, instalamos y mantenemos soluciones de energía, electricidad y seguridad con un único equipo responsable de principio a fin.':
        {
            en: 'We design, install and maintain energy, electrical and security solutions with one dedicated team from start to finish.',
            es: 'Diseñamos, instalamos y mantenemos soluciones de energía, electricidad y seguridad con un único equipo responsable de principio a fin.',
        },
    'Diseño a medida': { en: 'Tailored design', es: 'Diseño a medida' },
    'Ejecución integral': {
        en: 'End-to-end delivery',
        es: 'Ejecución integral',
    },
    'Seguimiento posventa': {
        en: 'After-sales support',
        es: 'Seguimiento posventa',
    },
    'Elige tu línea de proyecto': {
        en: 'Choose your project type',
        es: 'Elige tu línea de proyecto',
    },
    'En evolución': {
        en: 'Continuously evolving',
        es: 'En evolución',
    },
    Energía: { en: 'Energy', es: 'Energía' },
    Electricidad: { en: 'Electrical', es: 'Electricidad' },
    Seguridad: { en: 'Security', es: 'Seguridad' },
    Autoconsumo: { en: 'Solar self-consumption', es: 'Autoconsumo' },
    'Energía y movilidad': {
        en: 'Energy and mobility',
        es: 'Energía y movilidad',
    },
    'Produce, almacena y aprovecha tu energía': {
        en: 'Produce, store and make the most of your energy',
        es: 'Produce, almacena y aprovecha tu energía',
    },
    'Fotovoltaica, baterías y recarga coordinadas para reducir consumo y ganar autonomía.':
        {
            en: 'Solar, battery and EV charging solutions designed to reduce consumption and increase energy independence.',
            es: 'Fotovoltaica, baterías y recarga coordinadas para reducir consumo y ganar autonomía.',
        },
    'Solar · Baterías · Recarga': {
        en: 'Solar · Batteries · EV charging',
        es: 'Solar · Baterías · Recarga',
    },
    Fotovoltaica: { en: 'Solar energy', es: 'Fotovoltaica' },
    Almacenamiento: { en: 'Energy storage', es: 'Almacenamiento' },
    'Puntos de recarga': {
        en: 'EV charging points',
        es: 'Puntos de recarga',
    },
    'Nueva línea': { en: 'New service line', es: 'Nueva línea' },
    'Instalación · Reforma · Certificación': {
        en: 'Installation · Upgrade · Certification',
        es: 'Instalación · Reforma · Certificación',
    },
    Adecuaciones: { en: 'Upgrades', es: 'Adecuaciones' },
    Certificación: { en: 'Certification', es: 'Certificación' },
    'Cuadros, circuitos, adecuaciones y certificación con ejecución técnica y trazable.':
        {
            en: 'Electrical panels, circuits, upgrades and certification delivered with technical rigour and full traceability.',
            es: 'Cuadros, circuitos, adecuaciones y certificación con ejecución técnica y trazable.',
        },
    'Alarmas y seguridad': {
        en: 'Alarms and security',
        es: 'Alarmas y seguridad',
    },
    'Protección conectada a cada espacio': {
        en: 'Protection designed around each space',
        es: 'Protección conectada a cada espacio',
    },
    'Alarmas, videovigilancia y control de accesos dimensionados para viviendas y negocios.':
        {
            en: 'Alarms, video surveillance and access control designed for homes and businesses.',
            es: 'Alarmas, videovigilancia y control de accesos dimensionados para viviendas y negocios.',
        },
    Alarmas: { en: 'Alarms', es: 'Alarmas' },
    Videovigilancia: { en: 'Video surveillance', es: 'Videovigilancia' },
    'Control de accesos': {
        en: 'Access control',
        es: 'Control de accesos',
    },
    'Conocer este servicio': {
        en: 'Explore this service',
        es: 'Conocer este servicio',
    },
    'Control sencillo': {
        en: 'Straightforward control',
        es: 'Control sencillo',
    },
    'Soporte cercano': { en: 'Personal support', es: 'Soporte cercano' },
    'Energía solar': { en: 'Solar energy', es: 'Energía solar' },
    'Mantenimiento integral': {
        en: 'Comprehensive maintenance',
        es: 'Mantenimiento integral',
    },
    'Tres líneas, un solo equipo': {
        en: 'Three service lines, one team',
        es: 'Tres líneas, un solo equipo',
    },
    'La especialidad que necesitas. La coordinación que esperas.': {
        en: 'The expertise you need. The coordination you expect.',
        es: 'La especialidad que necesitas. La coordinación que esperas.',
    },
    'Cada línea tiene su propio alcance técnico, pero todas comparten el mismo método: estudio real, ejecución documentada y continuidad después de la puesta en marcha.':
        {
            en: 'Each service line has its own technical scope, but all share the same approach: a thorough assessment, documented delivery and ongoing support after commissioning.',
            es: 'Cada línea tiene su propio alcance técnico, pero todas comparten el mismo método: estudio real, ejecución documentada y continuidad después de la puesta en marcha.',
        },
    'Capacidad transversal': {
        en: 'Support across every service line',
        es: 'Capacidad transversal',
    },
    'Mantenimiento y continuidad': {
        en: 'Maintenance and continuity',
        es: 'Mantenimiento y continuidad',
    },
    'El proyecto no termina al ponerlo en marcha. Mantenemos cerca el seguimiento, la documentación y la atención técnica para cualquiera de las tres líneas.':
        {
            en: 'The project does not end at commissioning. We provide ongoing monitoring, documentation and technical support across all three service lines.',
            es: 'El proyecto no termina al ponerlo en marcha. Mantenemos cerca el seguimiento, la documentación y la atención técnica para cualquiera de las tres líneas.',
        },
    Monitorización: { en: 'Monitoring', es: 'Monitorización' },
    'Mantenimiento preventivo': {
        en: 'Preventive maintenance',
        es: 'Mantenimiento preventivo',
    },
    'Atención posventa': {
        en: 'After-sales support',
        es: 'Atención posventa',
    },
    'Soluciones reales, adaptadas a cada espacio.': {
        en: 'Practical solutions tailored to every space.',
        es: 'Soluciones reales, adaptadas a cada espacio.',
    },
    'Una selección de trabajos ejecutados por nuestro equipo, desde el estudio inicial hasta la puesta en marcha.':
        {
            en: 'A selection of projects delivered by our team, from the initial assessment through to commissioning.',
            es: 'Una selección de trabajos ejecutados por nuestro equipo, desde el estudio inicial hasta la puesta en marcha.',
        },
    'Una instalación bien resuelta se entiende, se documenta y sigue respondiendo mañana.':
        {
            en: 'A well-executed installation is easy to understand, properly documented and built to perform for years to come.',
            es: 'Una instalación bien resuelta se entiende, se documenta y sigue respondiendo mañana.',
        },
    'Una forma de trabajar': {
        en: 'Our way of working',
        es: 'Una forma de trabajar',
    },
    'Especialistas en cada línea. Responsables del conjunto.': {
        en: 'Specialists in every service line. Accountable for the whole project.',
        es: 'Especialistas en cada línea. Responsables del conjunto.',
    },
    'Energía, electricidad y seguridad exigen conocimientos distintos. Las abordamos con un mismo estándar de análisis, coordinación, documentación y atención posventa.':
        {
            en: 'Energy, electrical work and security require different expertise. We apply the same high standard of assessment, coordination, documentation and after-sales support to each one.',
            es: 'Energía, electricidad y seguridad exigen conocimientos distintos. Las abordamos con un mismo estándar de análisis, coordinación, documentación y atención posventa.',
        },
    'Estudio real': { en: 'Thorough assessment', es: 'Estudio real' },
    'Partimos de datos y condiciones concretas.': {
        en: 'We start with real data and specific site conditions.',
        es: 'Partimos de datos y condiciones concretas.',
    },
    'Visión integral': { en: 'End-to-end view', es: 'Visión integral' },
    'Coordinamos todas las fases del proyecto.': {
        en: 'We coordinate every phase of the project.',
        es: 'Coordinamos todas las fases del proyecto.',
    },
    'Información clara': {
        en: 'Clear information',
        es: 'Información clara',
    },
    'Sabes qué instalamos y por qué.': {
        en: 'You know exactly what we install and why.',
        es: 'Sabes qué instalamos y por qué.',
    },
    Continuidad: { en: 'Continuity', es: 'Continuidad' },
    'La relación no termina en la puesta en marcha.': {
        en: 'Our relationship does not end at commissioning.',
        es: 'La relación no termina en la puesta en marcha.',
    },
    'De la primera conversación al seguimiento.': {
        en: 'From the first conversation to ongoing support.',
        es: 'De la primera conversación al seguimiento.',
    },
    Escuchamos: { en: 'We listen', es: 'Escuchamos' },
    'Revisamos tus necesidades, consumos y las condiciones reales del emplazamiento.':
        {
            en: 'We review your needs, energy use and the actual conditions at the site.',
            es: 'Revisamos tus necesidades, consumos y las condiciones reales del emplazamiento.',
        },
    Diseñamos: { en: 'We design', es: 'Diseñamos' },
    'Preparamos una solución clara, dimensionada y explicada antes de empezar.':
        {
            en: 'Before work begins, we prepare a clear, properly sized solution and explain every detail.',
            es: 'Preparamos una solución clara, dimensionada y explicada antes de empezar.',
        },
    Instalamos: { en: 'We install', es: 'Instalamos' },
    'Coordinamos materiales, ejecución, puesta en marcha y documentación.': {
        en: 'We coordinate materials, installation, commissioning and documentation.',
        es: 'Coordinamos materiales, ejecución, puesta en marcha y documentación.',
    },
    Acompañamos: { en: 'We support you', es: 'Acompañamos' },
    'Seguimos cerca con monitorización, mantenimiento y atención posventa.': {
        en: 'We remain available for monitoring, maintenance and after-sales support.',
        es: 'Seguimos cerca con monitorización, mantenimiento y atención posventa.',
    },
    Hablemos: { en: "Let's talk", es: 'Hablemos' },
    '¿Tienes un proyecto en mente?': {
        en: 'Do you have a project in mind?',
        es: '¿Tienes un proyecto en mente?',
    },
    'Elige tu línea, cuéntanos qué necesitas y empezaremos por entender bien tu caso.':
        {
            en: 'Choose a service line, tell us what you need and we will start by understanding your requirements.',
            es: 'Elige tu línea, cuéntanos qué necesitas y empezaremos por entender bien tu caso.',
        },
    'Recarga y almacenamiento': {
        en: 'EV charging and energy storage',
        es: 'Recarga y almacenamiento',
    },
    'Instalaciones energéticas pensadas para durar.': {
        en: 'Energy installations built to last.',
        es: 'Instalaciones energéticas pensadas para durar.',
    },
    'Paneles solares sobre la cubierta de una vivienda': {
        en: 'Solar panels on the roof of a home',
        es: 'Paneles solares sobre la cubierta de una vivienda',
    },
    'Comprobación técnica de un cuadro eléctrico': {
        en: 'Technical inspection of an electrical panel',
        es: 'Comprobación técnica de un cuadro eléctrico',
    },
    'Cámaras de videovigilancia en la fachada de un edificio': {
        en: 'Video surveillance cameras on a building façade',
        es: 'Cámaras de videovigilancia en la fachada de un edificio',
    },
    'Solicitar estudio': {
        en: 'Request an assessment',
        es: 'Solicitar estudio',
    },
    'Proyectos realizados': {
        en: 'Completed projects',
        es: 'Proyectos realizados',
    },
    Cookies: { en: 'Cookies', es: 'Cookies' },
    'Baena · Córdoba · Andalucía': {
        en: 'Baena · Córdoba · Andalusia',
        es: 'Baena · Córdoba · Andalucía',
    },
    'Alarmas · CCTV · Accesos': {
        en: 'Alarms · CCTV · Access control',
        es: 'Alarmas · CCTV · Accesos',
    },
    'Autoconsumo en vivienda rural': {
        en: 'Solar self-consumption for a rural home',
        es: 'Autoconsumo en vivienda rural',
    },
    'Integración fotovoltaica sobre cubierta inclinada en una vivienda situada en un entorno rural.':
        {
            en: 'Rooftop solar integration for a home in a rural setting.',
            es: 'Integración fotovoltaica sobre cubierta inclinada en una vivienda situada en un entorno rural.',
        },
    'Instalación fotovoltaica en vivienda': {
        en: 'Residential solar installation',
        es: 'Instalación fotovoltaica en vivienda',
    },
    'Sistema fotovoltaico distribuido sobre una cubierta residencial inclinada.':
        {
            en: 'Solar array distributed across a pitched residential roof.',
            es: 'Sistema fotovoltaico distribuido sobre una cubierta residencial inclinada.',
        },
    'Autoconsumo residencial en cubierta plana': {
        en: 'Residential solar installation on a flat roof',
        es: 'Autoconsumo residencial en cubierta plana',
    },
    'Instalación de autoconsumo sobre cubierta plana en un entorno residencial.':
        {
            en: 'Solar self-consumption installation on a flat roof in a residential setting.',
            es: 'Instalación de autoconsumo sobre cubierta plana en un entorno residencial.',
        },
    'Paneles fotovoltaicos integrados sobre la cubierta de una vivienda rural':
        {
            en: 'Solar panels integrated into the roof of a rural home',
            es: 'Paneles fotovoltaicos integrados sobre la cubierta de una vivienda rural',
        },
    'Instalación fotovoltaica distribuida en la cubierta inclinada de una vivienda':
        {
            en: 'Solar installation distributed across the pitched roof of a home',
            es: 'Instalación fotovoltaica distribuida en la cubierta inclinada de una vivienda',
        },
    'Filas de paneles fotovoltaicos sobre una cubierta plana residencial': {
        en: 'Rows of solar panels on a flat residential roof',
        es: 'Filas de paneles fotovoltaicos sobre una cubierta plana residencial',
    },
};

const lookups: Record<Locale, Map<string, string>> = {
    en: new Map<string, string>(),
    es: new Map<string, string>(),
};

const normalize = (value: string) => value.replace(/\s+/g, ' ').trim();

const dynamicTerms: Translation[] = [
    { en: 'customers', es: 'clientes' },
    { en: 'overdue activities', es: 'actividades vencidas' },
    { en: 'staff members', es: 'miembros del equipo' },
    { en: 'opportunities', es: 'oportunidades' },
    { en: 'mapped', es: 'mapeadas' },
];

for (const [source, translation] of Object.entries(
    catalog as Record<string, Translation>,
)) {
    const variants = [source, translation.en, translation.es];

    for (const variant of variants) {
        const key = normalize(variant);

        if (!key) {
            continue;
        }

        lookups.en.set(key, translation.en);
        lookups.es.set(key, translation.es);
    }
}

for (const [source, translation] of Object.entries(terminologyOverrides)) {
    for (const variant of [source, translation.en, translation.es]) {
        const key = normalize(variant);
        lookups.en.set(key, translation.en);
        lookups.es.set(key, translation.es);
    }
}

export function translate(value: string, locale: Locale): string {
    const key = normalize(value);
    const directTranslation = lookups[locale].get(key);

    if (directTranslation) {
        return directTranslation;
    }

    const numericLabel = key.match(/^(\d+(?:[.,]\d+)?)\s+(.+)$/);

    if (numericLabel) {
        const [, amount, label] = numericLabel;
        const term = dynamicTerms.find(
            (candidate) => candidate.en === label || candidate.es === label,
        );

        if (term) {
            return `${amount} ${term[locale]}`;
        }
    }

    for (const [sourcePrefix, targetPrefix] of [
        ['Mostrar solución: ', 'Show solution: '],
        ['Ver proyecto: ', 'View project: '],
    ]) {
        if (key.startsWith(sourcePrefix)) {
            const subject = key.slice(sourcePrefix.length);

            return locale === 'en'
                ? `${targetPrefix}${translate(subject, locale)}`
                : `${sourcePrefix}${translate(subject, locale)}`;
        }
    }

    return value;
}

export function hasTranslation(value: string): boolean {
    const key = normalize(value);

    return lookups.en.has(key) && lookups.es.has(key);
}
