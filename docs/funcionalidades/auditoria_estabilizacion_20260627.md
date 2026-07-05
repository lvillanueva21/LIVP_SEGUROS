# Auditoria y estabilizacion - 27/06/2026

Version: `BS-20260627-133302-PET`

## Alcance

Esta correccion estabiliza Catalogos, Expedientes, notificaciones, migraciones de `localStorage`, permisos de maqueta y fechas con zona `America/Lima`.

No implementa plantillas ni cotizaciones completas.

## Decisiones aplicadas

- Un expediente es un contenedor flexible de una necesidad o caso.
- Crear un expediente solo exige cliente o entidad y responsable.
- Asunto y descripcion son opcionales.
- Situaciones validas: Abierto, En seguimiento, En espera, Cerrado y Cancelado.
- Tipo de seguro, aseguradora, moneda, prima, cuotas, vigencia, deducibles, GPS, coberturas, advertencias, mensajes y notas quedan fuera del expediente raiz.
- Las cotizaciones seran hijas opcionales del expediente.

## Migraciones locales

`assets/js/cache-migrations.js` centraliza:

- migracion de situaciones antiguas de Catalogos;
- lectura de Catalogos migrados desde cualquier modulo;
- migracion de `broker_seguros_demo_expedients_v1` a `broker_seguros_demo_expedients_v2`;
- normalizacion de fechas y horas Lima.

La migracion no depende de abrir primero Catalogos. Expedientes puede iniciar la migracion por si mismo.

## Permisos

Los permisos actuales controlan interfaz y rutas PHP de la maqueta. La informacion guardada en `localStorage` no reemplaza autorizacion real de servidor. Cuando exista MySQL, todos los permisos y filtros deberan validarse en backend.

## Limitaciones

- `localStorage` es por navegador/perfil y puede ser compartido por usuarios que usan el mismo navegador.
- Dos pestanas pueden competir al crear expedientes; la maqueta relee cache antes de generar codigo, pero la unicidad definitiva requiere backend/MySQL.
- Incognito no comparte datos con el navegador normal, por diseno del navegador.
