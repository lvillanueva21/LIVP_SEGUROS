# Corrección: Cotización independiente vinculada a Expediente

**Versión:** `BS-20260627-205614-COTREL`  
**Entrega:** 27/06/2026 20:56:14 (America/Lima)

## Qué corrige

- Una cotización requiere un expediente padre válido.
- La cotización se guarda separada de Expedientes y de Pólizas.
- No existe conversión, precarga ni vínculo entre alternativa elegida y póliza real.
- El expediente puede tener pólizas reales totalmente distintas a cualquier cotización histórica.

## Archivos a reemplazar

```text
cotizaciones.php
assets/js/cotizaciones.js
docs/00_version_actual.md
docs/04_pruebas_manual_y_humo.md
docs/05_historial_de_cambios.md
```

Sube estos archivos respetando la estructura dentro de `public_html/maqueta/`, reemplaza los existentes y luego usa `Ctrl + F5`.

No debes borrar `config/`, `views/`, `almacen/` ni módulos no incluidos en este ZIP.
