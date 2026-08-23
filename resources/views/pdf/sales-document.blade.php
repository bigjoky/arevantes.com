<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><style>
    @page { margin: 34mm 15mm 22mm; }
    body { font-family: DejaVu Sans, sans-serif; color: #17221c; font-size: 9pt; line-height: 1.4; }
    header { position: fixed; top: -25mm; left: 0; right: 0; height: 22mm; border-bottom: 2px solid #1f6b45; }
    .brand { font-size: 19pt; font-weight: bold; color: #155a39; }
    .doc-title { float: right; text-align: right; font-size: 14pt; font-weight: bold; text-transform: uppercase; }
    .muted { color: #66736b; } .right { text-align: right; } .strong { font-weight: bold; }
    .parties { width: 100%; margin-bottom: 8mm; border-collapse: collapse; }
    .parties td { width: 50%; vertical-align: top; padding: 4mm; border: 1px solid #d8dfda; }
    .label { color: #66736b; font-size: 8pt; text-transform: uppercase; margin-bottom: 1mm; }
    .meta { width: 100%; margin-bottom: 7mm; border-collapse: collapse; background: #f3f6f4; }
    .meta td { padding: 3mm; border-right: 1px solid #d8dfda; }
    table.lines { width: 100%; border-collapse: collapse; }
    .lines th { background: #1f6b45; color: white; padding: 2.5mm 2mm; font-size: 8pt; text-align: left; }
    .lines td { padding: 2.5mm 2mm; border-bottom: 1px solid #d8dfda; vertical-align: top; }
    .components { margin: 1.5mm 0 0; padding-left: 4mm; color: #536159; font-size: 8pt; }
    .components li { margin-bottom: .6mm; }
    .totals { width: 43%; margin: 7mm 0 0 auto; border-collapse: collapse; }
    .totals td { padding: 2mm; border-bottom: 1px solid #d8dfda; }
    .totals .grand td { font-size: 12pt; font-weight: bold; color: #155a39; border-top: 2px solid #1f6b45; }
    .notes { margin-top: 9mm; padding-top: 4mm; border-top: 1px solid #d8dfda; white-space: pre-wrap; }
    footer { position: fixed; bottom: -15mm; left: 0; right: 0; border-top: 1px solid #d8dfda; padding-top: 2mm; font-size: 8pt; color: #66736b; }
    .page:after { content: counter(page); }
</style></head>
<body>
@php
    $titles = ['quote' => 'Presupuesto', 'order' => 'Pedido', 'delivery_note' => 'Albarán', 'invoice' => 'Factura', 'credit_note' => 'Factura rectificativa'];
    $customer = $document->customer_snapshot ?? [];
    $customerName = $customer['company_name'] ?? trim(($customer['first_name'] ?? '').' '.($customer['last_name'] ?? ''));
@endphp
<header>@if($company['logo_data'] ?? null)<img src="{{ $company['logo_data'] }}" style="max-width:48mm;max-height:17mm">@else<span class="brand">{{ strtoupper($company['trade_name'] ?: $company['legal_name']) }}</span>@endif<div class="doc-title">{{ $titles[$kind] ?? $kind }}<br><span class="muted" style="font-size:9pt">{{ $document->number }}</span></div></header>
@if($isDraft ?? (isset($document->status) && $document->status === 'draft'))<div style="position:fixed;top:44%;left:20%;transform:rotate(-28deg);font-size:52pt;color:#e1e7e3;z-index:-1;font-weight:bold">BORRADOR</div>@endif
<footer><span>{{ $company['legal_name'] }} @if($company['tax_id']) · {{ $company['tax_id'] }} @endif · {{ $company['email'] }}</span><span style="float:right">Página <span class="page"></span></span></footer>

<table class="parties"><tr><td><div class="label">Emisor</div><div class="strong">{{ $company['legal_name'] }}</div>@if($company['tax_id'])<div>{{ $company['tax_id'] }}</div>@endif<div>{{ collect([$company['address'], $company['postal_code'], $company['city'], $company['province']])->filter()->join(', ') }}</div><div>{{ $company['email'] }} @if($company['phone']) · {{ $company['phone'] }} @endif</div></td>
<td><div class="label">Cliente</div><div class="strong">{{ $customerName ?: 'Cliente no especificado' }}</div><div>{{ $customer['tax_id'] ?? $customer['document_number'] ?? '' }}</div><div>{{ collect([$customer['address_line_1'] ?? null, $customer['address_line_2'] ?? null, $customer['postal_code'] ?? null, $customer['city'] ?? null, $customer['province_state'] ?? null, $customer['country'] ?? null])->filter()->join(', ') }}</div><div>{{ $customer['billing_email'] ?? $customer['email'] ?? '' }}</div></td></tr></table>

<table class="meta">
    <tr>
        <td><div class="label">Número</div><div class="strong">{{ $document->number }}</div></td>
        <td><div class="label">Fecha</div>{{ $document->issue_date?->format('d/m/Y') }}</td>
        @if($kind === 'quote')
            <td><div class="label">Válido hasta</div>{{ $document->valid_until?->format('d/m/Y') ?? '-' }}</td>
        @elseif($kind === 'invoice')
            <td><div class="label">Vencimiento</div>{{ $document->due_date?->format('d/m/Y') ?? '-' }}</td>
            @if($document->billing_percentage)
                <td><div class="label">Hito</div><div class="strong">{{ $document->billing_label }}</div>{{ rtrim(rtrim(number_format((float)$document->billing_percentage, 2, ',', '.'), '0'), ',') }}%</td>
            @endif
        @endif
    </tr>
</table>

<table class="lines"><thead><tr><th>Descripción</th><th class="right">Cantidad</th><th class="right">Precio</th><th class="right">Dto.</th><th class="right">IVA</th><th class="right">Total</th></tr></thead><tbody>
@foreach($document->lines as $line)<tr><td><span class="strong">{{ $line->description }}</span>
@if($kind === 'quote' && ($line->selection_mode ?? 'included') !== 'included')<div class="muted" style="font-size:8pt">{{ $line->selection_mode === 'optional' ? 'OPCIONAL' : 'ALTERNATIVA · '.($line->option_group ?: 'sin grupo') }} · {{ $line->is_selected ? 'seleccionada' : 'no incluida en el total' }}</div>@endif
@if($kind === 'quote' && $line->relationLoaded('components'))
@php($visibleComponents = $line->components->where('visibility', '!=', 'hidden'))
@if($visibleComponents->isNotEmpty())<ul class="components">@foreach($visibleComponents as $component)<li>@if($component->visibility === 'detailed'){{ rtrim(rtrim(number_format((float)$component->quantity * (float)$line->quantity, 3, ',', '.'), '0'), ',') }}{{ $component->unit?->symbol ? ' '.$component->unit->symbol : '' }} · @endif{{ $component->description }} <span class="muted">(incluido)</span></li>@endforeach</ul>@endif
@endif
</td><td class="right">{{ rtrim(rtrim(number_format((float)$line->quantity, 3, ',', '.'), '0'), ',') }}</td><td class="right">{{ number_format((float)$line->unit_price, 2, ',', '.') }} €</td><td class="right">{{ number_format((float)$line->discount_percent, 2, ',', '.') }}%</td><td class="right">{{ number_format((float)$line->tax_percent, 2, ',', '.') }}%</td><td class="right strong">{{ number_format((float)$line->total, 2, ',', '.') }} €</td></tr>@endforeach
</tbody></table>
<table class="totals"><tr><td>Subtotal</td><td class="right">{{ number_format((float)$document->subtotal, 2, ',', '.') }} €</td></tr>@if((float)$document->discount_amount > 0)<tr><td>Descuentos</td><td class="right">-{{ number_format((float)$document->discount_amount, 2, ',', '.') }} €</td></tr>@endif<tr><td>IVA</td><td class="right">{{ number_format((float)$document->tax_amount, 2, ',', '.') }} €</td></tr><tr class="grand"><td>Total</td><td class="right">{{ number_format((float)$document->amount, 2, ',', '.') }} €</td></tr></table>
@if($document->notes)<div class="notes"><div class="label">Observaciones</div>{{ $document->notes }}</div>@endif
@if(($kind === 'invoice' || $kind === 'quote') && ($company['iban'] ?? null))<div class="notes"><div class="label">Datos de pago</div>IBAN: {{ $company['iban'] }} @if($company['bic'] ?? null) · BIC: {{ $company['bic'] }} @endif</div>@endif
@if($company['invoice_footer'] ?? null)<div class="notes muted" style="font-size:8pt">{{ $company['invoice_footer'] }}</div>@endif
</body></html>
