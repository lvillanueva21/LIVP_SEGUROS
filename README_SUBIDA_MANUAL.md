# Cotizaciones v1 — subida manual

**Versión:** `BS-20260627-203011-COT`

## Subida

1. Haz una copia de `public_html/maqueta/` antes de reemplazar archivos.
2. Extrae este ZIP sin cambiar la estructura de carpetas.
3. Sube el contenido directamente a `public_html/maqueta/`.
4. Acepta reemplazar los archivos existentes.
5. Presiona `Ctrl + F5` antes de probar.

## Nuevos

```text
cotizaciones.php
config/demo_quote_templates.php
assets/js/cotizaciones.js
assets/css/cotizaciones.css
assets/js/catalogos-logos.js
assets/css/catalogos-logos.css
api/upload_catalog_logo.php
api/view_catalog_asset.php
almacen/catalogos/aseguradoras/.gitkeep
```

## Reemplazados

```text
config/bootstrap.php
config/modules.php
catalogos.php
docs/00_version_actual.md
docs/04_pruebas_manual_y_humo.md
docs/05_historial_de_cambios.md
```

## Prueba mínima

1. Inicia sesión como Gerente o Ejecutivo.
2. Abre Cotizaciones.
3. Usa la plantilla Cotización comparativa.
4. Selecciona expediente y destinatario.
5. Agrega una aseguradora, prima y cuotas.
6. Guarda, abre Vista A4 e imprime/guarda PDF desde el navegador.

No se almacena el PDF en Hostinger; solo la información de la cotización queda en la caché del navegador.
