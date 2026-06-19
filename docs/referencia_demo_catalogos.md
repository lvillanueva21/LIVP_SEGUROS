# Referencia demo para Catalogos

Este documento registra la revision funcional del repositorio `BROKERSEGUROS_DEMO` usada para construir el primer modulo real `catalogos` en LIVP_SEGUROS.

## Rutas reales revisadas

- `BROKERSEGUROS_DEMO/includes/layout.php`
- `BROKERSEGUROS_DEMO/modules/catalogos/index.php`
- `BROKERSEGUROS_DEMO/ajax/catalogos.php`
- `BROKERSEGUROS_DEMO/modules/polizas/index.php`
- `BROKERSEGUROS_DEMO/modules/cobranzas/index.php`

## Patrones adoptados

- Encabezado claro dentro del layout existente.
- Tarjetas KPI para lectura rapida del estado del modulo.
- Buscador, filtro por estado y boton Limpiar.
- Pestañas para separar Aseguradoras, Ramos y Productos / Planes.
- Tabla por catalogo con acciones en cada fila.
- Estados vacios cuando no existan registros.
- Modales para crear y editar.
- Mensajes tipo toast para resultado de acciones.
- Activar e inactivar registros sin borrado fisico.

## Patrones descartados

- Autenticacion del demo.
- Sesiones del demo.
- Datos simulados o mock-data.
- Almacenamiento en `$_SESSION`.
- `BROKERSEGUROS_DEMO/ajax/catalogos.php`.
- CSS o layout propio del demo.
- Roles hardcodeados.
- Acciones genericas basadas en `catalog_key`.

Estos elementos se descartaron porque LIVP_SEGUROS debe conservar su arquitectura real: sesion local recibida del maestro, permisos del maestro, endpoints locales protegidos, PDO, CSRF y respuestas JSON uniformes.

## Por que Catalogos es el primer modulo

Catalogos prepara datos base de negocio antes de construir polizas, cobranzas, siniestros y reportes. La relacion esperada es:

```text
Aseguradora + Ramo + Producto/Plan
-> Poliza
-> Cuotas y Cobranzas
-> Reportes
```

Por eso `seg_aseguradoras`, `seg_ramos` y `seg_productos` son el primer bloque funcional local de Broker Seguros.
