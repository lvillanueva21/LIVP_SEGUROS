# Preparacion de pagina logica Usuarios

Crear manualmente en LIVP_LSISTEMAS:

## Pagina logica

- Servicio: Broker Seguros.
- Codigo pagina: `usuarios`.
- Titulo menu: Usuarios.
- Titulo pagina: Usuarios.
- Icono sugerido: `fas fa-users`.
- Estado: Activo.
- Visible en menu: si.
- Nivel: 1.

## Permisos

Asignar al rol Gerente:

- `puede_ver`
- `puede_crear`
- `puede_editar`
- `puede_eliminar`

No otorgar estos permisos a Ejecutivo ni Cliente en esta primera fase.

## Resultado esperado

El modulo debe aparecer en el sidebar de LIVP_SEGUROS para Gerente y cargar desde:

```text
modulo.php?m=usuarios
```
