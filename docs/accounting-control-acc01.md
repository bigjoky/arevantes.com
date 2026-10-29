# ACC-01 — Control contable

Estado: implementado en candidato local; publicación pendiente de completar verificaciones. No se han modificado datos productivos en esta intervención.

## Diagnóstico y decisiones

Los observadores de terceros y la creación bancaria provisionaban subcuentas sin una elección explícita. Los generadores consultaban asignaciones actuales al reconstruir operaciones anteriores, por lo que un cambio de ficha podía alterar la contrapartida de un cobro posterior. El diario antiguo limitaba la búsqueda a los primeros 250 asientos. La edición de conceptos no tenía un procedimiento separado de rectificación contable.

Se reutilizan LedgerAccount, JournalEntry/JournalLine, JournalPoster, documentos y conciliación existentes. La selección futura sigue: operación → tercero/producto/concepto → primera regla aplicable → categoría → cuenta general válida. La creación y asociación son expresas, con permisos, unicidad, revisión de versión y auditoría. Los asientos existentes se conservan; los cobros/pagos recuperan la cuenta de control contabilizada, incluidas rectificaciones trazables.

La configuración permite cuentas generales, reglas ordenadas y conceptos ES/EN. Las fichas muestran asignaciones y acceso a configuración. Las operaciones nuevas admiten selección confirmada; un documento ya contabilizado remite al asiento. Las plantillas contables existentes se conservan para otros movimientos.

## Conservación y protección

Una anotación descriptiva genera registro inmutable y auditoría. Reasignar una cuenta genera asiento de ajuste equilibrado, con vista previa, motivo, confirmación y clave idempotente. No cambia importes, fechas fiscales, terceros ni líneas originales. Se protegen ejercicios, periodos, declaraciones preparadas/presentadas y relaciones bancarias. La anulación conserva y revierte los ajustes asociados. La edición fiscal de un documento con rectificación manual contable requiere revisión previa para evitar dos mecanismos contradictorios.

La migración es aditiva: dos categorías opcionales, cinco JSON opcionales y tres tablas. No actualiza registros antiguos. PostgreSQL mantiene RLS y revoca acceso Data API/PUBLIC a las nuevas tablas y secuencias. SQLite añade las claves foráneas mediante ALTER nativo para preservar triggers y la vista de identidad fiscal. La configuración es un singleton explícito: id=1, independiente de secuencias no transaccionales.

El catálogo PGC importado clasifica 438 como activo y 407 como pasivo por herencia de grupo. Las validaciones y proyecciones ahora aplican su naturaleza efectiva (438 pasivo; 407 activo), conservando los metadatos almacenados. Justificación: [PGC oficial consolidado, BOE](https://www.boe.es/buscar/act.php?id=BOE-A-2007-19884&p=20241221&tn=0). No es una reclasificación ni una migración de saldos.

## Recorrido

Contabilidad → Plan de cuentas para crear, buscar, editar metadatos y consultar usos. Configuración contable permite asignar fichas, revisar propuestas sin crearlas, ordenar reglas y editar conceptos. Libro diario filtra el ejercicio en servidor y enlaza origen, actor y rectificaciones. En un asiento, Rectificar permite previsualizar y confirmar. Revisión contable presenta incidencias informativas sin reclasificarlas automáticamente.

## Límites explícitos

- Existencias e inmovilizado permiten asignación y preparación de apuntes manuales; no se incorpora valoración automática ni amortización.
- Documentos históricos con varias cuentas y sin evidencia suficiente por línea requieren revisión expresa; no se infiere una distribución.
- Conciliaciones, cierres e impuestos protegidos conservan sus procedimientos específicos.
- Los saldos de la ficha de tercero que dependan de su cuenta actual no agregan automáticamente todas sus antiguas cuentas; el diario y mayor conservan el detalle histórico.
- No se han cambiado workers, scheduler, integraciones, retenciones ni jobs históricos.

## Verificación

Resultados definitivos y publicación se registrarán aquí al finalizar. Los ensayos se ejecutan con datos sintéticos; no se realizan pruebas de escritura en producción.
