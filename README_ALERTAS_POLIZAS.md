# Alertas de pólizas v1 — instalación manual

**Versión:** `BS-20260627-211508-ALR`  
**Fecha Perú:** 27/06/2026 21:15:08 (America/Lima)

## Qué hace

- Configura varias alertas por póliza.
- Calcula pendientes al abrir la maqueta.
- Permite preparar WhatsApp y correo con datos reales.
- No envía automáticamente: la integración futura será con Twilio y Zoho Mail.

## Subida

1. Respaldar `public_html/maqueta/`.
2. Extraer este ZIP.
3. Subir el contenido directamente sobre `public_html/maqueta/`, conservando estructura.
4. Aceptar reemplazos solo para los archivos incluidos.
5. Presionar `Ctrl + F5`.

## Archivos reemplazados

```text
expedientes.php
dashboard.php
assets/js/polizas.js
assets/js/cache-migrations.js
```

## Archivos nuevos

```text
assets/js/policy-alerts.js
assets/js/policy-alerts-dashboard.js
assets/css/policy-alerts.css
```

No se reemplaza ninguna configuración de correo o WhatsApp porque todavía no existe envío real.
