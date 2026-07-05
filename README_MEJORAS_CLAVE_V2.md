# Mejoras clave V2 — subida manual

**Versión:** `BS-20260627-213052-HUBV2`  
**Entrega:** 27/06/2026 21:30:52 (America/Lima)

## Qué aplica

- Ficha de Expediente V2 con pestañas: Resumen, Cotizaciones, Pólizas, Alertas, Documentos e Historial.
- Cotizaciones vinculadas obligatoriamente al expediente padre e independientes de pólizas.
- Documentos generales de expediente: PDF, PNG, JPG y WEBP hasta 7 MB.
- Duplicación de cotizaciones y plantillas.
- Filtros rápidos de pólizas y alertas.

## Subida

1. Haz una copia de seguridad de `public_html/maqueta/`.
2. Extrae este ZIP.
3. Sube el contenido directamente a `public_html/maqueta/`, respetando las carpetas.
4. Reemplaza los archivos existentes que coincidan.
5. No borres `config/`, `views/` ni archivos no incluidos.
6. Recarga con `Ctrl + F5`.

## Requisito previo

Esta mejora asume que ya están instalados los bloques de Pólizas, Alertas de Póliza y Mejoras Urgentes V1. No instala portal de Cliente: esta entrega sigue siendo solo para Gerente y Ejecutivo.

## Nota de maqueta

Los metadatos de documentos se guardan en la caché local del navegador. Los archivos físicos se guardan protegidos en `almacen/expedientes/`. Si se borra la caché del navegador, el archivo puede permanecer en hosting sin aparecer en la ficha; esto se resolverá cuando se migre a MySQL.
