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

### 2026-06-20 - uso de seg_archivos_vinculos para expedientes

Tabla: `seg_archivos_vinculos`
Tipo de cambio: uso documentado
Modulo relacionado: `expedientes`
Proposito: vincular archivos historicos almacenados en `seg_archivos` con expedientes comerciales sin crear un almacenamiento paralelo.

Columnas principales usadas:
- `archivo_id`
- `codigo_uso`
- `entidad_tipo`
- `entidad_id`
- `slot`
- `metadata_json`
- `estado`
- `creado_por_usuario_externo_id`
- `actualizado_por_usuario_externo_id`
- `creado_en`
- `actualizado_en`

Relaciones:
- `archivo_id` referencia `seg_archivos.id`.
- `entidad_tipo = expediente` y `entidad_id` apunta a `seg_expedientes.id`.

Valores operativos:
- `codigo_uso = expediente_documento`
- `entidad_tipo = expediente`
- `slot` guarda el codigo del tipo de documento: `documento_general`, `cotizacion`, `poliza`, `constancia`, `endoso`, `carta_fianza`, `voucher` o `garantia`.

Reglas de estado o eliminacion:
- `estado = 1` Activo.
- `estado = 0` Archivado o desvinculado.
- No hay borrado fisico desde Expedientes.
- La descarga debe pasar por endpoint protegido y no exponer rutas fisicas de `almacen/`.

Motivo del cambio:
- Permitir adjuntar, listar, descargar y archivar documentos del expediente reutilizando `almacen_core.php`, `seg_archivos` y `seg_archivos_vinculos`.

### 2026-06-20 - seg_timeline_eventos

Tabla: `seg_timeline_eventos`
Tipo de cambio: creada
Modulo relacionado: `expedientes`
Proposito: registrar eventos inmutables de actividad sobre entidades de negocio, iniciando con expedientes.

Columnas principales:
- `id`
- `entidad_tipo`
- `entidad_id`
- `codigo_evento`
- `descripcion`
- `actor_usuario_externo_id`
- `fecha_evento`
- `metadata_json`

Relaciones:
- `entidad_tipo` y `entidad_id` identifican la entidad afectada. Para esta fase se usa `entidad_tipo = expediente` y `entidad_id = seg_expedientes.id`.
- `actor_usuario_externo_id` guarda el ID del usuario autenticado proveniente del maestro. No referencia una tabla local de usuarios.

Indices y unique:
- Llave primaria `id`.
- Indice compuesto en `entidad_tipo`, `entidad_id` y `fecha_evento`.
- Indices en `codigo_evento`, `actor_usuario_externo_id` y `fecha_evento`.

Reglas de estado o eliminacion:
- Los eventos son inmutables.
- No hay edicion ni borrado de eventos desde el sistema.
- La fecha y hora se registran con la hora Lima usada por la aplicacion.

Eventos iniciales:
- `expediente_creado`
- `expediente_editado`
- `estado_expediente_modificado`
- `expediente_activado`
- `expediente_desactivado`
- `documento_cargado`
- `documento_archivado`
- `requisitos_generados`
- `requisito_estado_modificado`
- `requisito_documento_cargado`
- `requisito_documento_archivado`
- `poliza_registrada`
- `poliza_editada`
- `poliza_estado_modificado`
- `poliza_documento_principal_cargado`
- `poliza_documento_principal_archivado`
- `poliza_activada`
- `poliza_desactivada`

Motivo del cambio:
- Dar trazabilidad basica a expedientes, documentos y cambios comerciales sin implementar todavia un timeline avanzado.

### 2026-06-21 - seg_requisitos_tipo_seguro

Tabla: `seg_requisitos_tipo_seguro`
Tipo de cambio: creada
Modulo relacionado: `requisitos_tipo`
Proposito: administrar requisitos reutilizables que aplican a cada tipo de seguro activo.

Columnas principales:
- `id`
- `tipo_seguro_id`
- `codigo`
- `nombre`
- `descripcion`
- `es_obligatorio`
- `orden_visual`
- `estado`
- `creado_por_usuario_externo_id`
- `actualizado_por_usuario_externo_id`
- `creado_en`
- `actualizado_en`

Relaciones:
- `tipo_seguro_id` referencia `seg_tipos_seguro.id`.

