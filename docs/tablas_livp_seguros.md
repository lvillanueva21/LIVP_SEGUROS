# Tablas locales de LIVP_SEGUROS

Este documento registra el historial real de tablas locales de negocio creadas, modificadas o eliminadas en LIVP_SEGUROS.

La BD local de LIVP_SEGUROS pertenece al sistema esclavo Broker Seguros y debe almacenar datos de negocio, no usuarios de login, claves, hashes, secretos ni credenciales API.

## Estado actual

Las primeras tablas locales de negocio de Catalogos V1 ya existen fisicamente en la BD local de LIVP_SEGUROS. Fueron creadas manualmente por el desarrollador en phpMyAdmin antes de implementar el modulo `catalogos`.

## Historial real

### 2026-06-19 - seg_aseguradoras

Tabla: `seg_aseguradoras`
Tipo de cambio: creada
Modulo relacionado: `catalogos`
Proposito: almacenar aseguradoras disponibles para productos, polizas y procesos futuros.

Columnas principales:
- `id`
- `codigo`
- `razon_social`
- `nombre_comercial`
- `ruc`
- `contacto_nombre`
- `contacto_email`
- `contacto_telefono`
- `sitio_web`
- `observaciones`
- `estado`
- `creado_por_usuario_externo_id`
- `actualizado_por_usuario_externo_id`
- `creado_en`
- `actualizado_en`

Relaciones:
- Sera referenciada por `seg_productos.aseguradora_id`.

Indices y unique:
- Llave primaria `id`.
- Codigo unico.
- Razon social unica.
- RUC unico cuando exista.
- Indices de busqueda y estado segun estructura creada manualmente.

Reglas de estado o eliminacion:
- `estado = 1` Activo.
- `estado = 0` Desactivado.
- No hay borrado fisico en Catalogos V1.
- No se permite desactivar una aseguradora con productos activos.

Motivo del cambio:
- Primera base de datos local de negocio para Catalogos V1.

### 2026-06-19 - seg_ramos

Tabla: `seg_ramos`
Tipo de cambio: creada
Modulo relacionado: `catalogos`
Proposito: almacenar ramos de seguro como vehicular, vida, salud u otros.

Columnas principales:
- `id`
- `codigo`
- `nombre`
- `descripcion`
- `estado`
- `creado_por_usuario_externo_id`
- `actualizado_por_usuario_externo_id`
- `creado_en`
- `actualizado_en`

Relaciones:
- Sera referenciada por `seg_productos.ramo_id`.

Indices y unique:
- Llave primaria `id`.
- Codigo unico.
- Nombre unico.
- Indices de busqueda y estado segun estructura creada manualmente.

Reglas de estado o eliminacion:
- `estado = 1` Activo.
- `estado = 0` Desactivado.
- No hay borrado fisico en Catalogos V1.
- No se permite desactivar un ramo con productos activos.

Motivo del cambio:
- Primera base de datos local de negocio para Catalogos V1.

### 2026-06-19 - seg_productos

Tabla: `seg_productos`
Tipo de cambio: creada
Modulo relacionado: `catalogos`
Proposito: almacenar productos o planes comercializados por aseguradora y ramo.

Columnas principales:
- `id`
- `aseguradora_id`
- `ramo_id`
- `codigo`
- `nombre_producto`
- `nombre_plan`
- `descripcion`
- `estado`
- `creado_por_usuario_externo_id`
- `actualizado_por_usuario_externo_id`
- `creado_en`
- `actualizado_en`

Relaciones:
- `aseguradora_id` referencia `seg_aseguradoras.id`.
- `ramo_id` referencia `seg_ramos.id`.

Indices y unique:
- Llave primaria `id`.
- Codigo unico.
- Combinacion unica de aseguradora, ramo, producto y plan segun estructura creada manualmente.
- Indices en claves foraneas y estado segun estructura creada manualmente.

Reglas de estado o eliminacion:
- `estado = 1` Activo.
- `estado = 0` Desactivado.
- No hay borrado fisico en Catalogos V1.
- Para crear, editar o reactivar un producto, la aseguradora y el ramo deben estar activos.

Motivo del cambio:
- Primera base de datos local de negocio para Catalogos V1.

## Plantilla obligatoria para registros futuros

Copiar esta plantilla cada vez que se cree, modifique o elimine una tabla local.

```text
Fecha:
Tabla:
Tipo de cambio: creada / modificada / eliminada
Modulo relacionado:
Proposito:

Columnas principales:
-

Relaciones:
-

Indices y unique:
-

Reglas de estado o eliminacion:
-

Motivo del cambio:
-
```

## Reglas de mantenimiento

- No registrar tablas que no existan realmente.
- No documentar inserts demo como si fueran estructura del sistema.
- Actualizar este archivo en la misma fase donde se proponga o aplique un cambio de BD local.
- Si una tabla cambia, agregar un nuevo registro historico; no borrar la historia previa.
- Si una tabla se elimina, registrar la eliminacion y el motivo.
