# Almacen de Archivos V1

Este documento define la base reusable para guardar archivos de negocio en LIVP_SEGUROS.

## Objetivo

Centralizar el guardado de imagenes, documentos y adjuntos en una carpeta fisica unica:

```text
almacen/
```

Los modulos futuros decidiran sus carpetas funcionales cuando existan. El backend ya acepta carpetas seguras como:

```text
aseguradoras/logos
clientes/documentos
usuarios/fotos_perfil
polizas/adjuntos
```

La ruta fisica final se genera asi:

```text
almacen/{carpeta}/YYYY/MM/DD/{nombre_interno}
```

## Regla central

- El archivo vive en disco.
- La BD local guarda metadatos, ruta relativa segura y trazabilidad.
- No se usan BLOB, Base64 ni Data URI.
- La ruta fisica real no se expone en JSON ni en mensajes de error.
- `almacen/.htaccess` bloquea acceso directo.
- Los modulos consumidores deben servir archivos mediante endpoints propios, despues de validar sesion y permiso.

## Backend implementado

Archivo principal:

```text
includes/almacen_core.php
```

Funciones principales:

- `cb_almacen_guardar_upload(PDO $pdo, array $fileInfo, array $options, &$errors = [])`
- `cb_almacen_obtener_archivo(PDO $pdo, $archivoId, $soloActivo = true)`
- `cb_almacen_obtener_por_ruta(PDO $pdo, $rutaRelativa, $soloActivo = true)`
- `cb_almacen_payload_archivo(array $row)`
- `cb_almacen_servir_archivo(array $payload, $inline = false)`
- `cb_almacen_delete_by_ruta(PDO $pdo, $rutaRelativa, $actorUserId, &$errors = [])`
- `cb_almacen_delete_file_by_row(PDO $pdo, array $row, $actorUserId, &$errors = [])`

## Tablas esperadas

La estructura esta pensada en dos tablas:

- `seg_archivos`: registro principal del archivo.
- `seg_archivos_vinculos`: relacion futura entre archivo y uso de negocio.

Los querys se entregan por chat para ejecucion manual en phpMyAdmin. No se crean archivos `.sql` ni se ejecutan migraciones automaticas.

## Metadatos minimos

`seg_archivos` debe guardar:

- carpeta funcional normalizada,
- tipo de archivo,
- nombre original,
- nombre interno,
- extension,
- MIME detectado,
- tamanio,
- dimensiones si es imagen,
- ruta relativa,
- checksum SHA-256,
- descripcion opcional,
- estado,
- usuario externo creador/editor,
- fechas de auditoria.

`seg_archivos_vinculos` debe guardar:

- archivo relacionado,
- codigo de uso,
- tipo de entidad,
- id de entidad,
- slot,
- orden,
- metadata JSON opcional,
- estado,
- usuario externo creador/editor,
- fechas de auditoria.

## Seguridad

- Se rechazan extensiones ejecutables o peligrosas: PHP, scripts, ejecutables, HTML/JS, entre otras.
- Se detecta MIME real con `finfo` o `mime_content_type`.
- Para imagenes se guardan dimensiones si `getimagesize` puede leerlas.
- El nombre interno nunca depende solo del nombre original.
- La carpeta se normaliza por segmentos seguros.
- La ruta relativa debe iniciar con `almacen/`.
- Las rutas con `..`, rutas absolutas, protocolos o backslashes se rechazan.

## Consumo desde modulos futuros

Cada modulo debe:

1. Validar sesion con `includes/session_guard.php`.
2. Validar permiso especifico del modulo.
3. Validar CSRF en cambios.
4. Llamar a `cb_almacen_guardar_upload(...)` con su carpeta funcional.
5. Guardar el `archivo_id` o usar `seg_archivos_vinculos`.
6. Servir vista/descarga mediante endpoint propio del modulo.

Ejemplo conceptual:

```php
$saved = cb_almacen_guardar_upload($pdo, $_FILES['archivo'], [
    'carpeta' => 'clientes/documentos',
    'usuario_id' => cb_cliente_usuario_externo_id(),
    'descripcion' => 'Documento de cliente',
    'vinculo' => [
        'codigo_uso' => 'cliente_documento',
        'entidad_tipo' => 'seg_clientes',
        'entidad_id' => $clienteId,
        'slot' => 'documento',
    ],
], $errors);
```

## Aplicacion actual

El flujo de logos de aseguradoras queda conectado al almacen central:

```text
almacen/aseguradoras/logos/YYYY/MM/DD/
```

La tabla especifica `seg_aseguradora_logo_archivos` puede seguir guardando metadatos funcionales del modulo Catalogos, mientras que `seg_archivos` y `seg_archivos_vinculos` registran el archivo y su uso general.

