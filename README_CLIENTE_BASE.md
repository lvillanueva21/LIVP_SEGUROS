# Cliente Base V1 — Servicios Externos LSISTEMAS
aaaaaaaaaaaaaaaaaaaaaaa 4 de julio
## 1) ¿Qué es?
`cliente_base/` es una plantilla para sistemas cliente que autentican contra LSISTEMAS con API central, pero mantienen su propia sesión local y sus módulos de negocio.

Este cliente:
- sí valida login contra `sistema/api/serv/login.php` de LSISTEMAS,
- sí crea sesión local del cliente,
- sí protege páginas/módulos/endpoints locales con `includes/session_guard.php`,
- no crea superadmin,
- no crea usuarios locales de login,
- no guarda claves de usuarios externos,
- no copia la lógica administrativa interna de LSISTEMAS.

## 2) Estructura base
- `index.php`: redirección a login/dashboard según sesión.
- `login.php`: formulario y autenticación server-side contra LSISTEMAS.
- `dashboard.php`: panel privado inicial.
- `modulo.php`: cargador seguro de módulos locales.
- `logout.php`: cierre de sesión local.
- `includes/`: configuración, helpers, guard, API client, menú y layouts.
- `modules/inicio/index.php`: módulo ejemplo.
- `api/ejemplo_ping.php`: endpoint local protegido.
- `assets/css/cliente.css`: estilos base del cliente.
- `assets/js/cliente.js`: interacción visual mínima.
- `assets/default/`: assets SVG default del cliente.

## 3) Instalación rápida
1. Copia `cliente_base/` en tu dominio, subdominio o subcarpeta.
2. Asegura que existan `plugins/` y `dist/` de AdminLTE 3 en una ruta compatible con `cb_url(...)`.
3. Edita `includes/config_cliente.php`.
4. Configura como mínimo:
   - `API_BASE_URL`
   - `API_LOGIN_ENDPOINT`
   - `API_CONFIG_VISUAL_ENDPOINT`
   - `API_KEY`
   - `API_SECRET`
   - `SERVICIO_CODIGO`
   - `DOMINIO_LOCAL`
   - `CLIENTE_NOMBRE`
5. En LSISTEMAS confirma:
   - servicio activo,
   - dominio autorizado activo,
   - credencial API activa,
   - usuario externo activo,
   - vínculo usuario-servicio activo.

## 4) Homologación visual
El cliente base prioriza configuración visual remota desde LSISTEMAS y usa fallback local cuando no hay respuesta remota/caché válida.

Assets fallback locales livianos:
- `assets/default/branding/logo_cliente.svg`
- `assets/default/branding/favicon.svg`
- `assets/default/login/login_fondo.svg`
- `assets/default/login/carrusel_1.svg`
- `assets/default/login/carrusel_2.svg`
- `assets/default/login/carrusel_3.svg`
- `assets/default/ui/avatar_default.svg`
- `assets/default/ui/empty_state.svg`

Puedes personalizar desde `includes/config_cliente.php`:
- `CLIENTE_FAVICON_PATH`
- `CLIENTE_LOGO_PATH`
- `CLIENTE_LOGIN_BG_PATH`
- `CLIENTE_LOGIN_CARRUSEL_ACTIVO`
- `CLIENTE_LOGIN_CARRUSEL_1_PATH`
- `CLIENTE_LOGIN_CARRUSEL_2_PATH`
- `CLIENTE_LOGIN_CARRUSEL_3_PATH`
- `CLIENTE_AVATAR_DEFAULT_PATH`
- `CLIENTE_EMPTY_STATE_PATH`
- `CLIENTE_VERSION_LABEL`
- `CLIENTE_VISUAL_REMOTO_ACTIVO`
- `CLIENTE_VISUAL_CACHE_ACTIVO`
- `CLIENTE_VISUAL_CACHE_TTL_DEFAULT`
- `CLIENTE_VISUAL_CACHE_STALE_TTL`