Indices y unique:
- Llave primaria `id`.
- `codigo` unico generado por backend.
- Indices en tipo de seguro, estado, orden visual y busqueda por nombre.
- La duplicidad de requisitos activos por tipo de seguro y nombre se controla en backend porque MySQL/MariaDB no ofrece indice unico parcial portable para `estado = 1`.

Reglas de estado o eliminacion:
- `estado = 1` Activo.
- `estado = 0` Inactivo.
- No hay borrado fisico.
- Solo se permite asociar requisitos a tipos de seguro activos desde el backend.
- No se permite el mismo requisito activo repetido para el mismo tipo de seguro.

Motivo del cambio:
- Preparar la configuracion base de requisitos por tipo de seguro antes de implementarlos dentro de expedientes.

### 2026-06-21 - seg_expediente_requisitos

Tabla: `seg_expediente_requisitos`
Tipo de cambio: creada
Modulo relacionado: `expedientes`
Proposito: guardar requisitos concretos de cada expediente generados desde la configuracion reusable de requisitos por tipo de seguro.

Columnas principales:
- `id`
- `expediente_id`
- `requisito_tipo_seguro_id`
- `codigo_requisito_snapshot`
- `nombre_snapshot`
- `descripcion_snapshot`
- `es_obligatorio_snapshot`
- `orden_visual_snapshot`
- `estado_requisito`
- `observacion_actual`
- `fecha_entrega`
- `entregado_por_usuario_externo_id`
- `fecha_evaluacion`
- `evaluado_por_usuario_externo_id`
- `creado_por_usuario_externo_id`
- `actualizado_por_usuario_externo_id`
- `creado_en`
- `actualizado_en`

Relaciones:
- `expediente_id` referencia `seg_expedientes.id`.
- `requisito_tipo_seguro_id` referencia `seg_requisitos_tipo_seguro.id`.

Indices y unique:
- Llave primaria `id`.
- Combinacion unica `expediente_id` + `requisito_tipo_seguro_id` para evitar duplicados.
- Indices en expediente, requisito base, estado de requisito y orden visual.

Reglas de estado o eliminacion:
- Estados permitidos: `pendiente`, `entregado`, `observado`, `aprobado`, `rechazado`, `no_aplica`.
- No hay borrado fisico.
- Al crear un expediente se generan los requisitos activos del tipo de seguro dentro de la misma transaccion.
- Los expedientes antiguos pueden generar requisitos manualmente solo si aun no tienen requisitos.
- No se permite cambiar el tipo de seguro de un expediente cuando ya tiene requisitos generados.
- Para `observado`, `rechazado` y `no_aplica` se exige observacion o motivo.

Regla de snapshots:
- Los campos `*_snapshot` conservan la version historica del requisito al momento de generarse en el expediente.
- Cambios posteriores en `seg_requisitos_tipo_seguro` no deben alterar expedientes ya creados.

Motivo del cambio:
- Permitir checklist operativo por expediente sin perder trazabilidad historica de los requisitos definidos al momento de crear el expediente.

### 2026-06-21 - uso de seg_archivos_vinculos para respuestas de requisitos

Tabla: `seg_archivos_vinculos`
Tipo de cambio: uso documentado
Modulo relacionado: `expedientes`
Proposito: vincular documentos cargados como respuesta a un requisito concreto de expediente sin crear una tabla paralela de archivos.

Valores operativos:
- `codigo_uso = expediente_requisito_documento`
- `entidad_tipo = expediente_requisito`
- `entidad_id = seg_expediente_requisitos.id`
- `slot = respuesta_requisito`

Reglas de estado o eliminacion:
- `estado = 1` Activo.
- `estado = 0` Archivado.
- No hay borrado fisico del archivo historico.
- La descarga debe pasar por endpoint protegido de Expedientes.
- Los documentos generales de expediente usan `codigo_uso = expediente_documento` y se mantienen separados de las respuestas de requisito.

Motivo del cambio:
- Permitir evidencia documental por requisito usando `almacen_core.php`, `seg_archivos` y `seg_archivos_vinculos`.

### 2026-06-21 - seg_formatos_tipo_seguro

Tabla: `seg_formatos_tipo_seguro`
Tipo de cambio: creada
Modulo relacionado: `formatos_tipo`
Proposito: administrar formatos descargables reutilizables asociados a tipos de seguro y opcionalmente a requisitos del mismo tipo.

