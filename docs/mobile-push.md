# Notificaciones móviles APNs

La app usa APNs directamente, sin Firebase ni proveedores intermedios.

## Apple Developer

1. Registra el App ID `com.arevantes.mobile` y activa Push Notifications.
2. Crea una clave APNs `.p8` y conserva su Key ID y Team ID.
3. Selecciona el equipo de firma en el target Arevantes de Xcode.

## Laravel

Configura estas variables y reinicia workers/caché de configuración:

```dotenv
APNS_KEY_ID=
APNS_TEAM_ID=
APNS_BUNDLE_ID=com.arevantes.mobile
APNS_PRIVATE_KEY=/ruta/segura/AuthKey_XXXXXXXXXX.p8
```

`APNS_PRIVATE_KEY` puede contener el PEM, pero en producción se recomienda una ruta fuera del repositorio con permisos restringidos. El worker de colas debe estar activo. Los JWT de proveedor se conservan durante 50 minutos y APNs invalida automáticamente dispositivos caducados en la base de datos.

Debug usa el entorno sandbox de APNs y Release usa producción. Las notificaciones incluyen `work_order_id`; al tocarlas, la aplicación abre esa orden.
