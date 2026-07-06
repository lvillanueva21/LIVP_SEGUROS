# Control vivo del proyecto — Broker Seguros

> Este documento se debe actualizar junto con toda mejora, corrección, tabla, regla o decisión relevante. No sustituye las pruebas ni los README específicos; mantiene el mapa general del sistema.

## Estado actual

- Sistema: **Broker Seguros**.
- Entorno actual: maqueta PHP/JavaScript con datos principalmente en `localStorage`, JSON temporal y archivos en `almacen/`.
- Base visual interna: se conserva la maqueta existente.
- Entrega en preparación: **Autenticación Desarrollo V1**. El código y las consultas están listos, pero esta fase queda implementada solo después de subir el pack y ejecutar manualmente las consultas SQL.

## Implementación actual: Autenticación Desarrollo V1

### Funcionalidades reales

- Login real para rol `desarrollo` mediante **DNI de 8 dígitos + contraseña**.
- Registro temporal abierto en `registro.php` para crear más usuarios Desarrollo.
- Contraseñas con `password_hash()` y `password_verify()`.
- Consultas PDO preparadas.
- Sesión PHP con regeneración de ID al iniciar sesión.
- CSRF en login, registro y cierre de sesión.
- Auditoría de intentos de acceso.
- Bloqueo temporal: 5 fallos dentro de 15 minutos bloquean por 15 minutos, controlado por documento + IP.
- Validación de usuario activo en cada página protegida para sesiones de base de datos.
- Logout por POST con token CSRF.
- Protección de acceso web directo para `config/` mediante `.htaccess`.

### Compatibilidad que no debe romperse

- Los accesos demo existentes siguen disponibles:
  - Gerente demo: DNI `12345678`.
  - Ejecutivo demo: DNI `87654321`.
  - Empresa demo: RUC `20123456789`.
  - Consorcio demo: RUC `20698765432`.
- Los DNI demo están reservados: `registro.php` no permite crear Desarrollo con ellos.
- Las cuentas Cliente temporales almacenadas en JSON siguen funcionando.
- La sesión debe conservar `$_SESSION['livp_user']`, porque la maqueta usa esa estructura.
- Las rutas deben seguir usando `appRelativeUrl()` para funcionar en dominio, subdominio o subcarpeta.

### Rol Desarrollo

- Menú permitido: `Inicio` y `Configuración`.
- `Configuración` se muestra con `modulo.php?modulo=configuracion` como página protegida sin funcionalidad aún.
- Si Desarrollo intenta abrir módulos de Gerente, Ejecutivo o Cliente por URL directa, el sistema debe enviar a `acceso_denegado.php`.
- El dashboard muestra la fuente `default` porque el identificador de sesión de Desarrollo se guarda como `development-{id}` y no choca con IDs demo.

## Tablas MySQL requeridas para esta fase (pendientes de ejecución manual)

- `seg_roles`
- `seg_usuarios`
- `seg_usuario_roles`
- `seg_login_intentos`

## Recursos visuales del login

El login y registro usan un paquete aislado inspirado en el login de LIVP_LSISTEMAS. No cargan `assets/css/app.css` de la maqueta.

Carpetas controladas manualmente:

- `assets/login/default/favicon/`: un `.ico`, `.png` o `.svg`.
- `assets/login/default/logo/`: un `.png`, `.jpg`, `.jpeg`, `.webp` o `.svg`.
- `assets/login/default/carrusel/`: cero o más `.png`, `.jpg`, `.jpeg` o `.webp`.

Reglas:

- El sistema decide por carpeta y extensión, no por nombre fijo.
- Se usa el primer favicon/logo por orden natural.
- El carrusel ordena de forma natural; para definir el orden conviene `01.webp`, `02.webp`, `03.webp`.
- Si las carpetas están vacías, ese recurso no se muestra.
- Si no hay carrusel, no queda una columna visual vacía.

## Preferencias de programación vigentes

- PHP actual + MySQL/MariaDB con PDO.
- Zona horaria `America/Lima`.
- Rutas relativas, sin URL base fija ni dependencia del dominio.
- Cambios incrementales: no reemplazar toda la maqueta para implementar una funcionalidad real.
- Una entidad o módulo debe tener una fuente oficial de datos cuando se convierta a MySQL; evitar mezclar datos nuevos MySQL con duplicados de caché para la misma entidad.
- Entregar siempre pruebas de humo y funcionales antes de avanzar.
- Mantener este archivo actualizado en cada entrega.

## Cosas a evitar

- No copiar AdminLTE, `dist`, Tesseract ni módulos completos de LIVP_LSISTEMAS para el login.
- No cambiar los roles demo ni los nombres de sesión actuales sin revisar las interfaces existentes.
- No crear un usuario Desarrollo con DNI de los demos reservados.
- No eliminar `config/client_accounts.php` ni `config/demo_users.php` mientras existan accesos demo y cuentas Cliente temporales.
- No usar GET para logout.
- No aplicar estilos del login a las páginas internas de Broker Seguros.

## Próximos pasos sugeridos

1. Ejecutar las cuatro consultas SQL de autenticación en phpMyAdmin.
2. Completar `config/database.php` con la conexión real.
3. Subir este pack, probar demos, registrar Desarrollo y probar restricciones de menú.
4. Implementar posteriormente la primera pantalla real dentro de Configuración para administrar usuarios Desarrollo o parámetros de seguridad.
