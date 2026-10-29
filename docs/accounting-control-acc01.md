# ACC-01 — Control contable

Estado: publicado y verificado en producción. Se ha aplicado únicamente la migración aditiva propia; no se han reescrito datos históricos.

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

- Suite general integrada: 1.141 pruebas; 1.090 aprobadas, 51 omitidas, 15.504 aserciones (67,9 s).
- PostgreSQL 17.6: 121 pruebas económicas / 1.450 aserciones aprobadas; primer bloque adicional 63 / 585.
- Cinco carreras con procesos reales (1 prueba / 42 aserciones): creación de cuenta, asignación concurrente, misma clave, claves distintas y anulación frente a corrección.
- Frontend: 88 pruebas aprobadas; contrato de exclusiones técnicas ES/EN actualizado y comprobado, sin traducir identificadores internos.
- TypeScript, ESLint de los archivos afectados, Pint, catálogo y compilación aprobados. El build conserva el aviso existente sobre chunks grandes.
- Navegador con assets compilados, respuestas de controladores obtenidas con datos sintéticos y rollback: configuración y detalle de asiento en escritorio y móvil de 390 px, sin desbordamiento horizontal ni errores JS. Toda red interceptada; ninguna escritura productiva ni cambio del servidor local existente.
- Las primeras ejecuciones simultáneas de build/pruebas o de suites que comparten Storage::fake causaron fallos de fixture. Las verificaciones definitivas se ejecutaron sin esa interferencia; no se alteró la lógica documental para ocultarlos.
- Preflight productivo de lectura: 252 migraciones, únicamente pendiente la propia de ACC-01; ninguna diferencia debe/haber; nueve jobs sin intentos ni reservas; integraciones OPS y automatismos desactivados. Los ensayos se ejecutan con datos sintéticos; no se realizan pruebas de escritura en producción.

## Publicación efectiva

Código `26abc366b98f2bb5ed4db27a69518c3a41376958`; Laravel Cloud `depl-a31942d0-2fdf-4b92-bdd1-4ae32d544e9a`, succeeded el 29/10/2026 a las 15:50:18 UTC. Integra selectivamente ACC-01 sobre el almacén móvil ya publicado, conservando también la comprobación de contratos i18n existente en main.

Migraciones 252→253: aplicada exclusivamente `2026_10_29_105544_add_controlled_accounting_configuration_and_corrections`, bajo mantenimiento, locks acotados y comprobación de igualdad del registro. Doce tablas conservan todas sus columnas originales por hash antes/después; triggers históricos sin cambios. Fuentes remotas iguales al candidato por SHA-256. Tres nuevas tablas con RLS y sin privilegios Data API/PUBLIC, tampoco en sus secuencias. No se crearon movimientos económicos ni documentos de prueba en producción.

Mantenimiento retirado. Nueve GET autenticados de configuración, revisión, diario, plan, asiento, factura, banco, gastos y compras: 200. `/up` y `/login`: 200; configuración anónima: 302. El cliente urllib recibió 403 en la capa pública; se verificó con curl y mediante el kernel autenticado sin cambiar controles de acceso. No hay archivos de log de aplicación disponibles en el contenedor: el monitor de despliegue terminó sin errores y las rutas verificadas no devolvieron 500; no se afirma una revisión de logs inexistentes.

Los nueve jobs permanecen sin intentos ni reservas y con payload/cola intactos, incluidos los seis históricos. Workers, scheduler, integraciones OPS, push-to-deploy, hook y migraciones automáticas desactivados. Backups, custodia y retención conservados. Contenedor PostgreSQL sintético detenido y navegador de QA cerrado. La instancia localhost existente no se ha sustituido; la comprobación visual utilizó assets compilados y datos sintéticos con red interceptada.

Evidencia compacta: [publicación ACC-01](accounting-control-publication-2026-10-29.json).
