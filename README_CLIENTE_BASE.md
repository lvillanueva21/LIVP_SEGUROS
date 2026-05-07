# Cliente Base V1 — Servicios Externos LSISTEMAS

## 1) ¿Qué es?
`cliente_base/` es una plantilla mínima para instalar un sistema cliente en otro dominio (o subdominio/subcarpeta) y validar login centralizado contra LSISTEMAS.

Este cliente:
- **sí** valida login contra `sistema/api/serv/login.php` (LSISTEMAS),
- **sí** crea sesión local propia del cliente,
- **sí** protege páginas/módulos/endpoints locales con `session_guard.php`,
- **no** crea usuarios locales de login,
- **no** guarda claves de usuarios externos,
- **no** implementa superadmin/roles/permisos locales en V1.

## 2) Estructura base
- `index.php`: redirección a login/dashboard según sesión.
- `login.php`: formulario + llamada server-side a LSISTEMAS.
- `dashboard.php`: panel privado de ejemplo.
- `modulo.php`: cargador de módulos locales por código.
- `logout.php`: cierre de sesión local.
- `includes/`: configuración, helpers, guard, API client, menú y layouts.
- `modules/inicio/index.php`: módulo ejemplo.
- `api/ejemplo_ping.php`: endpoint local protegido.
- `assets/css/cliente.css`: estilos mínimos.
- `assets/js/cliente.js`: interacción visual (botón ojo).

## 3) Instalación rápida
1. Copia la carpeta `cliente_base/` en tu dominio.
2. Copia también `plugins/` y `dist/` de AdminLTE 3 junto a `cliente_base/`, **o** ajusta rutas en layouts.
3. Edita `includes/config_cliente.php`.
4. Configura:
   - `API_BASE_URL`
   - `API_LOGIN_ENDPOINT`
   - `API_KEY`
   - `API_SECRET`
   - `SERVICIO_CODIGO`
   - `DOMINIO_LOCAL`
   - `CLIENTE_NOMBRE`
   - `CLIENTE_BASE_URL` (si aplica)
5. En LSISTEMAS, asegúrate de tener:
   - servicio activo,
   - dominio autorizado activo,
   - credencial API activa,
   - usuario externo activo,
   - vínculo usuario-servicio activo.

## 4) Configuración importante
### Seguridad
- `API_SECRET` se usa **solo en PHP** (`api_client.php`).
- Nunca enviar `API_SECRET` a JS ni imprimirlo en HTML.
- No guardar claves de usuario en sesión ni BD local.

### Sesión local
- `session_guard.php` protege páginas privadas y endpoints locales.
- Timeout por inactividad usa `timeout_sesion_minutos` recibido de LSISTEMAS.

### Base de datos local opcional
- `conexion_cliente.php` usa PDO y se activa con `CLIENTE_DB_ACTIVA = true`.
- Es para módulos propios del cliente (inventario, asistencia, etc.).
- No usarla para usuarios de login externo.

## 5) Cómo agregar módulos propios
1. Crea carpeta: `modules/mi_modulo/index.php`.
2. Agrega entrada en `includes/menu_cliente.php`.
3. Abre: `modulo.php?m=mi_modulo`.

## 6) Protección obligatoria
- Todas las páginas privadas deben incluir `includes/session_guard.php`.
- Todos los endpoints locales (`api/*.php`) deben incluir `includes/session_guard.php`.

## 7) Qué queda fuera de V1
- Heartbeat central.
- Session check central.
- Token central en `serv_api_sesiones`.
- Menú dinámico desde LSISTEMAS.
- Permisos finos por módulo cliente.