Columnas principales:
- `id`
- `tipo_seguro_id`
- `requisito_tipo_seguro_id`
- `codigo`
- `nombre`
- `descripcion`
- `orden_visual`
- `estado`
- `creado_por_usuario_externo_id`
- `actualizado_por_usuario_externo_id`
- `creado_en`
- `actualizado_en`

Relaciones:
- `tipo_seguro_id` referencia `seg_tipos_seguro.id`.
- `requisito_tipo_seguro_id` referencia opcionalmente `seg_requisitos_tipo_seguro.id`.
- Si existe requisito relacionado, debe pertenecer al mismo tipo de seguro del formato.

Indices y unique:
- Llave primaria `id`.
- `codigo` unico generado por backend.
- Indices en tipo de seguro, requisito relacionado, estado, orden visual y nombre.

Reglas de estado o eliminacion:
- `estado = 1` Activo.
- `estado = 0` Inactivo.
- No hay borrado fisico.
- Solo se permite crear o activar formatos de tipos de seguro activos.
- No se permite activar un formato sin archivo principal activo.

Uso de archivos:
- El archivo principal usa `almacen_core.php`, `seg_archivos` y `seg_archivos_vinculos`.
- Carpeta funcional: `almacen/formatos_tipo/archivos/YYYY/MM/DD/`.
- `codigo_uso = formato_tipo_seguro_archivo`.
- `entidad_tipo = formato_tipo_seguro`.
- `entidad_id = seg_formatos_tipo_seguro.id`.
- `slot = archivo_principal`.
- Al reemplazar archivo se archiva el vinculo anterior; no se borra fisicamente el archivo historico.

Motivo del cambio:
- Permitir que los usuarios descarguen formatos configurados segun el tipo de seguro del expediente.

### 2026-06-21 - seg_polizas

Tabla: `seg_polizas`
Tipo de cambio: creada
Modulo relacionado: `expedientes`
Proposito: registrar polizas, cartas fianza, constancias, endosos u otros documentos emitidos vinculados a un expediente.

Columnas principales:
- `id`
- `codigo`
- `expediente_id`
- `cliente_id`
- `tipo_seguro_id`
- `aseguradora_id`
- `tipo_documento_emitido`
- `numero_documento`
- `contratante_nombre_snapshot`
- `contratante_ruc_snapshot`
- `beneficiario_nombre`
- `fecha_emision`
- `vigencia_inicio`
- `vigencia_fin`
- `vigencia_dias`
- `suma_asegurada`
- `moneda`
- `prima_comercial`
- `igv`
- `prima_total`
- `estado_poliza`
- `observaciones`
- `estado`
- `creado_por_usuario_externo_id`
- `actualizado_por_usuario_externo_id`
- `creado_en`
- `actualizado_en`

Relaciones:
- `expediente_id` referencia `seg_expedientes.id`.
- `cliente_id` referencia `seg_clientes.id` y se obtiene del expediente.
- `tipo_seguro_id` referencia `seg_tipos_seguro.id` y se obtiene del expediente.
- `aseguradora_id` referencia `seg_aseguradoras.id`.

Indices y unique:
- Llave primaria `id`.
- `codigo` unico generado por backend con formato `POL-AAAA-000001`.
- Indices en expediente, cliente, tipo de seguro, aseguradora, estado de poliza, vigencia y estado activo/inactivo.

Reglas de estado o eliminacion:
- Estados permitidos: `borrador`, `emitida`, `vigente`, `cancelada`, `anulada`.
- Tipos de documento emitido permitidos: `poliza`, `carta_fianza`, `constancia`, `endoso`, `otro`.
- `estado = 1` Activo.
- `estado = 0` Inactivo.
- No hay borrado fisico.
- No se permite crear polizas en expedientes inactivos.
- La fecha de fin de vigencia debe ser posterior al inicio.
- `vigencia_dias` se calcula en backend.
- Los montos deben ser no negativos.
- Para `emitida` o `vigente`, el numero de documento es obligatorio.

Regla de snapshots:
- `contratante_nombre_snapshot` y `contratante_ruc_snapshot` conservan los datos del cliente al registrar la poliza.
- Cuando un expediente ya tiene polizas, no se debe cambiar cliente ni tipo de seguro del expediente.

