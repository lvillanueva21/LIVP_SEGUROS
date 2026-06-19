# Diseno Catalogos V1

Este documento define el diseno inicial del modulo `catalogos` para LIVP_SEGUROS.

Las tablas descritas aqui ya fueron creadas manualmente por el desarrollador en phpMyAdmin y se registran en `LIVP_SEGUROS/docs/tablas_livp_seguros.md`.

## Alcance funcional

El modulo `catalogos` tendra tres pestanas internas:

- Aseguradoras.
- Ramos.
- Productos / Planes.

El codigo de pagina esperado en el maestro es:

```text
catalogos
```

La ruta fisica del modulo es:

```text
LIVP_SEGUROS/modules/catalogos/index.php
```

## Reglas generales

- La BD local es exclusiva de Broker Seguros.
- No se usa `id_servicio`.
- Las tablas locales usan prefijo `seg_`.
- Motor: InnoDB.
- Charset: utf8mb4.
- Collation propuesta: utf8mb4_unicode_ci.
- No existen foreign keys hacia LIVP_LSISTEMAS.
- Los IDs de auditoria son escalares tomados de `$_SESSION['cliente_auth']['usuario']['id']`.
- La hora de auditoria debe generarse desde PHP usando `America/Lima`.
- No usar `CURRENT_TIMESTAMP` ni la hora global del servidor como fuente principal de auditoria.
- Estado activo/inactivo mediante campo `estado`.
- En Catalogos V1 no hay eliminacion fisica.
- Las relaciones locales usan `ON DELETE RESTRICT` y `ON UPDATE RESTRICT`.

## Tabla seg_aseguradoras

Proposito: almacenar aseguradoras disponibles para productos, polizas y procesos futuros.

Campos:

- `id`: BIGINT UNSIGNED, PK autoincremental.
- `codigo`: VARCHAR(40), obligatorio, codigo interno unico.
- `razon_social`: VARCHAR(180), obligatorio.
- `nombre_comercial`: VARCHAR(180), opcional.
- `ruc`: VARCHAR(20), opcional, unico cuando exista.
- `contacto_nombre`: VARCHAR(120), opcional.
- `contacto_email`: VARCHAR(160), opcional.
- `contacto_telefono`: VARCHAR(40), opcional.
- `sitio_web`: VARCHAR(200), opcional.
- `observaciones`: TEXT, opcional.
- `estado`: TINYINT(1), obligatorio, default 1.
- `creado_por_usuario_externo_id`: BIGINT UNSIGNED, obligatorio.
- `actualizado_por_usuario_externo_id`: BIGINT UNSIGNED, obligatorio.
- `creado_en`: DATETIME, obligatorio.
- `actualizado_en`: DATETIME, obligatorio.

Reglas de duplicidad:

- `codigo` no se repite.
- `ruc` no se repite cuando se informa.
- `razon_social` no se repite.

Reglas de inactivacion:

- Una aseguradora puede desactivarse con `estado = 0`.
- En fases futuras, si una aseguradora tiene productos, polizas u otros datos relacionados, no debe eliminarse fisicamente.

## Tabla seg_ramos

Proposito: almacenar categorias de seguro como vehicular, vida, salud, SCTR u otros ramos del negocio.

Campos:

- `id`: BIGINT UNSIGNED, PK autoincremental.
- `codigo`: VARCHAR(40), obligatorio, codigo interno unico.
- `nombre`: VARCHAR(120), obligatorio, nombre unico.
- `descripcion`: TEXT, opcional.
- `estado`: TINYINT(1), obligatorio, default 1.
- `creado_por_usuario_externo_id`: BIGINT UNSIGNED, obligatorio.
- `actualizado_por_usuario_externo_id`: BIGINT UNSIGNED, obligatorio.
- `creado_en`: DATETIME, obligatorio.
- `actualizado_en`: DATETIME, obligatorio.

Reglas de duplicidad:

- `codigo` no se repite.
- `nombre` no se repite.

Reglas de inactivacion:

- Un ramo puede desactivarse con `estado = 0`.
- En fases futuras, si un ramo tiene productos o polizas relacionadas, no debe eliminarse fisicamente.

## Tabla seg_productos

Proposito: almacenar productos o planes comercializados por aseguradora y ramo.

Campos:

- `id`: BIGINT UNSIGNED, PK autoincremental.
- `aseguradora_id`: BIGINT UNSIGNED, obligatorio.
- `ramo_id`: BIGINT UNSIGNED, obligatorio.
- `codigo`: VARCHAR(40), obligatorio, codigo interno unico.
- `nombre_producto`: VARCHAR(160), obligatorio.
- `nombre_plan`: VARCHAR(160), opcional a nivel funcional; en BD se guarda como cadena vacia cuando no aplica.
- `descripcion`: TEXT, opcional.
- `estado`: TINYINT(1), obligatorio, default 1.
- `creado_por_usuario_externo_id`: BIGINT UNSIGNED, obligatorio.
- `actualizado_por_usuario_externo_id`: BIGINT UNSIGNED, obligatorio.
- `creado_en`: DATETIME, obligatorio.
- `actualizado_en`: DATETIME, obligatorio.

Relaciones:

- `aseguradora_id` referencia `seg_aseguradoras.id`.
- `ramo_id` referencia `seg_ramos.id`.
- Ambas relaciones usan `ON DELETE RESTRICT` y `ON UPDATE RESTRICT`.

Reglas de duplicidad:

- `codigo` no se repite.
- No se repite la combinacion `aseguradora_id + ramo_id + nombre_producto + nombre_plan`.

Reglas de inactivacion:

- Un producto puede desactivarse con `estado = 0`.
- En fases futuras, si un producto ya esta relacionado con polizas, cuotas, cobranzas o siniestros, no debe eliminarse fisicamente.

## Motivo para no permitir eliminacion fisica

Los catalogos seran base de polizas, cobranzas, siniestros y reportes. El borrado fisico podria romper trazabilidad historica y relaciones futuras. Por eso Catalogos V1 usara activacion/desactivacion mediante `estado`.

## Indices y busquedas

Indices propuestos:

- Busqueda por estado.
- Busqueda por razon social, nombre, nombre de producto.
- Indices en claves foraneas locales.
- UNIQUE para codigos y reglas principales de duplicidad.

## Implementacion funcional

Catalogos V1 se implementa con:

- `LIVP_SEGUROS/modules/catalogos/index.php`
- `LIVP_SEGUROS/api/catalogos/resumen.php`
- `LIVP_SEGUROS/api/catalogos/aseguradoras.php`
- `LIVP_SEGUROS/api/catalogos/ramos.php`
- `LIVP_SEGUROS/api/catalogos/productos.php`

Los endpoints validan sesion, permisos por accion, metodo HTTP, CSRF en cambios, PDO con prepared statements y auditoria con usuario externo de sesion.

## Pendientes futuros

- Crear modulos de polizas, cuotas, cobranzas, siniestros y reportes cuando el desarrollador lo solicite.
- Bloquear nuevas reglas de inactivacion cuando productos, aseguradoras o ramos ya esten asociados a polizas reales.
