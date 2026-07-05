# Estabilización Pre-Demo V1

**Versión:** `BS-20260627-231256-PREDEMO`  
**Entrega:** 27/06/2026 23:12:56 (America/Lima)

## Propósito

Este paquete deja una demo coherente para presentación. Incluye los módulos Cliente de Seguros, Pagos y Siniestros, más datos precargados y un botón para restaurar el escenario sin crear registros manualmente.

## Instalación

1. Haz copia de seguridad de `public_html/maqueta/`.
2. Extrae este ZIP.
3. Sube el contenido **directamente** a `public_html/maqueta/`.
4. Acepta reemplazar archivos coincidentes.
5. No borres archivos ni carpetas que no estén dentro del ZIP.
6. Recarga con `Ctrl + F5`.

## Preparación antes de mostrar la demo

1. Inicia sesión como Gerente.
2. En Inicio pulsa **Restablecer escenario demo**.
3. Ingresa como Cliente Empresa:
   - RUC: `20123456789`
   - Clave: `Empresa2026!`
4. Ingresa como Cliente Consorcio:
   - RUC: `20698765432`
   - Clave: `Consorcio2026!`

## Escenario que se carga

- **Constructora Norte S.A.C.**
  - Expediente `EXP-2026-0001`.
  - Póliza `POL-2026-0001`.
  - Una cuota pagada y una pendiente.
  - Un siniestro de muestra.
  - PDF demostrativo autorizado.

- **Consorcio Vías del Norte**
  - Expediente `EXP-2026-0003`.
  - Póliza `POL-2026-0002` próxima a vencer.
  - Una cuota pagada.
  - PDF demostrativo autorizado.

## Límite que debes explicar

Es una **maqueta funcional de demostración**, todavía basada en caché local del navegador. No es un sistema multiusuario productivo ni una implementación con MySQL.
