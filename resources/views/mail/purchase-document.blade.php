<!doctype html>
<html lang="es">
<body style="font-family:Arial,sans-serif;color:#17221c;line-height:1.5">
<div style="max-width:620px;margin:auto;padding:32px 20px">
    <h1 style="font-size:22px;color:#155a39">{{ config('app.name') }}</h1>
    <p>Hola,</p>
    <p>Adjuntamos {{ $document->type === 'purchase_rfq' ? 'nuestra solicitud de oferta' : 'nuestro pedido de compra' }} <strong>{{ $document->number }}</strong>.</p>
    @if($messageText)<p style="white-space:pre-wrap">{{ $messageText }}</p>@endif
    <p style="color:#66736b;font-size:13px">Este mensaje incluye el documento en formato PDF.</p>
</div>
</body>
</html>
