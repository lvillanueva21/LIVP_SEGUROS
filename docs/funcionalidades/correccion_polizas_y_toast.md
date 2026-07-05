# Corrección de Pólizas y notificaciones contextuales

- **Versión:** `BS-20260627-191718-PET`
- **Fecha Perú:** 27/06/2026 19:17:18 (America/Lima)
- **Base:** `BS-20260627-183503-PET`

## Problemas corregidos

- El toast global quedaba debajo de un `<dialog>` nativo.
- Registrar póliza intentaba abrir una interfaz adicional sobre la ficha del Expediente.
- Algunas rutas de error terminaban sin mostrar mensaje.

## Comportamiento nuevo

- Con un modal abierto, los avisos se insertan dentro de su estructura.
- La ficha de Expediente sigue siendo el único modal abierto.
- Registrar, editar y desactivar pólizas usa una pantalla interna temporal.
- Cancelar o guardar devuelve a la ficha actualizada.
