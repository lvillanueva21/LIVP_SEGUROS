# Funcionalidad histórica: Expedientes demo v1

## Estado

Esta versión fue reemplazada por la corrección `BS-20260627-131127-PET`.

## Motivo de reemplazo

La versión inicial trataba incorrectamente el expediente como una gestión con campos obligatorios de seguro, aseguradora y moneda.

El modelo vigente está documentado en:

```text
docs/funcionalidades/modelo_expediente_y_cotizacion.md
```

## Regla actual

Un expediente es un contenedor flexible y no requiere cotización, seguro, póliza, pago ni documento.
