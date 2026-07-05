# Siniestros Cliente V1 — instalación manual

**Versión:** `BS-20260627-223842-CLAIMCLIENTV1`  
**Entrega:** 27/06/2026 22:38:42 (America/Lima)

## Qué incorpora

- Ruta real para **Mis Siniestros**.
- Cliente puede registrar un reporte inicial vinculado a una de sus pólizas.
- Se genera código `SIN-YYYY-NNNN`.
- El reporte aparece en la vista Cliente y se registra en la Línea de tiempo interna del expediente.

## Qué no incorpora todavía

- Adjuntar evidencia.
- Envío a aseguradora.
- Validación de cobertura.
- Estados operativos internos detallados.
- Gestión multiusuario con base de datos.

## Instalación

1. Haz copia de seguridad de `public_html/maqueta/`.
2. Extrae este ZIP.
3. Sube su contenido directo a `public_html/maqueta/`.
4. Acepta reemplazos y no borres archivos ajenos.
5. Recarga con `Ctrl + F5`.

## Prueba

Cliente → **Mis Siniestros** → reportar con una póliza.  
Gerente/Ejecutivo → abrir el expediente relacionado → **Historial**.