Uso de archivos:
- El PDF principal usa `almacen_core.php`, `seg_archivos` y `seg_archivos_vinculos`.
- Carpeta funcional: `almacen/polizas/documentos/YYYY/MM/DD/`.
- `codigo_uso = poliza_documento_principal`.
- `entidad_tipo = poliza`.
- `entidad_id = seg_polizas.id`.
- `slot = documento_principal`.
- Solo se acepta PDF para el documento principal.
- Al reemplazar o archivar PDF se desactiva el vinculo anterior; no se borra fisicamente el archivo historico.

Timeline:
- Los eventos de poliza se registran sobre el expediente padre con `entidad_tipo = expediente`.
- Eventos: `poliza_registrada`, `poliza_editada`, `poliza_estado_modificado`, `poliza_documento_principal_cargado`, `poliza_documento_principal_archivado`, `poliza_activada`, `poliza_desactivada`.

Motivo del cambio:
- Incorporar una primera gestion basica de documentos emitidos sin implementar cotizaciones, renovaciones, endosos avanzados, pagos ni garantias.

### 2026-06-21 - cotizaciones completas en expedientes

Tablas:
- `seg_cotizaciones`
- `seg_cotizacion_datos_riesgo`
- `seg_cotizacion_alternativas`
- `seg_cotizacion_alternativa_cuotas`
- `seg_cotizacion_comparativos`
- `seg_cotizacion_comparativo_valores`

Tipo de cambio: creadas
Modulo relacionado: `expedientes`
Proposito: registrar cotizaciones comerciales completas dentro del expediente, con datos variables del riesgo, alternativas de aseguradoras, opciones de pago, comparativos y generacion de PDF en navegador.

Columnas principales de `seg_cotizaciones`:
- `id`
- `codigo`
- `expediente_id`
- `fecha_cotizacion`
- `fecha_vencimiento`
- `titulo`
- `estado_cotizacion`
- `descripcion`
- `observaciones`
- `nota_pdf`
- `estado`
- auditoria estandar local

Columnas principales de `seg_cotizacion_datos_riesgo`:
- `id`
- `cotizacion_id`
- `etiqueta`
- `valor`
- `orden_visual`
- `estado`
- auditoria estandar local

Columnas principales de `seg_cotizacion_alternativas`:
- `id`
- `cotizacion_id`
- `aseguradora_id`
- `producto_id`
- `nombre_plan_snapshot`
- `orden_visual`
- `vigencia_meses`
- `vigencia_texto`
- `suma_asegurada`
- `moneda`
- `prima_comercial`
- `igv`
- `prima_total`
- `condicion_gps`
- `es_aceptada`
- `observaciones`
- `estado`
- auditoria estandar local

Columnas principales de `seg_cotizacion_alternativa_cuotas`:
- `id`
- `alternativa_id`
- `modalidad`
- `cantidad_cuotas`
- `valor_cuota`
- `descripcion`
- `orden_visual`
- `estado`
- auditoria estandar local

Columnas principales de `seg_cotizacion_comparativos`:
- `id`
- `cotizacion_id`
- `seccion`
- `etiqueta`
- `detalle`
- `orden_visual`
- `estado`
- auditoria estandar local

Columnas principales de `seg_cotizacion_comparativo_valores`:
- `id`
- `comparativo_id`
- `alternativa_id`
- `valor`
- `estado`
- auditoria estandar local

Relaciones:
- `seg_cotizaciones.expediente_id` referencia `seg_expedientes.id`.
- `seg_cotizacion_datos_riesgo.cotizacion_id` referencia `seg_cotizaciones.id`.
- `seg_cotizacion_alternativas.cotizacion_id` referencia `seg_cotizaciones.id`.
- `seg_cotizacion_alternativas.aseguradora_id` referencia `seg_aseguradoras.id`.
- `seg_cotizacion_alternativas.producto_id` referencia opcionalmente `seg_productos.id`.
- `seg_cotizacion_alternativa_cuotas.alternativa_id` referencia `seg_cotizacion_alternativas.id`.
- `seg_cotizacion_comparativos.cotizacion_id` referencia `seg_cotizaciones.id`.
- `seg_cotizacion_comparativo_valores.comparativo_id` referencia `seg_cotizacion_comparativos.id`.
- `seg_cotizacion_comparativo_valores.alternativa_id` referencia `seg_cotizacion_alternativas.id`.

