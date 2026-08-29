<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28mm 15mm 20mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #1c2922; font-size: 9pt; line-height: 1.42; }
        header { position: fixed; top: -20mm; left: 0; right: 0; height: 16mm; border-bottom: 1.5px solid #1f6b45; }
        footer { position: fixed; bottom: -13mm; left: 0; right: 0; padding-top: 2.5mm; border-top: 1px solid #d9e1dc; color: #65736b; font-size: 7.5pt; }
        .logo { max-width: 42mm; max-height: 13mm; }
        .brand { color: #155a39; font-size: 18pt; font-weight: bold; letter-spacing: .2pt; }
        .header-reference { float: right; padding-top: 2mm; text-align: right; color: #526159; font-size: 8pt; }
        .header-reference strong { display: block; color: #183e2b; font-size: 10pt; }
        .page-number:after { content: counter(page); }
        .muted { color: #65736b; }
        .strong { font-weight: bold; }
        .right { text-align: right; }
        .center { text-align: center; }
        .nowrap { white-space: nowrap; }
        .eyebrow { margin-bottom: 1.5mm; color: #1f6b45; font-size: 8pt; font-weight: bold; letter-spacing: 1.2pt; text-transform: uppercase; }
        .hero { width: 100%; margin-bottom: 4mm; border-collapse: collapse; background: #eef6f1; border-left: 4px solid #1f6b45; }
        .hero td { padding: 4mm; vertical-align: top; }
        .hero-main { width: 65%; }
        .hero-side { width: 35%; border-left: 1px solid #d4e3da; text-align: right; }
        h1 { margin: 0 0 1.5mm; color: #123c28; font-size: 21pt; line-height: 1.05; font-weight: bold; }
        .hero-subtitle { color: #43534a; font-size: 10pt; }
        .hero-number { color: #123c28; font-size: 13pt; font-weight: bold; }
        .status { display: inline-block; margin-top: 2mm; padding: 1mm 2.5mm; border: 1px solid #8fb49e; border-radius: 10px; color: #1f6b45; font-size: 7pt; font-weight: bold; text-transform: uppercase; }
        .section { margin-top: 4mm; }
        .section-title { margin: 0 0 2.5mm; padding-bottom: 1.5mm; border-bottom: 1px solid #cfd9d3; color: #183e2b; font-size: 11pt; font-weight: bold; }
        .parties { width: 100%; margin-bottom: 3mm; border-spacing: 3mm 0; border-collapse: separate; }
        .parties td { width: 50%; padding: 3mm; vertical-align: top; border: 1px solid #d9e1dc; border-radius: 3px; }
        .label { margin-bottom: 1mm; color: #65736b; font-size: 7pt; font-weight: bold; letter-spacing: .7pt; text-transform: uppercase; }
        .party-name { margin-bottom: 1mm; color: #183e2b; font-size: 10pt; font-weight: bold; }
        .context { width: 100%; margin-bottom: 3.5mm; border-collapse: collapse; background: #f7f9f8; }
        .context td { padding: 2.5mm; vertical-align: top; border-right: 1px solid #dfe5e1; }
        .context td:last-child { border-right: none; }
        .context-value { color: #24362c; font-weight: bold; }
        table.lines { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .lines thead { display: table-header-group; }
        .lines tr { page-break-inside: avoid; }
        .lines th { padding: 2.5mm 1.8mm; background: #1f6b45; color: white; font-size: 7.3pt; font-weight: bold; letter-spacing: .3pt; text-align: left; text-transform: uppercase; }
        .lines th.right { text-align: right; }
        .lines td { padding: 3mm 1.8mm; border-bottom: 1px solid #dce3df; vertical-align: top; }
        .line-index { color: #789087; font-size: 8pt; font-weight: bold; }
        .line-description { color: #182820; font-weight: bold; }
        .line-tag { display: inline-block; margin-top: 1mm; padding: .6mm 1.7mm; border-radius: 8px; background: #e7f1eb; color: #286746; font-size: 6.7pt; font-weight: bold; text-transform: uppercase; }
        .components { margin-top: 2mm; padding-top: 1.5mm; border-top: 1px dotted #ccd7d1; color: #526159; font-size: 7.5pt; }
        .component { margin: .7mm 0; page-break-inside: avoid; }
        .component-bullet { color: #2f7b52; font-weight: bold; }
        .continuation-header { width: 100%; margin-bottom: 5mm; border-collapse: collapse; border-bottom: 1.5px solid #1f6b45; page-break-before: always; }
        .continuation-header td { padding: 0 0 3mm; vertical-align: bottom; }
        .continuation-header td:last-child { color: #526159; font-size: 8pt; text-align: right; }
        .scope-details { margin-top: 3mm; padding: 3mm 3.5mm; border: 1px solid #d8e1dc; background: #fafcfb; page-break-inside: avoid; }
        .scope-details-title { margin-bottom: 1.5mm; color: #183e2b; font-size: 8pt; font-weight: bold; }
        .scope-details .components { margin-top: 0; padding-top: 0; border-top: none; }
        .option-box { margin-top: 6mm; padding: 3.5mm; border: 1px solid #cfdad4; background: #fafcfb; page-break-inside: avoid; }
        .option-note { margin-bottom: 2.5mm; color: #526159; font-size: 8pt; }
        .summary { width: 100%; margin-top: 6mm; border-collapse: collapse; page-break-inside: avoid; }
        .summary-spacer { width: 50%; }
        .summary-box { width: 50%; padding-left: 5mm; vertical-align: top; }
        .totals { width: 100%; border-collapse: collapse; }
        .totals td { padding: 1.8mm 2mm; border-bottom: 1px solid #dce3df; }
        .totals .base td { background: #f5f8f6; }
        .totals .grand td { padding-top: 3mm; padding-bottom: 3mm; border-top: 2px solid #1f6b45; border-bottom: none; color: #155a39; font-size: 14pt; font-weight: bold; }
        .tax-detail { margin-top: 2mm; color: #65736b; font-size: 7.2pt; text-align: right; }
        .commercial-terms { width: 100%; margin-top: 6mm; border-collapse: collapse; page-break-inside: avoid; }
        .commercial-terms td { width: 50%; padding: 4mm; vertical-align: top; border: 1px solid #d9e1dc; }
        .commercial-terms td + td { border-left: none; }
        .notes { margin-top: 6mm; padding: 4mm; border-left: 3px solid #9ab8a6; background: #f6f8f7; white-space: pre-wrap; page-break-inside: avoid; }
        .acceptance { margin-top: 7mm; padding-top: 4mm; border-top: 1.5px solid #1f6b45; page-break-inside: avoid; }
        .acceptance-table { width: 100%; margin-top: 4mm; border-collapse: separate; border-spacing: 3mm 0; }
        .acceptance-table td { width: 33.33%; height: 14mm; padding: 2mm 0; vertical-align: bottom; border-bottom: 1px solid #8e9d95; color: #65736b; font-size: 7.5pt; }
        .legal { margin-top: 6mm; color: #6c7871; font-size: 7.2pt; line-height: 1.35; page-break-inside: avoid; }
        .watermark { position: fixed; top: 42%; left: 20%; z-index: -1; color: #edf1ee; font-size: 42pt; font-weight: bold; transform: rotate(-28deg); }
    </style>
</head>
<body>
@php
    $customer = $document->customer_snapshot ?? [];
    $customerName = $customer['company_name'] ?? trim(($customer['first_name'] ?? '').' '.($customer['last_name'] ?? ''));
    $customerTaxId = filled($customer['tax_id'] ?? null) ? $customer['tax_id'] : ($customer['document_number'] ?? null);
    $companyName = $company['trade_name'] ?? null ?: ($company['legal_name'] ?? 'Arevantes');
    $includedLines = $document->lines->filter(fn ($line) => ($line->selection_mode ?? 'included') === 'included' || (bool) $line->is_selected);
    $optionalLines = $document->lines->reject(fn ($line) => ($line->selection_mode ?? 'included') === 'included' || (bool) $line->is_selected);
    $taxSummary = $includedLines->groupBy(fn ($line) => number_format((float) $line->tax_percent, 2, '.', ''))->map(fn ($lines, $rate) => [
        'rate' => (float) $rate,
        'base' => $lines->sum(fn ($line) => (float) $line->subtotal - (float) $line->discount_amount),
        'tax' => $lines->sum(fn ($line) => (float) $line->tax_amount),
    ])->sortBy('rate');
    $serviceLabels = [
        'solar' => 'Energía solar fotovoltaica',
        'solar_pv' => 'Energía solar fotovoltaica',
        'ev_charging' => 'Recarga de vehículo eléctrico',
        'hvac' => 'Climatización',
        'electrical' => 'Instalaciones eléctricas',
        'security' => 'Alarmas y sistemas de seguridad',
    ];
    $statusLabels = ['draft' => 'Borrador', 'sent' => 'Enviada', 'accepted' => 'Aceptada', 'rejected' => 'Rechazada', 'expired' => 'Caducada'];
    $projectName = $document->opportunity?->title ?: ($document->installation?->site_name ?: null);
@endphp

<header>
    @if(($company['logo_data'] ?? null) && !($company['logo_is_fallback'] ?? false))
        <img class="logo" src="{{ $company['logo_data'] }}" alt="{{ $companyName }}">
    @else
        <span class="brand">{{ strtoupper($companyName) }}</span>
    @endif
    <div class="header-reference"><strong>{{ $document->number }}</strong>Propuesta comercial · versión {{ $document->version ?? 1 }}</div>
</header>

@if($isDraft ?? ($document->status === 'draft'))
    <div class="watermark">BORRADOR</div>
@endif

<footer>
    <span>{{ $company['legal_name'] ?? $companyName }}@if($company['tax_id'] ?? null) · {{ $company['tax_id'] }}@endif @if($company['website'] ?? null) · {{ $company['website'] }}@endif</span>
    <span style="float:right">{{ $document->number }} · Página <span class="page-number"></span></span>
</footer>

<table class="hero">
    <tr>
        <td class="hero-main">
            <div class="eyebrow">Propuesta comercial</div>
            <h1>{{ $projectName ?: 'Una solución preparada para ti' }}</h1>
            <div class="hero-subtitle">
                @if($document->service_type)
                    {{ $serviceLabels[$document->service_type] ?? ucfirst(str_replace('_', ' ', $document->service_type)) }}
                @else
                    Alcance, inversión y condiciones de la propuesta
                @endif
            </div>
        </td>
        <td class="hero-side">
            <div class="label">Referencia</div>
            <div class="hero-number">{{ $document->number }}</div>
            <div class="muted">Versión {{ $document->version ?? 1 }}</div>
            <div class="status">{{ $statusLabels[$document->status] ?? $document->status }}</div>
        </td>
    </tr>
</table>

<table class="parties">
    <tr>
        <td>
            <div class="label">Propuesta para</div>
            <div class="party-name">{{ $customerName ?: 'Cliente no especificado' }}</div>
            @if($customerTaxId)<div>{{ $customerTaxId }}</div>@endif
            <div>{{ collect([$customer['address_line_1'] ?? null, $customer['address_line_2'] ?? null, $customer['postal_code'] ?? null, $customer['city'] ?? null, $customer['province_state'] ?? null, $customer['country'] ?? null])->filter()->unique()->join(', ') }}</div>
            @if($customer['billing_email'] ?? $customer['email'] ?? null)<div>{{ $customer['billing_email'] ?? $customer['email'] }}</div>@endif
        </td>
        <td>
            <div class="label">Preparada por</div>
            <div class="party-name">{{ $companyName }}</div>
            @if(($company['legal_name'] ?? null) && $company['legal_name'] !== $companyName)<div>{{ $company['legal_name'] }}</div>@endif
            @if($company['tax_id'] ?? null)<div>{{ $company['tax_id'] }}</div>@endif
            <div>{{ collect([$company['address'] ?? null, $company['postal_code'] ?? null, $company['city'] ?? null, $company['province'] ?? null])->filter()->join(', ') }}</div>
            <div>{{ collect([$company['email'] ?? null, $company['phone'] ?? null])->filter()->join(' · ') }}</div>
        </td>
    </tr>
</table>

<table class="context">
    <tr>
        <td><div class="label">Fecha de emisión</div><div class="context-value">{{ $document->issue_date?->format('d/m/Y') ?: '-' }}</div></td>
        <td><div class="label">Validez</div><div class="context-value">{{ $document->valid_until?->format('d/m/Y') ?: 'Sin fecha límite indicada' }}</div></td>
        @if($document->opportunity)
            <td><div class="label">Oportunidad</div><div class="context-value">{{ $document->opportunity->code }}</div></td>
        @elseif($document->installation)
            <td><div class="label">Proyecto</div><div class="context-value">{{ $document->installation->project_reference ?: $document->installation->site_name }}</div></td>
        @endif
    </tr>
</table>

<div class="section">
    <div class="section-title">Alcance e inversión</div>
    <table class="lines">
        <colgroup>
            <col style="width:6%">
            <col style="width:47%">
            <col style="width:8%">
            <col style="width:14%">
            <col style="width:8%">
            <col style="width:17%">
        </colgroup>
        <thead>
            <tr><th class="center">#</th><th>Descripción</th><th class="right">Cant.</th><th class="right">Precio</th><th class="right">IVA</th><th class="right">Importe</th></tr>
        </thead>
        <tbody>
        @forelse($includedLines as $line)
            <tr>
                <td class="center"><span class="line-index">{{ str_pad((string) $line->position, 2, '0', STR_PAD_LEFT) }}</span></td>
                <td>
                    <div class="line-description">{{ $line->description }}</div>
                    @if(($line->selection_mode ?? 'included') !== 'included')
                        <span class="line-tag">{{ $line->selection_mode === 'alternative' ? 'Alternativa seleccionada' : 'Opción seleccionada' }}</span>
                    @endif
                </td>
                <td class="right nowrap">{{ rtrim(rtrim(number_format((float) $line->quantity, 3, ',', '.'), '0'), ',') }}</td>
                <td class="right nowrap">
                    {{ number_format((float) $line->unit_price, 2, ',', '.') }} €
                    @if((float) $line->discount_percent > 0)<div class="muted">-{{ rtrim(rtrim(number_format((float) $line->discount_percent, 2, ',', '.'), '0'), ',') }}%</div>@endif
                </td>
                <td class="right nowrap">{{ rtrim(rtrim(number_format((float) $line->tax_percent, 2, ',', '.'), '0'), ',') }}%</td>
                <td class="right nowrap strong">{{ number_format((float) $line->total, 2, ',', '.') }} €</td>
            </tr>
        @empty
            <tr><td colspan="6" class="center muted">No hay partidas incluidas en esta propuesta.</td></tr>
        @endforelse
        </tbody>
    </table>

    @foreach($includedLines as $line)
        @php($visibleComponents = $line->relationLoaded('components') ? $line->components->where('visibility', '!=', 'hidden') : collect())
        @if($visibleComponents->isNotEmpty())
            @if($loop->first)
                <table class="continuation-header"><tr>
                    <td><span class="brand">{{ strtoupper($companyName) }}</span></td>
                    <td><span class="strong">{{ $document->number }}</span><br>Continuación de la propuesta · versión {{ $document->version ?? 1 }}</td>
                </tr></table>
            @endif
            <div class="scope-details">
                <div class="scope-details-title">Composición de {{ str_pad((string) $line->position, 2, '0', STR_PAD_LEFT) }} · {{ $line->description }}</div>
                <div class="components">
                    @foreach($visibleComponents as $component)
                        <div class="component" style="padding-left: {{ max(0, ((int) $component->depth) - 1) * 2.5 }}mm">
                            <span class="component-bullet">›</span>
                            @if($component->visibility === 'detailed')
                                {{ rtrim(rtrim(number_format($line->effectiveComponentQuantity($component), 3, ',', '.'), '0'), ',') }}{{ $component->unit?->symbol ? ' '.$component->unit->symbol : '' }} ·
                            @endif
                            {{ $component->description }}
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</div>

@if($optionalLines->isNotEmpty())
    <div class="option-box">
        <div class="section-title">Opciones disponibles no incluidas</div>
        <div class="option-note">Estas partidas amplían o sustituyen el alcance principal y no forman parte del total de la propuesta mientras no sean seleccionadas.</div>
        <table class="lines">
            <colgroup><col style="width:52%"><col style="width:16%"><col style="width:14%"><col style="width:18%"></colgroup>
            <thead><tr><th>Opción</th><th>Grupo</th><th class="right">Cant.</th><th class="right">Importe</th></tr></thead>
            <tbody>
            @foreach($optionalLines as $line)
                <tr>
                    <td><div class="line-description">{{ $line->description }}</div><span class="line-tag">{{ $line->selection_mode === 'alternative' ? 'Alternativa' : 'Opcional' }}</span></td>
                    <td>{{ $line->option_group ?: '-' }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format((float) $line->quantity, 3, ',', '.'), '0'), ',') }}</td>
                    <td class="right strong">{{ number_format((float) $line->total, 2, ',', '.') }} €<div class="muted" style="font-size:7pt">IVA incluido</div></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

<table class="summary">
    <tr>
        <td class="summary-spacer"></td>
        <td class="summary-box">
            <table class="totals">
                <tr><td>Subtotal</td><td class="right">{{ number_format((float) $document->subtotal, 2, ',', '.') }} €</td></tr>
                @if((float) $document->discount_amount > 0)<tr><td>Descuentos</td><td class="right">-{{ number_format((float) $document->discount_amount, 2, ',', '.') }} €</td></tr>@endif
                <tr class="base"><td>Base imponible</td><td class="right">{{ number_format((float) $document->subtotal - (float) $document->discount_amount, 2, ',', '.') }} €</td></tr>
                <tr><td>Impuestos</td><td class="right">{{ number_format((float) $document->tax_amount, 2, ',', '.') }} €</td></tr>
                <tr class="grand"><td>Total</td><td class="right">{{ number_format((float) $document->amount, 2, ',', '.') }} €</td></tr>
            </table>
            @if($taxSummary->isNotEmpty())
                <div class="tax-detail">
                    @foreach($taxSummary as $tax)
                        Base {{ rtrim(rtrim(number_format($tax['rate'], 2, ',', '.'), '0'), ',') }}%: {{ number_format($tax['base'], 2, ',', '.') }} € · cuota {{ number_format($tax['tax'], 2, ',', '.') }} €@if(!$loop->last)<br>@endif
                    @endforeach
                </div>
            @endif
        </td>
    </tr>
</table>

<table class="commercial-terms">
    <tr>
        <td>
            <div class="label">Condiciones de aceptación</div>
            @if((float) $document->acceptance_payment_percentage > 0)
                <div class="strong">Anticipo del {{ rtrim(rtrim(number_format((float) $document->acceptance_payment_percentage, 2, ',', '.'), '0'), ',') }}%</div>
                <div class="muted">Vencimiento: {{ $document->acceptance_payment_due_days }} días desde la aceptación.</div>
            @else
                <div class="strong">Sin anticipo a la aceptación</div>
                <div class="muted">La facturación se realizará conforme a los hitos acordados.</div>
            @endif
        </td>
        <td>
            <div class="label">Validez de la propuesta</div>
            <div class="strong">{{ $document->valid_until?->format('d/m/Y') ?: 'Sin fecha límite indicada' }}</div>
            <div class="muted">Los cambios de alcance se documentarán en una nueva versión.</div>
        </td>
    </tr>
</table>

@if($document->notes)
    <div class="notes"><div class="label">Observaciones y condiciones particulares</div>{{ $document->notes }}</div>
@endif

@if($company['iban'] ?? null)
    <div class="notes"><div class="label">Datos para pagos por transferencia</div><span class="strong">IBAN {{ $company['iban'] }}</span>@if($company['bic'] ?? null) · BIC {{ $company['bic'] }}@endif</div>
@endif

<div class="acceptance">
    <div class="section-title">Aceptación de la propuesta</div>
    <div class="muted">La aceptación puede formalizarse mediante el enlace seguro recibido o, si se entrega este documento impreso, mediante firma. La aceptación se refiere a la versión {{ $document->version ?? 1 }} de la propuesta {{ $document->number }}.</div>
    <table class="acceptance-table"><tr><td>Nombre y apellidos</td><td>Fecha</td><td>Firma</td></tr></table>
</div>

@if($company['invoice_footer'] ?? null)
    <div class="legal">{{ $company['invoice_footer'] }}</div>
@endif
</body>
</html>
