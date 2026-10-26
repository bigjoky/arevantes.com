# Reservas de numeración comercial

La eliminación autorizada del duplicado OPP-2026-00010 y su borrador
PRE-2026-00006 deja esos dos códigos disponibles. Los identificadores internos
no se reutilizan: conservarlos independientes evita atribuir auditoría antigua
a una oportunidad nueva.

`commercial_number_reservations` permite reservar expresamente un número de
oportunidad o presupuesto, con ejercicio, motivo y fecha de consumo. Un borrado
no crea reservas automáticamente. No se aplica a facturas ni a otros documentos
fiscales. Las oportunidades archivadas siguen ocupando su código.

Todas las altas de oportunidades (CRM, móvil, web y renovaciones) usan
`OpportunityNumber`. El contador anual se inicializa desde los códigos existentes
y el identificador de la primera alta; después evoluciona independientemente del
ID. El bloqueo del contador y el consumo de la reserva pertenecen a la misma
transacción del alta. Los presupuestos conservan sus bloqueos de ejercicio/serie;
consumir una reserva no adelanta `document_series.next_number`.

Si falla la creación, la reserva queda pendiente. Un código reservado que ya esté
ocupado detiene el alta, en lugar de reutilizarlo. Cada reserva se consume una vez
y queda registrada. No se recorren otros huecos históricos.

## Activación de las dos reservas autorizadas

Después de publicar y aplicar la migración, ejecutar en Tinker de producción:

```php
require base_path('scripts/reserve-duplicate-commercial-numbers.php');
```

El script comprueba destino, ejercicio abierto, serie PRE activa y códigos
desocupados. Serializa la operación con los emisores y conserva los contadores.
Es idempotente mientras las reservas estén pendientes y rechaza rearmar una
reserva consumida. No crea oportunidades ni presupuestos ficticios.

Con el estado observado el 26/10/2026, el orden esperado es:

| Tipo | Próximo código | Continuación habitual |
| --- | --- | --- |
| Oportunidad | OPP-2026-00010 | OPP-2026-00015, OPP-2026-00016… |
| Presupuesto | PRE-2026-00006 | PRE-2026-00008, PRE-2026-00009… |

`CommercialNumberReservationTest` verifica consumo, continuidad, aislamiento por
tipo/ejercicio, rechazo de archivados y rollback. `FiscalConcurrencyTest` prueba
dos procesos reales contra PostgreSQL aislado, incluidos la inicialización del
contador y el consumo único de cada reserva.
