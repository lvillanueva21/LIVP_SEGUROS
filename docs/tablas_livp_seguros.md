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

### 2026-06-20 - seg_tipos_seguro

Tabla: `seg_tipos_seguro`
Tipo de cambio: propuesta para creacion manual
Modulo relacionado: `catalogos`
Proposito: almacenar tipos de seguro configurables que se usaran como base para futuros expedientes, cotizaciones y polizas.

Columnas principales:
- `id`
- `ramo_id`
- `codigo`
- `nombre`
- `descripcion`
- `ejemplo_uso`
- `orden_visual`
- `estado`
- `creado_por_usuario_externo_id`
- `actualizado_por_usuario_externo_id`
- `creado_en`
- `actualizado_en`

Relaciones:
- `ramo_id` referencia `seg_ramos.id`.

Indices y unique:
- Llave primaria `id`.
- Codigo tecnico unico.
- Combinacion unica de ramo y nombre.
- Indices en `ramo_id`, `estado` y `orden_visual`.

Reglas de estado o eliminacion:
- `estado = 1` Activo.
- `estado = 0` Desactivado.
- No hay borrado fisico.
- Para crear, editar o reactivar, el ramo asociado debe estar activo.
- El codigo se genera al crear y no cambia cuando se edita el nombre.

Motivo del cambio:
- Ampliar Catalogos con una base configurable para futuros modulos de expedientes.

### 2026-06-20 - seg_estados_expediente

Tabla: `seg_estados_expediente`
Tipo de cambio: propuesta para creacion manual
Modulo relacionado: `catalogos`
Proposito: almacenar estados configurables para el ciclo futuro de expedientes.

Columnas principales:
- `id`
- `codigo`
- `nombre`
- `descripcion`
- `ejemplo_uso`
- `color_etiqueta`
- `orden_visual`
- `es_inicial`
- `estado`
- `creado_por_usuario_externo_id`
- `actualizado_por_usuario_externo_id`
- `creado_en`
- `actualizado_en`

Relaciones:
- No tiene relaciones directas en esta fase.

Indices y unique:
- Llave primaria `id`.
- Codigo tecnico unico.
- Nombre unico.
- Indices en `estado`, `es_inicial` + `estado` y `orden_visual`.

Reglas de estado o eliminacion:
- `estado = 1` Activo.
- `estado = 0` Desactivado.
- No hay borrado fisico.
- Debe existir como maximo un estado inicial activo.
- Al marcar un estado inicial activo, los anteriores dejan de ser iniciales mediante transaccion.
- No se permite desactivar el unico estado inicial activo.
- El codigo se genera al crear y no cambia cuando se edita el nombre.

Motivo del cambio:
- Ampliar Catalogos con una base configurable para futuros modulos de expedientes.

### 2026-06-20 - seg_clientes

Tabla: `seg_clientes`
Tipo de cambio: modificada
Modulo relacionado: `clientes`
Proposito: ampliar el cliente comercial para soportar empresas y consorcios sin romper las empresas ya registradas.

Columnas principales agregadas o ajustadas:
- `tipo_cliente`
- `ruc` pasa a permitir `NULL` para consorcios con operador tributario.

Relaciones:
- Sera referenciada por `seg_cliente_consorcios.cliente_id`.
- Sera referenciada por `seg_cliente_consorcios.operador_cliente_id`.
- Sera referenciada por `seg_cliente_consorcio_integrantes.consorcio_cliente_id`.
- Sera referenciada por `seg_cliente_consorcio_integrantes.empresa_cliente_id`.

Indices y unique:
- Se mantiene `codigo` unico.
- Se mantiene `ruc` unico; MySQL/MariaDB permite varios `NULL`, lo que habilita consorcios sin RUC propio.
- Indice recomendado en `tipo_cliente` y `estado`.

Reglas de estado o eliminacion:
- `estado = 1` Activo.
- `estado = 0` Desactivado.
- No hay borrado fisico.
- Empresas y consorcios con RUC propio deben tener RUC valido de 11 digitos.
- Consorcios con operador tributario deben tener `ruc = NULL`.

