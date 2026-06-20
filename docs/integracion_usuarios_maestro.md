# Integracion de usuarios maestro -> LIVP_SEGUROS

## Objetivo

El modulo `usuarios` de LIVP_SEGUROS administra accesos de Broker Seguros sin escribir directamente en la base de datos de LIVP_LSISTEMAS.

La identidad, clave, rol, servicio y permisos siguen viviendo en LIVP_LSISTEMAS.

## Flujo obligatorio

1. El usuario inicia sesion en LIVP_SEGUROS.
2. LIVP_SEGUROS consulta `sistema/api/serv/login.php`.
3. El maestro devuelve usuario, rol, menu, permisos y `token_sesion_servicio`.
4. LIVP_SEGUROS guarda el token solo en `$_SESSION['cliente_auth']`.
5. El navegador llama endpoints locales `api/usuarios/`.
6. Los endpoints locales llaman al maestro desde PHP, enviando credenciales API y token servidor a servidor.

## Reglas de seguridad

- El navegador nunca recibe `API_KEY`, `API_SECRET` ni `token_sesion_servicio`.
- LIVP_SEGUROS no usa conexion directa a la BD maestra.
- Cada endpoint local exige sesion y permiso local.
- Cada endpoint maestro valida nuevamente token, actor, rol Gerente y permisos reales.
- El rol Gerente no se puede crear, editar, Activar ni Desactivar desde LIVP_SEGUROS.
- Los usuarios Gerente se listan como solo lectura con el texto: Gestionar en Luigi Sistemas.

## Endpoints locales

- `api/usuarios/contexto.php`
- `api/usuarios/listar.php`
- `api/usuarios/crear.php`
- `api/usuarios/actualizar.php`
- `api/usuarios/cambiar_estado.php`

## Endpoints del maestro

- `sistema/api/serv/usuarios/contexto.php`
- `sistema/api/serv/usuarios/listar.php`
- `sistema/api/serv/usuarios/crear.php`
- `sistema/api/serv/usuarios/actualizar.php`
- `sistema/api/serv/usuarios/cambiar_estado.php`

## Alcance de esta fase

Incluye gestion de usuarios externos asociados al servicio Broker Seguros.

No incluye recuperacion ni restablecimiento de clave.
No crea `seg_clientes`.
No mezcla cliente comercial con usuario de acceso.
