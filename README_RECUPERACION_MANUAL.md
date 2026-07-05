# Recuperación estable de Expedientes y Pólizas

**Versión:** `BS-20260627-193319-PET`  
**Fecha Perú:** 27/06/2026 19:33:19 (America/Lima)

## Qué corrige

Este paquete recupera el módulo Expedientes/Pólizas sin depender de la carga dinámica que provocó botones sin estilo y acciones silenciosas.

## Subida manual segura

1. En Hostinger, crea una copia de seguridad de `public_html/maqueta/`.
2. Descomprime este ZIP manteniendo su estructura de rutas.
3. Sube el contenido del ZIP a `public_html/maqueta/`.
4. Acepta reemplazar archivos existentes.
5. No borres carpetas externas al ZIP.
6. Verifica estas rutas:

```text
public_html/maqueta/expedientes.php
public_html/maqueta/assets/js/expedientes.js
public_html/maqueta/assets/js/polizas.js
public_html/maqueta/assets/css/polizas.css
public_html/maqueta/assets/css/modal-ui.css
public_html/maqueta/assets/css/notifications.css
public_html/maqueta/api/upload_policy_pdf.php
public_html/maqueta/almacen/.htaccess
```

7. Abre `docs/00_version_actual.md`; debe decir `BS-20260627-193319-PET`.
8. Presiona `Ctrl + F5` antes de probar.

## Importante

Los metadatos demo siguen en el almacenamiento del navegador. No uses “Borrar datos del sitio” mientras pruebas PDFs, porque el archivo físico puede quedar sin referencia temporal.
