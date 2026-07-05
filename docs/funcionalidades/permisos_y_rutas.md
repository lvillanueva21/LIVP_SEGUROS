# Funcionalidad: matriz de permisos y rutas controladas

## Versión de entrega

- **Código:** `BS-20260627-1220-PET`
- **Fecha y hora Perú:** 27/06/2026 12:20:47 (America/Lima)

## Propósito

Evitar que los permisos dependan únicamente de ocultar enlaces en pantalla. Cada módulo debe validar desde PHP que el rol de la sesión tenga autorización.

## Fuente única

La matriz está en:

```text
config/modules.php
```

Cada módulo define:

- `id`: identificador estable;
- `label`: texto visible;
- `icon`: ícono del menú;
- `roles`: roles con permiso;
- `description`: propósito temporal;
- `scope`: ámbito funcional.

## Flujo de acceso

1. El usuario inicia sesión y tiene un rol en `$_SESSION['livp_user']`.
2. `modulesForRole()` genera solo los enlaces permitidos en el menú.
3. Al abrir un enlace, `modulo.php` ejecuta `requireModuleAccess()`.
4. La función valida la sesión, la existencia del módulo y el rol.
5. Si no hay permiso, redirige a `acceso_denegado.php`.
6. Si el permiso existe, muestra la ruta protegida del módulo.

## Matriz inicial

| Módulo | Gerente | Ejecutivo | Cliente |
|---|:---:|:---:|:---:|
| Inicio | Sí | Sí | Sí |
| Reportes | Sí | No | No |
| Usuarios | Sí | No | No |
| Clientes | Sí | Sí | No |
| Seguros | Sí | Sí | No |
| Cobranzas | Sí | Sí | No |
| Siniestros | Sí | Sí | No |
| Catálogos | Sí | Sí | No |
| Mis Seguros | No | No | Sí |
| Mis Pagos | No | No | Sí |
| Mis Siniestros | No | No | Sí |
| Mi Perfil | No | No | Sí |

## Pruebas esenciales

- Abrir un módulo permitido desde el menú.
- Probar una URL de módulo no permitido usando otro perfil.
- Probar un identificador inexistente.
- Confirmar que un acceso no autorizado no muestra contenido interno.
- Verificar que el menú coincide con los permisos de la matriz.
