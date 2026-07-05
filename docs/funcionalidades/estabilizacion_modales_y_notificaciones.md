# Estabilización de modales y notificaciones

## Versión

- Código: `BS-20260627-180106-PET`
- Fecha y hora Perú: 27/06/2026 18:01:06 (America/Lima)
- Base revisada: `BS-20260627-173744-PET`

## Problema resuelto

Los `<dialog>` nativos se dibujan en una capa superior del navegador. Un toast agregado al `body`, aun con un `z-index` alto, puede quedar debajo del modal.

## Comportamiento nuevo

- Modal abierto: `BrokerNotify` inserta el mensaje dentro del contenido del modal.
- Sin modal: `BrokerNotify` usa el host global en la esquina superior derecha.
- Un modal que se cierra antes de notificar deja libre el host global.
- El cierre `×` de una confirmación resuelve la acción como cancelada.

## Capa visual

`assets/css/modal-ui.css` aplica estilos comunes a:

- input;
- select;
- textarea;
- checkbox;
- mensajes de ayuda;
- filas repetibles de contactos y participantes;
- botones de acción;
- bloques dinámicos insertados por JavaScript.

## No incluido

- Cambios de reglas de negocio.
- Cotizaciones.
- Pólizas.
- Documentos.
- Migración a MySQL.
