# Diseno Logos de Aseguradoras V1

Este documento reemplaza el diseno incorrecto anterior basado en BLOB.

## Regla oficial

Las imagenes, logos, fotos y archivos de LIVP_SEGUROS se almacenan como archivos fisicos en el servidor. La BD local solo guarda metadatos y una ruta relativa segura.

Prohibido:

- guardar imagenes como BLOB, MEDIUMBLOB o LONGBLOB;
- guardar imagenes como Base64 o Data URI;
- guardar el nombre original como ruta fisica;
- guardar rutas arbitrarias enviadas por usuario;
- subir imagenes al repositorio.

## Patron adoptado desde LIVP_LSISTEMAS

El maestro usa almacenamiento fisico con ruta relativa, nombre interno aleatorio y metadatos en BD. Para LIVP_SEGUROS se adapta asi:

```text
storage/imagenes/aseguradoras/logos/YYYY/MM/DD/
```

La carpeta es propia de LIVP_SEGUROS y no depende de LIVP_LSISTEMAS.

## Tabla propuesta

`seg_aseguradora_logo_archivos`

Campos:

- `id`
- `aseguradora_id`
- `ruta_relativa`
- `nombre_original`
- `nombre_interno`
- `extension`
- `mime_type`
- `tamanio_bytes`
- `ancho_px`
- `alto_px`
- `checksum_sha256`
- `creado_por_usuario_externo_id`
- `actualizado_por_usuario_externo_id`
- `creado_en`
- `actualizado_en`

La tabla guarda solo un logo vigente por aseguradora mediante UNIQUE `aseguradora_id`.

## Reemplazo seguro

1. Validar el archivo recibido por HTTP.
2. Generar nombre interno aleatorio.
3. Guardar el nuevo archivo fisico.
4. Actualizar metadatos en BD.
5. Eliminar el archivo anterior solo si la operacion nueva quedo correcta.
6. Si falla BD, eliminar el archivo nuevo para evitar archivos huerfanos.

## Eliminacion segura

Quitar logo elimina el archivo fisico asociado y borra el metadato del logo. No elimina la aseguradora ni cambia sus datos de catalogo.

## Validacion

- MIME real mediante `finfo`.
- Contenido real mediante `getimagesize`.
- Permitidos para este flujo: PNG, JPEG y WEBP.
- Rechazados: SVG, GIF, PDF y archivos renombrados con MIME falso.
- Sin limite de tamano impuesto por la aplicacion; se respetan limites reales del servidor.
- No se confia en extension ni nombre original.

## Cache y fallback

La interfaz usa vista previa local antes de guardar y parametro de version basado en `actualizado_en` para evitar cache obsoleta. Si no existe logo, se muestra fallback con icono corporativo.

## Pendiente operativo

La tabla no debe registrarse como existente hasta que el desarrollador ejecute manualmente el query en phpMyAdmin.
