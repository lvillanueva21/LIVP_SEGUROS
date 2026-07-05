# Funcionalidad: autenticación y sesión demo

## Propósito

Permitir que la maqueta identifique al perfil que ingresó y conserve su contexto durante la navegación sin usar base de datos.

## Perfiles disponibles

| Perfil | Documento | Tipo de cuenta |
|---|---|---|
| Gerente | DNI | Persona |
| Ejecutivo | DNI | Persona |
| Empresa cliente | RUC | Empresa |
| Consorcio | RUC | Consorcio |

## Flujo

1. El usuario selecciona tipo de documento, número y contraseña.
2. `index.php` compara las credenciales con `config/demo_users.php`.
3. Si la validación es correcta, `createUserSession()` elimina el hash de contraseña, regenera el identificador de sesión y guarda el contexto.
4. El sistema redirige a `dashboard.php`.
5. `dashboard.php` exige autenticación mediante `requireAuth()`.
6. `logout.php` elimina la sesión y redirige al login.

## Reglas

- No se guarda información de negocio de manera permanente.
- No se debe acceder al dashboard sin sesión.
- Después del cierre de sesión, una recarga o acceso directo a una página privada debe volver al login.
- Un consorcio debe conservar la lista de sus empresas participantes.
