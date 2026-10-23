# Publicación PWA — 24/10/2026

El usuario autorizó la publicación directa a producción, sustituyendo los límites anteriores de no commit/push/merge/despliegue. No se crea staging ni infraestructura QA.

Candidato preparado en checkout aislado a partir de main f79866655e90b34c491c261158d8c747808ac9b6. La copia local servida en localhost:8000 y su rama quedan intactas. Se trasladan los archivos PWA nuevos, las adaptaciones compartidas y E-01/E-02; se conserva el hotfix anterior ya presente en main. No hay cambios de migraciones, dependencias, secretos, runtime o recursos Cloud.

Verificación previa: 29 pruebas frontend; tipos, lint y build correctos. Suite backend completa: 925 casos, 919 aprobados, 6 omitidos y 14.213 aserciones. Cloud confirmó 235 migraciones aplicadas y cero pendientes; el comando de despliegue habitual no incorpora nuevas migraciones del corte.

Limitaciones conservadas: validación física iPhone/Android y modo instalado pendiente; sesión/logout+Atrás, actualización entre ventanas y teclado/safe areas deben comprobarse en uso real. Bandeja completa de notificaciones y Web Push siguen fuera de este corte. El control i18n mantiene 20 faltantes previos en field-service y quotes/form; las cadenas nuevas PWA tienen catálogo. El build mantiene el aviso previo de chunks >500 kB.

La activación del service worker actualizado requiere acción explícita; no recarga automáticamente ni cachea información privada. E-01 confirma el descarte y mantiene el formulario al cancelar; bloquea cierre/doble envío mientras está en curso. E-02 devuelve el foco al botón Menú tras el retroceso.

Recuperación: volver al despliegue anterior f79866655e90b34c491c261158d8c747808ac9b6 si aparecen regresiones; no hay reversión de esquema asociada a este corte. Comprobar worker, assets y sesión también tras una recuperación. No se configura una reversión automática que pueda descartar trabajo en curso.

El resultado y SHA productivo efectivo se confirman después de CI y despliegue; este documento por sí solo no demuestra publicación.
