<!doctype html>
<html lang="es"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;color:#172033;font-size:9px}h1{font-size:18px;margin:0 0 4px}h2{font-size:12px;margin:16px 0 6px}.meta{color:#667085;margin-bottom:14px}.cards{width:100%;border-collapse:collapse}.cards td{border:1px solid #d8dee9;padding:8px;width:25%;vertical-align:top}.value{font-size:16px;font-weight:bold}.muted{color:#667085;font-size:8px}table.records{width:100%;border-collapse:collapse}table.records th,table.records td{border-bottom:1px solid #e5e7eb;padding:4px;text-align:left}table.records th{background:#f4f6f8}.number{text-align:right!important}
</style></head><body>
<h1>Informe de analítica · {{ config('analytics.areas.'.$filter->area) }}</h1>
<div class="meta">{{ $filter->from->format('d/m/Y') }} – {{ $filter->to->format('d/m/Y') }} · Generado {{ now()->format('d/m/Y H:i') }}</div>
<table class="cards"><tr>@foreach($metrics as $metric)<td><strong>{{ $metric['name'] }}</strong><div class="value">{{ $metric['unit']==='currency' ? number_format($metric['value'],2,',','.').' €' : number_format($metric['value'],2,',','.').($metric['unit']==='percent'?' %':'') }}</div><div class="muted">Anterior: {{ number_format($metric['previous'],2,',','.') }} · Calidad: {{ $metric['quality']['score'] }}%</div></td>@if($loop->iteration % 4 === 0)</tr><tr>@endif @endforeach</tr></table>
@foreach($metrics as $metric)
<h2>{{ $metric['name'] }}</h2><div class="muted">Fórmula: {{ $metric['formula'] }} · Fuente: {{ $metric['source'] }} · Propietario: {{ $metric['owner'] }}</div>
<table class="records"><thead><tr><th>Fecha</th><th>Referencia</th><th>Proyecto</th><th>Estado</th><th class="number">Valor</th></tr></thead><tbody>
@forelse($records[$metric['key']] as $row)<tr><td>{{ $row['date'] }}</td><td>{{ $row['reference'] }}</td><td>{{ $row['project'] }}</td><td>{{ $row['status'] }}</td><td class="number">{{ number_format($row['value'],2,',','.') }}</td></tr>@empty<tr><td colspan="5">Sin registros</td></tr>@endforelse
</tbody></table>@endforeach
</body></html>
