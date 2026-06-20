# Politicas Base del Sistema (V1)

Este documento adapta para LIVP_SEGUROS las politicas operativas usadas en LIVP_LSISTEMAS, manteniendo la arquitectura esclava: el maestro decide permisos e identidad, y LIVP_SEGUROS guarda datos reales de negocio.

## UX global

- Mantener AdminLTE 3 y Bootstrap 4.
- Usar rutas relativas y helpers existentes.
- No usar `alert()`, `confirm()` ni `prompt()`.
- Usar modales, notificaciones, loading y estados vacios coherentes.
- Toda accion temporal debe usar notificacion flotante con boton de cierre y autocierre.
- Si una accion tarda, mostrar indicador de carga desde el inicio hasta el resultado.
- Las tablas deben paginar en 10 registros por defecto cuando aplique.
- Los botones de accion en tablas administrativas deben ser icon-only, verticales, con `title` y `aria-label`.
- Los textos tecnicos largos deben truncarse solo cuando deformen la tabla; el valor completo debe quedar en `title`, `data-*`, modal o boton de copiar.

## Formularios

- Validar siempre en backend aunque exista validacion frontend.
- Los campos de clave deben tener boton de ojo (`fas fa-eye` / `fas fa-eye-slash`).
- Los campos de color deben usar `input type="color"` y validacion backend estricta `#RRGGBB`.
- Nombres y apellidos deben persistirse en MAYUSCULAS salvo excepcion explicita.
- Preservar caracteres validos como la letra N con virgulilla y tildes; no introducir mojibake.

## Subida de archivos

- Todo archivo de negocio debe guardarse fisicamente en `almacen/`.
- La BD local guarda metadatos, ruta relativa segura y trazabilidad; no guarda BLOB, Base64 ni Data URI.
- Cada flujo debe mostrar barra de progreso real cuando la subida venga desde frontend.
- Al finalizar, informar datos utiles: nombre, tipo, peso o resultado equivalente.
- No imponer limites caprichosos desde la aplicacion; respetar los limites reales del servidor salvo regla de negocio explicita.
- Rechazar extensiones ejecutables o peligrosas aunque el archivo este en carpeta bloqueada.
- No confiar en el nombre original, extension enviada ni ruta enviada por el usuario.
- Generar nombre interno unico con fecha, slug seguro y valor aleatorio.
- Organizar archivos por carpeta funcional y fecha: `almacen/{carpeta}/YYYY/MM/DD/`.
- Servir descargas o vistas privadas solo desde endpoints que validen sesion y permiso del modulo consumidor.

## Eliminacion de archivos

- Validar permiso especifico antes de eliminar.
- Usar CSRF en operaciones que cambian datos.
- Eliminar vinculos, registro de BD y archivo fisico de forma coherente.
- Si falla la eliminacion fisica, no reportar exito falso.
- Si falla la BD despues de mover un archivo nuevo, eliminar el archivo nuevo para evitar huerfanos.
- Al reemplazar un archivo, borrar el anterior solo despues de confirmar exito del nuevo flujo.

## Backend

- Usar PDO y prepared statements.
- Todo endpoint privado debe pasar por `includes/session_guard.php`.
- Validar metodo HTTP.
- Validar permiso: `puede_ver`, `puede_crear`, `puede_editar` o `puede_eliminar`.
- Usar CSRF local cuando haya cambios de datos.
- No exponer secretos, SQL interno, rutas fisicas, stack traces ni configuraciones sensibles.
- La hora oficial de aplicacion y auditoria es Lima, Peru.
