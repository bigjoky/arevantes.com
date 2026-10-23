# Reconciliación main/develop — 05/10/2026

Origen publicado: `f5890858f81fbabc0429aac95b1f8eecd4e2f843` (PR131).
Develop comparado: `8a71f8f`. No publicación ni modificación de datos reales.

Los conflictos procedían de historiales distintos para entregas equivalentes.
Se revisaron y resolvieron por bloque, sin sustituir una rama completa.

| Archivo | Comprobación y resolución |
| --- | --- |
| ExpenseController | Todas las líneas de main ya presentes y en orden; conservar además país y duplicados por territorio de develop. |
| expenses/edit.tsx | Todas las líneas publicadas presentes; conservar selector de país añadido. |
| expenses/index.tsx | Única evolución funcional: pasar países al mismo formulario del emisor; edición, cálculo e historial publicados conservados. |
| lang/en.json, lang/es.json, i18n/catalog.json | Ninguna clave publicada ausente y ningún valor compartido diferente; conservar entradas fiscales nuevas. |
| roadmap y plan 303/390/347 | Conservar historial y actualizar estados a los avances y despliegues ya verificados; no restaurar estados antiguos pendientes. |

Resultado frente al develop anterior: ninguna modificación de código funcional;
solo diferencias de espacios vacíos en documentación. Migraciones publicadas,
correcciones de gastos, anulación de pagos, líneas libres, logos, importación y
conciliación bancaria no se sustituyen por versiones antiguas.

Verificación de la integración: 715 pruebas, 710 superadas, 5 omitidas,
10.688 aserciones. Traducciones compartidas comprobadas por clave y valor.
El merge incorpora main como ancestro y elimina la divergencia de historial;
las mejoras fiscales pendientes continúan en develop, sin habilitar presentación.
