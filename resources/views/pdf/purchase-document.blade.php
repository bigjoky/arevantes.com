<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><style>
    @page { margin: 30mm 15mm 20mm; }
    body { font-family: DejaVu Sans, sans-serif; color:#17221c; font-size:9pt; }
    header { position:fixed; top:-22mm; left:0; right:0; border-bottom:2px solid #1f6b45; height:18mm; }
    .title { float:right; text-align:right; font-size:14pt; font-weight:bold; text-transform:uppercase; }
    .muted { color:#66736b; } .right { text-align:right; } .strong { font-weight:bold; }
    .parties,.meta,.lines,.totals { width:100%; border-collapse:collapse; }
    .parties { margin-bottom:7mm; } .parties td { width:50%; vertical-align:top; padding:4mm; border:1px solid #d8dfda; }
    .meta { margin-bottom:7mm; background:#f3f6f4; } .meta td { padding:3mm; }
    .label { color:#66736b; font-size:8pt; text-transform:uppercase; }
    .lines th { background:#1f6b45; color:white; padding:2.5mm 2mm; text-align:left; }
    .lines td { padding:2.5mm 2mm; border-bottom:1px solid #d8dfda; }
    .totals { width:43%; margin:7mm 0 0 auto; } .totals td { padding:2mm; border-bottom:1px solid #d8dfda; }
    .grand { font-size:12pt; font-weight:bold; color:#155a39; }
    .notes { margin-top:8mm; border-top:1px solid #d8dfda; padding-top:3mm; white-space:pre-wrap; }
</style></head>
<body>
<header>
    @if($company['logo_data'] ?? null)<img src="{{ $company['logo_data'] }}" style="max-width:48mm;max-height:15mm">@else<strong>{{ $company['trade_name'] ?: $company['legal_name'] }}</strong>@endif
    <div class="title">{{ $document->type === 'purchase_rfq' ? 'Solicitud de oferta' : 'Pedido de compra' }}<br><span class="muted" style="font-size:9pt">{{ $document->number }}</span></div>
</header>
<table class="parties"><tr>
    <td><div class="label">Comprador</div><div class="strong">{{ $company['legal_name'] }}</div><div>{{ $company['tax_id'] ?? '' }}</div><div>{{ collect([$company['address'] ?? null, $company['postal_code'] ?? null, $company['city'] ?? null])->filter()->join(', ') }}</div><div>{{ $company['email'] ?? '' }}</div></td>
    <td><div class="label">Proveedor</div><div class="strong">{{ $document->supplier->legal_name }}</div><div>{{ $document->supplier->tax_id }}</div><div>{{ collect([$document->supplier->address_line_1, $document->supplier->address_line_2, $document->supplier->postal_code, $document->supplier->city, $document->supplier->country])->filter()->join(', ') }}</div><div>{{ $document->supplier->email }}</div></td>
</tr></table>
<table class="meta"><tr>
    <td><div class="label">Número</div><strong>{{ $document->number }}</strong></td>
    <td><div class="label">Fecha</div>{{ $document->issue_date?->format('d/m/Y') }}</td>
    <td><div class="label">Entrega prevista</div>{{ $document->expected_delivery_date?->format('d/m/Y') ?? '-' }}@if($document->destinationWarehouse)<br>{{ $document->destinationWarehouse->name }} ({{ $document->destinationWarehouse->code }})@endif</td>
</tr></table>
<table class="lines"><thead><tr><th>Descripción</th><th class="right">Cantidad</th><th class="right">Precio</th><th class="right">Dto.</th><th class="right">IVA</th><th class="right">Total</th></tr></thead><tbody>
@foreach($document->lines as $line)<tr><td>{{ $line->description }}</td><td class="right">{{ rtrim(rtrim(number_format((float)$line->quantity, 3, ',', '.'), '0'), ',') }}</td><td class="right">{{ number_format((float)$line->unit_price, 2, ',', '.') }} €</td><td class="right">{{ number_format((float)$line->discount_percent, 2, ',', '.') }}%</td><td class="right">{{ number_format((float)$line->tax_percent, 2, ',', '.') }}%</td><td class="right strong">{{ number_format((float)$line->total, 2, ',', '.') }} €</td></tr>@endforeach
</tbody></table>
<table class="totals"><tr><td>Subtotal</td><td class="right">{{ number_format((float)$document->subtotal, 2, ',', '.') }} €</td></tr><tr><td>IVA</td><td class="right">{{ number_format((float)$document->tax_amount, 2, ',', '.') }} €</td></tr><tr class="grand"><td>Total</td><td class="right">{{ number_format((float)$document->amount, 2, ',', '.') }} €</td></tr></table>
@if($document->notes)<div class="notes"><div class="label">Observaciones</div>{{ $document->notes }}</div>@endif
</body></html>