Indices y unique:
- `seg_cotizaciones.codigo` es unico y generado por backend con formato `COT-AAAA-000001`.
- Indices por expediente, estado de cotizacion, estado activo/inactivo, aseguradora, producto, alternativa, seccion y orden visual.

Reglas de estado o eliminacion:
- Estados de cotizacion permitidos: `borrador`, `enviada`, `aceptada`, `vencida`, `perdida`, `cancelada`.
- Condicion GPS permitida: `no_requiere`, `requerido`, `opcional`, `pendiente`.
- Modalidades de cuota permitidas: `afiliacion`, `cupon`, `contado`, `otro`.
- Secciones comparativas permitidas: `cobertura`, `servicio`, `deducible`, `condicion`, `otro`.
- `estado = 1` Activo.
- `estado = 0` Inactivo.
- No hay borrado fisico; los hijos se versionan operativamente desactivando registros previos al editar.
- Solo una alternativa activa puede estar aceptada por cotizacion.
- Una cotizacion en estado `aceptada` debe tener exactamente una alternativa aceptada.
- Solo se permiten aseguradoras activas y productos activos compatibles con la aseguradora y ramo del tipo de seguro del expediente.
- Los montos no pueden ser negativos.

PDF:
- La vista previa se genera en HTML formato A4 dentro de Expedientes.
- El PDF se genera en navegador usando pdfMake local desde `plugins/pdfmake/pdfmake.min.js` y `plugins/pdfmake/vfs_fonts.js`.
- No usa TCPDF, Dompdf, Composer, CDN ni API externa.
- El PDF no se guarda aun como archivo historico; se visualiza o descarga desde el navegador.

Timeline:
- Los eventos se registran sobre el expediente padre con `entidad_tipo = expediente`.
- Eventos: `cotizacion_creada`, `cotizacion_editada`, `cotizacion_estado_modificado`, `cotizacion_alternativa_registrada`, `cotizacion_alternativa_modificada`, `cotizacion_alternativa_aceptada`, `cotizacion_cuota_registrada`, `cotizacion_comparativo_actualizado`, `cotizacion_activada`, `cotizacion_desactivada`.
- Si no hay cambios reales, no se generan eventos nuevos.

Motivo del cambio:
- Permitir cotizaciones comerciales completas, comparables y descargables en PDF sin implementar todavia aceptacion formal vinculada a polizas, pagos, vouchers ni garantias.

### 2026-06-21 - seg_poliza_extracciones

Tabla: `seg_poliza_extracciones`
Tipo de cambio: pendiente de creacion manual
Modulo relacionado: `expedientes`
Proposito: guardar evidencia auxiliar del analisis de PDF usado para prellenar una poliza, sin reemplazar los campos estructurados de `seg_polizas`.

Columnas principales:
- `id`
- `poliza_id`
- `expediente_id`
- `archivo_id`
- `metodo_extraccion`
- `estado_extraccion`
- `confianza_global`
- `campos_extraidos_json`
- `texto_extraido`
- `observaciones`
- `estado`
- `creado_por_usuario_externo_id`
- `actualizado_por_usuario_externo_id`
- `creado_en`
- `actualizado_en`

Relaciones:
- `poliza_id` referencia `seg_polizas.id`.
- `expediente_id` referencia `seg_expedientes.id`.
- `archivo_id` referencia opcionalmente `seg_archivos.id`.

Indices y unique:
- Llave primaria `id`.
- Indices en poliza, expediente, archivo, metodo, estado de extraccion y estado activo/inactivo.

Reglas de estado o eliminacion:
- `estado = 1` Activo.
- `estado = 0` Inactivo.
- No hay borrado fisico.
- Estados de extraccion: `pendiente`, `extraida`, `revisada`, `guardada`, `fallida`, `ocr_requerido`.
- Metodos de extraccion: `manual`, `texto_pdf`, `ocr`, `mixto`, `ocr_pendiente`.
- La extraccion no reemplaza la revision humana: el usuario decide que datos guardar en `seg_polizas`.

Motivo del cambio:
- Permitir trazabilidad de campos y texto extraido al subir una poliza en PDF, preparando una base para OCR completo futuro sin crear almacenamiento paralelo.
- En el dump local `u517204426_in5vRANce_01.sql` revisado el 2026-06-21 esta tabla no aparece todavia; debe crearse manualmente en phpMyAdmin antes de considerar esta evidencia auxiliar como instalada.

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
