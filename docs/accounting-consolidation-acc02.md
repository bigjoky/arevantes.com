# ACC-02 — Saldos consolidados y reclasificación asistida

Estado: candidato integrado verificado; publicación manual pendiente. No publicado todavía.

## Alcance y evidencia

Se reutilizan terceros, cuentas, asientos, apuntes y rectificaciones de ACC-01. `PartyLedger` es una proyección de lectura, no otro libro contable. La atribución se deduce del documento contabilizado y se propaga a los ajustes y reversiones enlazados. Cada apunte se cuenta una sola vez. El uso de una cuenta compartida no atribuye a un tercero los movimientos de otros ni los movimientos manuales sin evidencia suficiente.

Las identidades documentales con cambios auditados contradictorios permanecen pendientes de identificación cuando falta una atribución inequívoca por apunte. Se muestran separadamente y no se suman al saldo confirmado. No se infiere por nombre, NIF, código de cuenta ni fecha de creación ambigua.

La ficha presenta situación contable, periodo, cuenta principal, cuentas históricas con movimientos, saldos inicial/final, Debe/Haber y anticipos. El saldo consolidado descuenta los anticipos: clientes positivo a cobrar; proveedores/acreedores positivo a pagar. El importe pendiente de las facturas conserva su cálculo separado. El desglose conserva la naturaleza efectiva de cada cuenta (incluidas 438 y 407). El mayor del tercero muestra 25 apuntes por página, con sus fechas reales y enlaces a originales y ajustes.

## Reclasificación supervisada

Contabilidad incorpora filtros, selección de apuntes, cuenta propuesta, vista previa por elemento y totales agregados por cuenta. Se reutiliza `JournalCorrection`: no se modifican asientos ni documentos originales. Cada ajuste conserva usuario, fecha, motivo y enlace. El lote dispone de UUID, huella de solicitud, revisión de la propuesta y resultados inmutables. Sus resultados son accesibles desde el asiento y el historial paginado.

La vista previa no realiza escrituras económicas. La ejecución requiere confirmación, y confirmación adicional cuando hay elementos bloqueados. Se rechazan mezclas de terceros, ejercicios o criterios; las operaciones empresariales sin tercero verificable no se mezclan silenciosamente. Cuentas fiscales, personal, tesorería (también como destino), cierres, ajustes documentales y operaciones conciliadas conservan sus procedimientos específicos. No se modifica ninguna relación bancaria ni se crean pagos.

Los bloqueos se recalculan al confirmar, incluido el estado de elementos que permanecen excluidos. Los ajustes elegibles se ejecutan en una transacción; cualquier cambio o fallo obliga a revisar, sin una ejecución parcial accidental. Se bloquean fuentes, ejercicios y cuentas en orden estable; se serializan lotes y se mantienen las protecciones de anulación de ACC-01.

## Fechas, cierres y reversión

Una reclasificación no precede al original ni a otra reclasificación económica posterior ya existente. Se respetan los periodos y ejercicios cerrados; ACC-02 no los reabre ni sustituye el procedimiento específico para resolverlos. Se comprueban declaraciones protegidas tanto en el origen como en el periodo del ajuste.

La revisión detectó dos interacciones concretas que se han corregido: cobros/pagos retrodatados deben utilizar la cuenta vigente en su fecha; y una anulación no puede fechar la reversión de un ajuste antes del propio ajuste. Las proyecciones contabilizadas admiten consulta a fecha, y las reversiones de ajustes se generan cronológicamente en la fecha máxima entre anulación y ajuste, comprobando cierres y protección fiscal. La fecha contractual de anulación del movimiento original se conserva.

## Esquema, permisos y límites

Única migración: `2026_10_29_185005_create_accounting_reclassification_batches`. Añade una tabla de auditoría con UUID, usuario, motivo, selección, resultados y registro UTC. No actualiza tablas históricas. Índices para historial y consulta de lotes por asiento; RLS y sin privilegios Data API/PUBLIC. No hay workers ni procesamiento automático de lotes.

Consultar saldos exige acceso financiero. Rectificar exige el permiso específico de ACC-01. El aislamiento mantiene el bloqueo existente si hay varias empresas sin propiedad contable definida.

La selección interactiva corresponde a la página actual; el servidor admite hasta 50 apuntes. Los saldos pendientes de identificación son explícitamente incompletos: no se corrigen automáticamente. No se realiza reclasificación histórica de oficio ni se inicia Nexo/ECO-03.

## Verificación y publicación

- Suite general: 1.156 pruebas, 1.104 aprobadas, 52 omitidas, 15.576 aserciones (67,5 s).
- PostgreSQL 17.6: 101 pruebas económicas / 897 aserciones aprobadas, incluida la migración final con índices y JSONB.
- Concurrencia PostgreSQL: ocho escenarios en dos pruebas / 64 aserciones; creación/asignación, claves iguales/distintas y rectificación/lote frente a anulación. Las tres carreras específicas de lotes están incluidas.
- Frontend: 88 pruebas aprobadas. TypeScript, ESLint de los archivos afectados, Pint, catálogo ES/EN y compilación aprobados. El aviso existente de chunks grandes se conserva.
- Navegador con assets compilados y props/respuestas de controladores sobre PostgreSQL sintético, todo revertido: selección de dos apuntes, vista previa y confirmación por teclado, resultado, mayor y fichas de cliente/proveedor. Escritorio 1.440 px y móvil 390 px; sin desbordamiento horizontal ni errores JS. La confirmación visual se intercepta; las escrituras reales de prueba se verifican únicamente en bases desechables.
- Vista previa confirmada de solo lectura. EXPLAIN ANALYZE de la consulta de atribución en el pequeño fixture sintético: 10 filas, 2,076 ms de ejecución y 8,289 ms de planificación; no se presenta como ensayo de carga productiva.
- Preflight productivo de lectura: únicamente pendiente la migración propia, sin asientos descuadrados; automatismos desactivados y trabajos pendientes preservados.

ACC-02 no se considera completado hasta publicar y verificar ambas capacidades. El despliegue y sus comprobaciones se registrarán al finalizar.