Motivo del cambio:
- Permitir el registro de consorcios con RUC propio o con operador tributario sin crear una pagina nueva.

### 2026-06-20 - seg_cliente_consorcios

Tabla: `seg_cliente_consorcios`
Tipo de cambio: creada
Modulo relacionado: `clientes`
Proposito: almacenar la configuracion 1 a 1 de un cliente comercial tipo consorcio.

Columnas principales:
- `id`
- `cliente_id`
- `modalidad`
- `operador_cliente_id`
- `estado`
- `creado_por_usuario_externo_id`
- `actualizado_por_usuario_externo_id`
- `creado_en`
- `actualizado_en`

Relaciones:
- `cliente_id` referencia `seg_clientes.id` y debe corresponder a un cliente tipo `consorcio`.
- `operador_cliente_id` referencia `seg_clientes.id` y debe corresponder a una empresa activa cuando la modalidad sea operador tributario.

Indices y unique:
- Llave primaria `id`.
- `cliente_id` unico para asegurar relacion 1 a 1.
- Indices en `modalidad`, `operador_cliente_id` y `estado`.

Reglas de estado o eliminacion:
- `estado = 1` Activo.
- `estado = 0` Desactivado.
- No hay borrado fisico.

Motivo del cambio:
- Separar reglas especificas de consorcios sin contaminar los campos generales del cliente comercial.

### 2026-06-20 - seg_cliente_consorcio_integrantes

Tabla: `seg_cliente_consorcio_integrantes`
Tipo de cambio: creada
Modulo relacionado: `clientes`
Proposito: registrar las empresas integrantes de cada consorcio.

Columnas principales:
- `id`
- `consorcio_cliente_id`
- `empresa_cliente_id`
- `participacion_porcentaje`
- `rol_descripcion`
- `estado`
- `creado_por_usuario_externo_id`
- `actualizado_por_usuario_externo_id`
- `creado_en`
- `actualizado_en`

Relaciones:
- `consorcio_cliente_id` referencia `seg_clientes.id`.
- `empresa_cliente_id` referencia `seg_clientes.id`.

Indices y unique:
- Llave primaria `id`.
- Combinacion unica `consorcio_cliente_id` + `empresa_cliente_id`.
- Indices en consorcio, empresa y estado.

Reglas de estado o eliminacion:
- `estado = 1` Activo.
- `estado = 0` Desactivado.
- No hay borrado fisico.
- No se permite repetir una empresa dentro del mismo consorcio.
- Un consorcio requiere al menos dos integrantes activos.

Motivo del cambio:
- Permitir modelar consorcios con multiples empresas integrantes y operador tributario vinculado.

### 2026-06-20 - seg_expedientes

Tabla: `seg_expedientes`
Tipo de cambio: creada
Modulo relacionado: `expedientes`
Proposito: almacenar el expediente comercial base de una solicitud de seguro para un cliente activo.

Columnas principales:
- `id`
- `codigo`
- `cliente_id`
- `tipo_seguro_id`
- `estado_expediente_id`
- `descripcion`
- `observaciones`
- `fecha_apertura`
- `estado`
- `creado_por_usuario_externo_id`
- `actualizado_por_usuario_externo_id`
- `creado_en`
- `actualizado_en`

Relaciones:
- `cliente_id` referencia `seg_clientes.id`.
- `tipo_seguro_id` referencia `seg_tipos_seguro.id`.
- `estado_expediente_id` referencia `seg_estados_expediente.id`.

Indices y unique:
- Llave primaria `id`.
- `codigo` unico con formato `EXP-AAAA-000001`.
- Indices en cliente, tipo de seguro, estado de expediente, fecha de apertura y estado activo/inactivo.

Reglas de estado o eliminacion:
- `estado = 1` Activo.
- `estado = 0` Desactivado.
- No hay borrado fisico.
- Al crear, el estado de expediente se toma del unico estado inicial activo configurado.
- No se permite crear si no existe exactamente un estado inicial activo.

Motivo del cambio:
- Crear la primera base operativa para futuros flujos de cotizaciones, polizas, requisitos y documentos sin implementar todavia esos modulos.

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
