# Plantilla de modulo local

Esta carpeta es una plantilla tecnica interna. No es un modulo real, no debe registrarse en LIVP_LSISTEMAS, no aparece en el menu y no usa base de datos.

## Como crear un modulo real

1. Crear la pagina logica en LIVP_LSISTEMAS.
2. Usar como `codigo_pagina` el mismo nombre de carpeta local.
3. Asignar permisos al rol que corresponda.
4. Duplicar `modules/_plantilla/` como `modules/{codigo}/`.
5. En `modules/{codigo}/index.php`, reemplazar `_plantilla` por el codigo real.
6. Acceder mediante `modulo.php?m={codigo}`.

## Seguridad obligatoria

- Todo modulo real debe cargar `includes/module_guard.php`.
- Todo modulo real debe llamar `cb_require_module_context('{codigo}')`.
- El archivo `modules/{codigo}/index.php` no debe ejecutarse directamente por URL.
- La sesion y `puede_ver` se validan antes en `modulo.php`.

## Endpoints locales

Crear `api/{codigo}/` solo cuando el modulo necesite lectura o cambios dinamicos.

Endpoints comunes:

```text
api/{codigo}/list.php
api/{codigo}/create.php
api/{codigo}/update.php
api/{codigo}/toggle_state.php
```

La estructura es flexible. Se pueden crear endpoints adicionales si el requerimiento lo justifica.

## Base de datos local

Crear tablas locales solo si el modulo necesita persistir datos de negocio.

Cuando exista cambio de BD:

- entregar SQL por chat para phpMyAdmin,
- no crear archivos `.sql`,
- no ejecutar SQL automaticamente,
- actualizar `docs/tablas_livp_seguros.md`.

## Flexibilidad

La plantilla no limita futuros tabs, formularios, cards, dashboards, CSS/JS especificos, endpoints extra, subcarpetas o flujos especiales.

Esa flexibilidad no permite omitir sesion, permisos, validacion backend, CSRF cuando corresponda, PDO ni documentacion.
