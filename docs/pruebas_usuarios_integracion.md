# Pruebas de integracion de usuarios

## Preparacion

1. Crear la pagina logica `usuarios` en LIVP_LSISTEMAS.
2. Asignar permisos al rol Gerente.
3. Iniciar sesion en LIVP_SEGUROS como Gerente.

## Pruebas funcionales

- Entrar a `modulo.php?m=usuarios`.
- Confirmar que se ve el titulo Usuarios.
- Confirmar que se listan usuarios del servicio Broker Seguros.
- Confirmar que los roles asignables no incluyen Gerente.
- Crear usuario Ejecutivo.
- Crear usuario Cliente.
- Confirmar que un rol futuro activo aparece en el selector sin cambiar codigo.
- Editar nombres, apellidos, documento y rol de un usuario no Gerente.
- Desactivar un acceso no Gerente.
- Activar un acceso no Gerente.
- Confirmar que un Gerente aparece como solo lectura con: Gestionar en Luigi Sistemas.

## Pruebas negativas

- Intentar crear usuario sin rol.
- Intentar crear usuario con documento duplicado.
- Intentar usar una clave menor a 8 caracteres.
- Intentar acceder con un usuario sin permiso `usuarios`.
- Intentar abrir endpoint local sin sesion.
- Intentar POST sin CSRF.
- Cerrar sesion e intentar usar la pantalla.

## Verificaciones de seguridad

- El navegador no debe mostrar `API_KEY`.
- El navegador no debe mostrar `API_SECRET`.
- El navegador no debe mostrar `token_sesion_servicio`.
- LIVP_SEGUROS no debe tener consultas SQL contra tablas `serv_*`.
- El cambio de estado afecta el vinculo usuario-servicio, no la identidad global.