## 5) Seguridad obligatoria
- `API_SECRET` solo vive en PHP (`includes/config_cliente.php` y uso interno en `includes/api_client.php`).
- Nunca exponer `API_SECRET` en JS, HTML, sesión o logs.
- Nunca guardar `API_SECRET` en caché local.
- No subir `config_cliente.php` con credenciales reales a repositorios públicos.
- Mantener `includes/.htaccess` activo para bloquear acceso directo a configuración/helpers.
- Mantener `storage/.htaccess` y `storage/cache/.htaccess` activos.
- No guardar usuarios de login ni claves en BD local.
- `login.php` usa CSRF local y `session_regenerate_id(true)` al autenticar correctamente.

## 6) Sesión local y protección de rutas
- Toda página privada debe incluir `includes/session_guard.php`.
- Todo endpoint local (`api/*.php`) debe incluir `includes/session_guard.php`.
- El timeout local usa `timeout_sesion_minutos` recibido desde LSISTEMAS.

## 7) Base de datos local opcional
- `includes/conexion_cliente.php` usa PDO.
- Activar solo si `CLIENTE_DB_ACTIVA = true`.
- Es para módulos de negocio del cliente (inventario, asistencia, shop, etc.).
- No usar BD local para autenticación central.

## 8) Qué NO copiar de LSISTEMAS central
No llevar a cliente_base:
- Superadmin,
- gestión de usuarios internos,
- roles/permisos internos,
- páginas lógicas `pag_*`,
- administración de servicios externos,
- credenciales API del panel,
- módulos administrativos centrales.

## 9) Reglas para módulos futuros
- Mantener AdminLTE 3 + Bootstrap 4.
- Usar rutas relativas (sin `/assets`, `/plugins`, `/dist` a raíz).
- En tablas: botones de acción icon-only, apilados verticalmente, con `title` y `aria-label`.
- Truncar textos largos con criterio y dejar valor completo en `title` o `data-*`.
- No usar `alert()`, `confirm()` ni `prompt()`.
- Inputs de clave deben tener botón ojo (`fas fa-eye` / `fas fa-eye-slash`).

## 10) Estrategia de despliegue de assets de AdminLTE
Para evitar duplicar miles de archivos en cada proyecto:
- opción 1: copiar `plugins/` y `dist/` junto al cliente al desplegar,
- opción 2: apuntar esas rutas a una ubicación compartida si tu hosting lo permite.

El código del cliente está preparado para rutas relativas; solo ajusta configuración/rutas si cambias ubicación.

## 11) Prueba rápida
1. Abre `login.php`.
2. Inicia sesión con DNI/CE válido del servicio.
3. Verifica redirección a `dashboard.php`.
4. Abre `modulo.php?m=inicio`.
5. Abre `api/ejemplo_ping.php` con sesión activa.
6. Cierra sesión y verifica que endpoints/páginas privadas redirigen o responden 401.

## 12) Configuración visual remota pre-login
- En la versión actual, `cliente_base` ya intenta cargar configuración visual remota antes del login desde:
  - `API_BASE_URL + API_CONFIG_VISUAL_ENDPOINT`
- La llamada es server-side en PHP, nunca desde JavaScript.
- Orden real de adquisición visual:
  1. caché vigente,
  2. remoto LSISTEMAS,
  3. caché stale (si remoto falla),
  4. fallback local.
- Si remoto responde `ok`, se actualiza caché local sin secretos.
- Los assets reales de LSISTEMAS tendrán prioridad sobre placeholders locales.
- Los `SVG` locales quedarán como fallback cuando LSISTEMAS no responda o no exista recurso remoto válido.
- No se deben convertir formatos reales (`PNG`, `WebP`, `JPG`, `JPEG`, `ICO`, `SVG`).
- `API_SECRET` debe permanecer solo en backend PHP y nunca exponerse en frontend.
- Los assets remotos se referencian por URL; no se descargan ni se convierten en `cliente_base`.
- Para forzar actualización visual:
  - borrar archivos `cliente_base/storage/cache/*.json`, o
  - esperar al TTL configurado.
- Un cambio visual en LSISTEMAS puede tardar hasta el TTL en verse en cliente.
- En pruebas, puedes borrar caché manualmente para refrescar de inmediato.
- No desactivar `.htaccess` en `storage/` ni `storage/cache/`.
