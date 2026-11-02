<!doctype html>
<html lang="es"><body style="font-family:Arial,sans-serif;color:#123a32;line-height:1.6">
<div style="max-width:600px;margin:auto;padding:32px;border-top:5px solid #86c544">
<h1 style="color:#004638">AREVANTES</h1>
@if ($visitor)
<h2>Hemos recibido tu consulta</h2>
<p>Gracias por contactar con AREVANTES. Nuestro equipo revisará tu solicitud y se pondrá en contacto contigo.</p>
@else
<h2>Nueva solicitud desde la web</h2>
<p>Hay una solicitud disponible para su gestión en Comercial.</p>
@if ($opportunityUrl)<p><a href="{{ $opportunityUrl }}">Abrir oportunidad en el ERP</a></p>@endif
@endif
<p>AREVANTES · Baena, Córdoba</p>
</div></body></html>
