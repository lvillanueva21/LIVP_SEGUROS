# Funcionalidad: Notificaciones y mensajes amigables

## Versión de entrega

- **Código:** `BS-20260627-125439-PET`
- **Fecha y hora Perú:** 27/06/2026 12:54:39 (America/Lima)

## Propósito

Evitar que una persona realice una acción sin saber si se guardó, si falló o si requiere corregir información.

## Interfaz global

El sistema expone:

```javascript
window.BrokerNotify.success('Mensaje');
window.BrokerNotify.error('Mensaje');
window.BrokerNotify.warning('Mensaje');
window.BrokerNotify.info('Mensaje');
```

Los mensajes muestran título, descripción, ícono, cierre manual y duración automática según su importancia.

## Mensajes implementados

### Éxito

- Expediente creado.
- Estado de expediente actualizado.
- Catálogo agregado o editado.
- Catálogo activado o desactivado.
- Catálogos restaurados.

### Advertencia y error

- Campos requeridos sin completar.
- Código duplicado en Catálogos.
- Error de almacenamiento local.
- Sesión no válida al cargar acciones recientes.

### Información

- Recordatorio de que los datos no están en MySQL.
- Recordatorio de que los cambios quedan solo en el navegador actual.

## Regla de contenido

Un mensaje de éxito debe comunicar:

1. qué elemento se modificó;
2. qué acción se realizó;
3. dónde quedó guardado;
4. qué limitación tiene la maqueta mientras no exista base de datos.

Ejemplo:

> El expediente EXP-2026-0006 fue creado y guardado temporalmente en este navegador.

## Pruebas esenciales

Ejecutar los casos `NOT-01` a `NOT-10` de `docs/04_pruebas_manual_y_humo.md`.
