<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"></head><body>
<table><tr><th colspan="8">Analítica · {{ config('analytics.areas.'.$filter->area) }} · {{ $filter->from->toDateString() }} / {{ $filter->to->toDateString() }}</th></tr>
<tr><th>KPI</th><th>Valor</th><th>Anterior</th><th>Objetivo/presupuesto</th><th>Calidad %</th><th>Propietario</th><th>Fuente</th><th>Fórmula</th></tr>
@foreach($metrics as $metric)<tr><td>{{ $metric['name'] }}</td><td>{{ $metric['value'] }}</td><td>{{ $metric['previous'] }}</td><td>{{ $metric['target'] }}</td><td>{{ $metric['quality']['score'] }}</td><td>{{ $metric['owner'] }}</td><td>{{ $metric['source'] }}</td><td>{{ $metric['formula'] }}</td></tr>@endforeach</table>
@foreach($metrics as $metric)<br><table><tr><th colspan="6">{{ $metric['name'] }} · registros fuente</th></tr><tr><th>ID</th><th>Fecha</th><th>Referencia</th><th>Proyecto</th><th>Estado</th><th>Valor</th></tr>
@foreach($records[$metric['key']] as $row)<tr><td>{{ $row['id'] }}</td><td>{{ $row['date'] }}</td><td>{{ $row['reference'] }}</td><td>{{ $row['project'] }}</td><td>{{ $row['status'] }}</td><td>{{ $row['value'] }}</td></tr>@endforeach</table>@endforeach
</body></html>
