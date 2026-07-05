# Corrección PDF de Cotizaciones

**Versión:** `BS-20260627-223240-PRINTFIX`  
**Fecha Perú:** 27/06/2026 22:32:40 (America/Lima)

## Causa del problema

La regla de impresión ocultaba `app-shell`, pero la hoja A4 de cotización estaba dentro de ese mismo contenedor. Por eso el navegador abría el diálogo de impresión, pero no tenía contenido visible y generaba una hoja en blanco.

## Qué corrige este paquete

- Deja visible el contenedor necesario para imprimir.
- Oculta solo menú lateral, barra superior, pantalla de inicio, editor y controles de impresión.
- Fuerza dos ciclos de renderizado antes de ejecutar `window.print()`.
- Cambia las versiones de recursos para que el navegador descargue el CSS nuevo.

## Instalación

1. Extrae el ZIP.
2. Sube el contenido directo sobre `public_html/maqueta/`.
3. Reemplaza los archivos existentes.
4. Pulsa `Ctrl + F5`.
5. Prueba con una cotización que tenga título, expediente, destinatario y al menos una alternativa.

No borres archivos ni carpetas fuera del ZIP.
