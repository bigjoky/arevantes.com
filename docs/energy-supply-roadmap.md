# Suministros y estudio energético — propuesta ENG-ENERGY

Fecha: 13/09/2026. Anexo del roadmap unificado, no un programa paralelo.
Estado: diseño propuesto; todavía no existe consulta CUPS ni conector activo.
La prioridad fiscal y el objetivo 01/01/2027 se mantienen.

## Experiencia en una visita comercial

Desde Cliente → Suministros, seleccionar un punto existente o registrar uno
con la factura del cliente. Un cliente puede tener varios suministros y ubicaciones.
La ficha muestra CUPS, dirección, distribuidora, tarifa, potencias contratadas por
periodo, consumo y configuración eléctrica, con fecha y fuente de cada dato.
Se accede desde la oportunidad sin volver a introducir la información.

El comercial puede preparar un estudio orientativo y solicitar revisión técnica.
Ingeniería compara escenarios, aprueba una versión y la vincula a la propuesta.
Modificar consumos o hipótesis genera otra versión, no altera ofertas anteriores.

## Fuentes y acceso: decisiones de producto

- Primera entrega sin dependencia externa: alta manual y documentos aportados por
  el cliente. CSV de distribuidora después de una previsualización validada; no
  guardar una extracción OCR como dato confirmado sin revisión humana.
- Datadis es el candidato principal para acceso delegado a suministros y curvas.
  Antes de implementar: confirmar documentación vigente, alta de Arevantes,
  cobertura, campos, límites, mecanismo de autorización/revocación y condiciones.
  No solicitar ni almacenar la contraseña del cliente. No prometer disponibilidad
  inmediata, acceso a cualquier CUPS ni consulta por NIF/dirección.
- El portal SIPS de CNMC no es una búsqueda pública: requiere alta y aprobación.
  No asumir que ser instalador habilita a descargarlo. Evaluar un proveedor
  autorizado solo si el alcance y la base de acceso están acreditados.
- Monofásica/trifásica: dato explícito contrastado con CIE, documentación técnica
  de distribuidora o visita profesional. No inferirlo del CUPS ni de la potencia
  contratada. Admitir «desconocida», fuente, fecha de verificación y discrepancias.
- Registrar autorización por suministro, finalidad, titular/representante,
  evidencia, vigencia y revocación. La revocación bloquea consultas futuras;
  definir conservación y eliminación según finalidad antes de activar el conector.
  Secretos solo en servidor, documentos privados, CUPS enmascarado en logs y
  listados generales, auditoría de consultas y permisos por cliente asignado.

## Modelo funcional e integración

Una ficha canónica de punto de suministro enlaza cliente, ubicación, oportunidad
y proyecto; el CUPS identifica el punto, no la persona. La relación con el titular
tiene vigencia para conservar cambios sin atribuir consumos históricos a otro.
Importaciones conservan origen, intervalo, zona horaria, unidad, huella y calidad.
Los estudios referencian una versión de los datos, nunca una curva mutable.

Reutilizar EngineeringDossier, sus versiones, eventos y aprobación. La instalación
ya tiene electrical_phase y electrical_voltage_v: mapearlos explícitamente y
avisar de discrepancias; no crear un segundo dato técnico sin relación.
Usar el catálogo existente para módulos, inversores y baterías y la composición
de propuestas para la solución aprobada. No reservar stock al simular.

## Consumo y dimensionado

- Mostrar kWh de los últimos doce meses completos disponibles, fechas exactas,
  cobertura y retraso. Si falta información, mostrar consumo parcial; cualquier
  anualización debe estar rotulada como estimación y conservar su método.
- Importación idempotente, detección de duplicados, huecos, periodos solapados,
  cambios de hora, unidades y lecturas estimadas. Separar consumo de red,
  exportación y producción: en autoconsumo existente la importación no equivale
  a la demanda total de la instalación.
- Fotovoltaica: ubicación, superficie útil, orientaciones, inclinación, sombras,
  límites eléctricos, presupuesto y cargas futuras (vehículo/climatización).
  PVGIS como fuente candidata de producción horaria estimada, no de consumos.
- Comparar curvas temporales de demanda y producción para autoconsumo,
  excedentes y energía de red. El total anual por sí solo no justifica tamaño
  de inversor/batería ni garantiza ahorro. Si solo hay totales, ofrecer rango
  preliminar y bloquear la aprobación técnica por falta de evidencia.
- Escenarios sin batería/con batería: hipótesis de eficiencia, potencia,
  capacidad útil, límites de carga, costes y tarifas versionados; separación
  clara de producción estimada, autoconsumo, ahorro y retorno estimados.
- El resultado comercial no sustituye diseño eléctrico, protecciones,
  verificación estructural ni validación de un técnico competente.

## Entregas y criterios de salida

1. ENG-ENERGY-01: ficha manual, varios suministros, evidencia documental, permisos
   y enlace a oportunidad/proyecto. Criterios: CUPS normalizado/validado según
   formato oficial contrastado, duplicados controlados, titularidad histórica,
   estado desconocido de fases y denegación cruzada entre clientes.
2. ENG-ENERGY-02: CSV con previsualización, doce meses y control de calidad.
   Pruebas: huecos, duplicados, unidades, cambio horario, autoconsumo existente
   y no sustitución silenciosa de datos confirmados.
3. ENG-ENERGY-03: simulación y escenarios en expediente técnico versionado,
   aprobación y enlace a oferta; fixtures de cálculo contrastados y revisión
   de un técnico antes de habilitar recomendaciones comerciales.
4. ENG-ENERGY-04: Datadis tras resolver acceso y autorizaciones. Cola de consulta,
   reintentos acotados, idempotencia, revocación, errores comprensibles y límites.
5. ENG-ENERGY-05: visita móvil usando el mismo API y permisos. La app conserva
   su desarrollo independiente; no replicar reglas o almacenar curvas completas
   fuera de línea por defecto.

## Fuentes consultadas

- [Datadis](https://datadis.es/): plataforma de datos de consumo y API; contrato
  técnico y campos delegados pendientes de validación.
- [CNMC: acceso SIPS](https://sede.cnmc.gob.es/tramites/energia-electricidad-energia-gas/bases-de-datos-de-consumidores-y-puntos-de-suministro): procedimiento de alta, aprobación y confidencialidad.
- [JRC: PVGIS](https://joint-research-centre.ec.europa.eu/pvgis-online-tool_en):
  estimaciones de radiación y rendimiento fotovoltaico, incluidas series horarias.
- [Endesa: identificación monofásica/trifásica](https://www.endesa.com/es/blog/blog-de-endesa/luz/instalacion-monofasica-trifasica): documentación eléctrica/CIE como evidencia.
