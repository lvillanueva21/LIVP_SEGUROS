# Arquitectura de modulos de negocio

Este documento define como debe nacer, publicarse y probarse un modulo de negocio en LIVP_SEGUROS.

## Obligatorio

### Flujo estandar

1. Crear la pagina logica en el maestro LIVP_LSISTEMAS.
2. Asignar permisos al rol correspondiente en el maestro.
3. Confirmar que el `codigo_pagina` coincide con la carpeta fisica esperada.
4. Crear la carpeta local `LIVP_SEGUROS/modules/{codigo}/`.
5. Crear `LIVP_SEGUROS/modules/{codigo}/index.php`.
6. Crear endpoints locales en `LIVP_SEGUROS/api/{codigo}/` cuando el modulo necesite datos dinamicos.
7. Crear o actualizar tablas locales de negocio solo si el modulo lo requiere.
8. Actualizar `LIVP_SEGUROS/docs/tablas_livp_seguros.md` si hubo cambios de BD local.
9. Probar permisos, seguridad, UX, estados vacios, errores y responsividad.

### Relacion con el maestro

- El maestro decide si el usuario puede ver o ejecutar acciones en una pagina.
- LIVP_SEGUROS usa los permisos recibidos en `$_SESSION['cliente_auth']`.
- Los modulos de negocio no deben consultar al maestro en cada accion normal.
- El codigo de pagina del maestro debe coincidir con `modules/{codigo}/index.php`.

### Carga de modulos

- El modulo debe abrirse mediante `modulo.php?m={codigo}`.
- El archivo `modules/{codigo}/index.php` no debe ser la puerta publica principal.
- Si una pagina esta permitida pero el archivo fisico no existe, el router debe mostrar "Pagina en construccion".
- Si la pagina no esta permitida, debe bloquearse el acceso.

## Estructura recomendada

```text
LIVP_SEGUROS/modules/{codigo}/index.php
LIVP_SEGUROS/api/{codigo}/list.php
LIVP_SEGUROS/api/{codigo}/create.php
LIVP_SEGUROS/api/{codigo}/update.php
LIVP_SEGUROS/api/{codigo}/toggle_state.php
```

Esta estructura es adaptable. No todos los modulos necesitan todos los endpoints.

## Recomendado

- Usar un archivo `index.php` enfocado en la interfaz del modulo.
- Usar endpoints separados para lectura, creacion, edicion y cambio de estado cuando el modulo tenga CRUD.
- Mantener nombres de endpoints simples y orientados a la accion.
- Reutilizar helpers globales cuando existan.
- Usar paginacion de 10 registros en listados.
- Mostrar loading durante requests.
- Mostrar estados vacios cuando no existan datos.
- Usar modales para crear/editar.
- Usar toasts o notificaciones del sistema para resultado de acciones.
- Mantener botones de tabla icon-only, verticales, con `title` y `aria-label`.

## Base tecnica reusable disponible

Los modulos futuros deben apoyarse en estos helpers:

- `includes/autorizacion_cliente.php`: permisos locales por pagina y accion.
- `includes/request_cliente.php`: metodo HTTP, payload, CSRF y respuestas JSON.
- `includes/conexion_cliente.php`: conexion PDO local y `cb_cliente_db_required()`.

Patron recomendado para endpoints locales:

1. Incluir `includes/session_guard.php`.
2. Exigir metodo HTTP con `cb_require_method(...)`.
3. Exigir permiso con `cb_require_cliente_permission($codigoPagina, $accion)`.
4. Exigir CSRF en acciones de cambio con `cb_require_local_csrf($scope)`.
5. Leer datos con `cb_request_payload()`.
6. Obtener PDO con `cb_cliente_db_required()` solo si el endpoint usa BD local.
7. Responder con `cb_json_success(...)` o `cb_json_error(...)`.

Para auditoria local, usar `cb_cliente_usuario_externo_id()` y guardar ese ID en las columnas de auditoria definidas para la tabla.

## Flexible

- Un modulo puede tener subcarpetas si maneja pantallas internas complejas.
- Un modulo puede tener endpoints adicionales si el requerimiento lo justifica.
- Un modulo puede tener JavaScript o CSS propio si no rompe el layout global.
- Un modulo puede usar tabs, cards, dashboards o flujos especiales si el desarrollador lo solicita.

## Validaciones visuales obligatorias

Antes de marcar un modulo como listo, validar:

- Rol visible o contexto correcto cuando aplique.
- Permiso `puede_ver`.
- Permisos de accion si hay CRUD.
- Estado vacio.
- Loading.
- Errores de red o servidor.
- Responsividad en escritorio y movil.
- Acceso bloqueado con usuario sin permiso.
- URL directa no permitida.
- Sesion expirada.
