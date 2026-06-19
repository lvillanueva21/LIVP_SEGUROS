# Seguridad de endpoints locales

Este documento define el contrato obligatorio para endpoints locales de negocio en LIVP_SEGUROS.

## Obligatorio para todo endpoint privado

- Incluir o pasar por `includes/session_guard.php`.
- Validar que exista sesion local activa.
- Validar permiso especifico segun la accion.
- Validar el metodo HTTP esperado.
- Validar y normalizar datos en backend.
- Usar PDO y prepared statements para cualquier acceso a BD.
- Devolver JSON consistente.
- No revelar secretos, SQL interno, stack traces, rutas fisicas ni configuraciones sensibles.
- Manejar errores de forma controlada.
- Mantener trazabilidad cuando corresponda.

## Helpers reales disponibles

Los endpoints locales deben reutilizar estos helpers:

- `includes/autorizacion_cliente.php`
  - `cb_cliente_puede($codigoPagina, $accion)`: valida `puede_ver`, `puede_crear`, `puede_editar` o `puede_eliminar` desde la sesion local.
  - `cb_require_cliente_permission($codigoPagina, $accion)`: corta la ejecucion con JSON 403 si el usuario no tiene permiso.
  - `cb_cliente_usuario_externo_id()`: obtiene el ID del usuario externo actual para auditoria local.
- `includes/request_cliente.php`
  - `cb_require_method($methodOrMethods)`: exige metodo HTTP permitido.
  - `cb_request_payload()`: obtiene payload JSON, POST o cuerpo urlencoded.
  - `cb_local_csrf_token($scope)`: crea o recupera token CSRF local por alcance.
  - `cb_validate_local_csrf($scope, $token)`: valida token con `hash_equals`.
  - `cb_require_local_csrf($scope)`: corta la ejecucion con JSON 403 si el token no es valido.
  - `cb_json_success($message, $data = [], $httpStatus = 200)`: respuesta JSON uniforme de exito.
  - `cb_json_error($code, $message, $httpStatus = 400, $errors = [])`: respuesta JSON uniforme de error.
- `includes/conexion_cliente.php`
  - `cb_cliente_db_required()`: devuelve PDO local o lanza error controlado sin exponer credenciales.

`includes/session_guard.php` carga los helpers de autorizacion para endpoints privados. Si un endpoint necesita CSRF, payload o JSON uniforme, puede usar directamente los helpers disponibles desde esa carga.

## Blindaje de modulos fisicos

Todo archivo `modules/{codigo}/index.php` debe:

- cargarse mediante `modulo.php?m={codigo}`,
- incluir `includes/module_guard.php`,
- llamar `cb_require_module_context('{codigo}')` antes de renderizar contenido.

`modulo.php` es el unico punto que define el contexto seguro de carga. Ese contexto se define solo despues de validar sesion, codigo seguro, permiso `puede_ver` y existencia fisica del archivo.

Si un usuario abre directamente `modules/{codigo}/index.php`, el modulo debe responder HTTP 403 con mensaje generico, sin redirigir al login y sin exponer rutas, secretos ni detalles internos.

## Permisos por accion

- Listados y lecturas: requieren `puede_ver`.
- Creacion: requiere `puede_crear`.
- Edicion: requiere `puede_editar`.
- Cambio de estado: normalmente requiere `puede_editar` o `puede_eliminar`, segun la regla del modulo.
- Borrado fisico excepcional: requiere `puede_eliminar` y justificacion de negocio.

## CSRF

- Las operaciones que cambien datos deben usar CSRF local cuando la base tecnica correspondiente este disponible.
- Listados de solo lectura pueden no requerir CSRF si no modifican estado.
- Nunca usar CSRF como reemplazo de permisos; CSRF y permisos son controles distintos.

## Listados y lecturas

Obligatorio:

- Validar sesion.
- Validar `puede_ver`.
- Usar `cb_require_cliente_permission($codigoPagina, 'puede_ver')`.
- Usar `cb_require_method('GET')` o el metodo definido para el endpoint.
- Validar parametros de filtros, pagina y limite.
- Limitar paginacion a valores razonables.
- Responder JSON sin datos sensibles.

Recomendado:

- Paginacion de 10 registros por defecto.
- Filtros normalizados.
- Ordenamientos controlados por lista blanca.

## Creacion

Obligatorio:

- Validar sesion.
- Validar `puede_crear`.
- Validar metodo esperado.
- Usar `cb_require_cliente_permission($codigoPagina, 'puede_crear')`.
- Validar CSRF cuando este disponible.
- Validar campos requeridos.
- Normalizar texto segun reglas del modulo.
- Registrar auditoria de usuario externo creador.

## Edicion

Obligatorio:

- Validar sesion.
- Validar `puede_editar`.
- Validar metodo esperado.
- Usar `cb_require_cliente_permission($codigoPagina, 'puede_editar')`.
- Validar CSRF cuando este disponible.
- Confirmar que el registro existe.
- Validar cambios permitidos.
- Registrar auditoria de usuario externo editor.

## Cambio de estado

Obligatorio:

- Validar sesion.
- Validar permiso de accion definido para el modulo.
- Validar CSRF cuando este disponible.
- Usar `cb_require_cliente_permission($codigoPagina, 'puede_editar')` o `cb_require_cliente_permission($codigoPagina, 'puede_eliminar')` segun el caso.
- Evitar cambios incoherentes cuando el registro este relacionado con otros datos.
- Preferir activacion/desactivacion antes que borrado fisico en catalogos.

## Borrado fisico excepcional

El borrado fisico solo se permite cuando:

- No existe relacion con datos de negocio.
- El requerimiento lo justifica.
- El usuario tiene permiso `puede_eliminar`.
- La operacion esta protegida por CSRF.
- La respuesta informa claramente el resultado.

## Respuesta JSON

Recomendado:

```text
ok: boolean
message: string
data: object|array|null
errors: object|array|null
code: string|null
```

No incluir:

- `API_SECRET`.
- Credenciales.
- Hashes.
- SQL crudo.
- Stack traces.
- Rutas fisicas del servidor.
