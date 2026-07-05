# Portal Cliente V1 — subida manual

**Versión:** `BS-20260627-215413-CLIENTV1`  
**Entrega:** 27/06/2026 21:54:13 (America/Lima)

## Incluye

- Ruta funcional para **Mis Seguros**.
- Pólizas visibles para la empresa o consorcio autenticado.
- Gestiones públicas simplificadas.
- Filtro de pólizas vigentes, próximas a vencer y vencidas.
- Vista de detalle de solo consulta.

## Regla de visibilidad

El Cliente no ve la ficha interna completa. No tiene acceso a:

- Cotizaciones.
- Alertas internas.
- Contactos de gestión.
- Notas y observaciones.
- Línea de tiempo.
- Documentos internos.
- Pólizas desactivadas.

## Limitación de maqueta

Los datos de Expedientes y Pólizas se almacenan en `localStorage`. Para ver los registros creados por Gerente/Ejecutivo, debes iniciar la sesión Cliente en el mismo navegador y origen donde se guardó la caché. MySQL resolverá la sincronización entre computadoras y usuarios en una etapa posterior.

## Instalación

1. Haz una copia de seguridad de `public_html/maqueta/`.
2. Extrae este ZIP.
3. Sube el contenido directamente dentro de `public_html/maqueta/`.
4. Acepta reemplazar `config/bootstrap.php`.
5. No borres archivos ni carpetas ajenos al ZIP.
6. Recarga el navegador con `Ctrl + F5`.
