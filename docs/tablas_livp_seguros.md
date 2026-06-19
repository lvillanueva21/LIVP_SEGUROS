# Tablas locales de LIVP_SEGUROS

Este documento registra el historial real de tablas locales de negocio creadas, modificadas o eliminadas en LIVP_SEGUROS.

La BD local de LIVP_SEGUROS pertenece al sistema esclavo Broker Seguros y debe almacenar datos de negocio, no usuarios de login, claves, hashes, secretos ni credenciales API.

## Estado actual

No hay tablas locales de negocio confirmadas en esta fase documental.

No se registran todavia tablas como `seg_aseguradoras`, `seg_ramos` o `seg_productos`, porque aun no han sido creadas ni aprobadas mediante SQL manual.

## Plantilla obligatoria para registros futuros

Copiar esta plantilla cada vez que se cree, modifique o elimine una tabla local.

```text
Fecha:
Tabla:
Tipo de cambio: creada / modificada / eliminada
Modulo relacionado:
Proposito:

Columnas principales:
-

Relaciones:
-

Indices y unique:
-

Reglas de estado o eliminacion:
-

Motivo del cambio:
-
```

## Reglas de mantenimiento

- No registrar tablas que no existan realmente.
- No documentar inserts demo como si fueran estructura del sistema.
- Actualizar este archivo en la misma fase donde se proponga o aplique un cambio de BD local.
- Si una tabla cambia, agregar un nuevo registro historico; no borrar la historia previa.
- Si una tabla se elimina, registrar la eliminacion y el motivo.
