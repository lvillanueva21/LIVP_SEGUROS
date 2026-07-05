# Modelo de expediente y cotización

## Versión de decisión vigente

- **Código de versión:** `BS-20260627-164101-PET`
- **Fecha y hora Perú:** 27/06/2026 16:41:01 (America/Lima)

## Regla principal

El expediente es el contenedor de un solo proceso asegurador.

No es una cotización ni una póliza. Puede empezar sin cliente, tipo de seguro, aseguradora, cotización o póliza, pero necesita contacto de gestión, nombre y descripción.

## Separación de conceptos

| Elemento | Pertenece a |
|---|---|
| Contacto de gestión, nombre, descripción, cliente opcional, situación y fechas | Expediente |
| Datos de una posible contratación | Cotización futura |
| Seguro emitido, código flexible, vigencias y documento PDF | Póliza futura |
| Tipo de seguro y aseguradora | Catálogos y póliza/cotización cuando corresponda |
| Pagos, vouchers, garantías y datos específicos | Módulos o ítems futuros |

## Relación futura

```text
Expediente 1 ── 0..N Cotizaciones opcionales
Expediente 1 ── 0..N Pólizas de continuidad o renovación
```

Una póliza puede vincularse a una cotización anterior, pero no será obligatorio.

No se deben codificar datos específicos de seguros directamente en el expediente raíz.
