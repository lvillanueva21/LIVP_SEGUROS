# Corrección rápida: Cotización desde Expediente

**Versión:** `BS-20260627-214418-COTFIX`  
**Fecha Perú:** 27/06/2026 21:44:18 (America/Lima)

## Qué corrige

La mejora anterior buscaba el selector antiguo `#q-expedient`.  
La pantalla real usa `#e-exp`.

Esta corrección preselecciona correctamente el expediente padre cuando se abre una nueva cotización desde la ficha del expediente.

## Instalación

1. Extrae el ZIP.
2. Sube su contenido directamente a `public_html/maqueta/`.
3. Reemplaza `assets/js/cotizaciones-v2-enhancements.js`.
4. Recarga con `Ctrl + F5`.

No borres ningún archivo ni carpeta adicional.
