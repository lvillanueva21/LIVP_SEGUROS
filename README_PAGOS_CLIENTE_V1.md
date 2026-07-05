# Pagos Cliente V1

**Versión:** `BS-20260627-222604-PAYCLIENTV1`  
**Entrega:** 27/06/2026 22:26:04 (America/Lima)

## Instalación

1. Haz copia de seguridad de `public_html/maqueta/`.
2. Extrae el ZIP y sube su contenido directo a `public_html/maqueta/`.
3. Acepta reemplazos y no borres archivos ajenos.
4. Recarga con `Ctrl + F5`.

## Uso

- Gerente/Ejecutivo: Expediente → Póliza → **Pagos** → Programar cuota.
- Cliente: **Mis Pagos** → Subir comprobante.
- Gerente/Ejecutivo: Póliza → **Pagos** → Ver, validar u observar comprobante.

## Límite de maqueta

El cronograma y su relación con pólizas se guardan en caché local. Cliente y equipo interno deben usar el mismo navegador y dominio para compartir la información de prueba. El archivo físico sí queda guardado bajo `almacen/comprobantes/`; MySQL reemplazará esta limitación en la etapa multiusuario.
