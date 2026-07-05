# BROKER SEGUROS — Maqueta funcional

Versión preparada: `BS-20260627-173744-PET` — 27/06/2026 17:37:44 (America/Lima).

## Bloque incluido

Clientes v1: Empresas, Consorcios y Contactos de gestión, integrado con Expedientes.

- Empresas: razón social o RUC; RUC válido de 11 dígitos y único cuando se registre.
- Consorcios: RUC propio o empresa operadora tributaria; mínimo dos empresas activas.
- Contactos: persona natural con nombre y celular obligatorios, múltiples vínculos y contacto principal por entidad.
- Desactivación con motivo obligatorio; no hay eliminación física.
- Expedientes usa empresas y consorcios creados desde Clientes, y puede sugerir entidad cuando el contacto tiene un único vínculo activo.

## Persistencia demo

Los datos se guardan únicamente en `localStorage` del navegador actual. No hay MySQL todavía.

## Pruebas

Revisar `docs/04_pruebas_manual_y_humo.md` y `docs/funcionalidades/clientes_empresas_consorcios_v1.md`.
