# Mejoras urgentes — Dashboard y trazabilidad

**Versión:** `BS-20260627-212338-NEXO`  
**Fecha y hora Perú:** 27/06/2026 21:23:38 (America/Lima)

## Qué mejora este paquete

1. **Dashboard real en caché**
   - Muestra expedientes, cotizaciones, pólizas activas y alertas pendientes usando los datos guardados en el navegador.
   - Muestra actividad reciente y pólizas con vencimiento dentro de 45 días.

2. **Línea de tiempo dentro del expediente**
   - Registra creación y actualización del expediente.
   - Registra alta, edición, desactivación/reactivación de pólizas.
   - Registra creación, edición, pausa, gestión y eliminación de alertas.
   - Registra creación y edición de cotizaciones del expediente, sin vincularlas funcionalmente con pólizas.

3. **Navegación rápida**
   - Desde Inicio se puede abrir la ficha de un expediente por URL.

## Regla preservada

Una cotización exige expediente padre. Es un antecedente comercial independiente: no crea, modifica ni se enlaza a pólizas o seguros emitidos.

## Subida

1. Haz una copia de seguridad de `public_html/maqueta/`.
2. Sube el contenido respetando la estructura de carpetas.
3. Acepta reemplazar los archivos incluidos.
4. No borres archivos que no estén incluidos en este ZIP.
5. Usa `Ctrl + F5`.

## Prueba rápida

- Abre una póliza y edítala o configura una alerta.
- Vuelve a la ficha: la línea de tiempo debe mostrar la acción.
- Entra a Inicio: deben cambiar los indicadores de caché y mostrarse actividad reciente.
- Crea o edita una cotización vinculada a un expediente; luego abre ese expediente para ver el antecedente en la línea de tiempo.
