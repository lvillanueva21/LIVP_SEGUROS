# Convencion de base de datos local

Este documento define reglas para tablas locales de negocio en LIVP_SEGUROS.

## Obligatorio

### Alcance

- La BD local es exclusiva de Broker Seguros.
- La BD local guarda datos de negocio, no datos de autenticacion.
- No crear usuarios locales de login.
- No guardar claves, hashes, `API_SECRET` ni credenciales API.
- No copiar tablas internas del maestro.
- No usar `id_servicio` en tablas locales salvo cambio explicito a arquitectura multicliente.

### Nombres

- Toda tabla local de negocio debe usar prefijo `seg_`.
- La llave primaria debe llamarse `id`.
- Los nombres deben ser claros, estables y relacionados al dominio de seguros.

### Charset y collation

- Usar charset/collation compatible con la BD real donde se ejecutara el SQL.
- Antes de proponer SQL definitivo, revisar la BD o dump disponible.
- No asumir charset si no hay evidencia.

### Estado y eliminacion

- Usar `estado` como mecanismo principal de activacion o desactivacion.
- En catalogos relacionados a otros datos, no usar borrado fisico como flujo normal.
- Si un registro ya esta relacionado con polizas, cuotas, cobranzas u otros datos futuros, debe preferirse desactivacion o borrado logico.

### Auditoria estandar

Toda tabla local de negocio debe considerar:

```text
creado_por_usuario_externo_id
actualizado_por_usuario_externo_id
creado_en
actualizado_en
```

Los IDs de auditoria vienen de la sesion autorizada por el maestro. No implican crear tabla local de usuarios.

## Recomendado

- Crear foreign keys para relaciones reales entre tablas locales.
- Crear indices en claves foraneas.
- Crear indices en campos usados para busqueda.
- Crear restricciones unique para evitar duplicados de negocio.
- Usar transacciones cuando una operacion afecte mas de una tabla.
- Mantener codigos o nombres normalizados cuando ayuden a evitar duplicados.
- Evitar columnas genericas sin proposito claro.

## Flexible

- Un modulo puede requerir campos adicionales si el flujo de negocio lo exige.
- Un modulo puede requerir tablas auxiliares si simplifican reglas reales.
- Un requerimiento del desarrollador puede ampliar columnas, relaciones o indices.

La flexibilidad no elimina la obligacion de documentar el cambio en `LIVP_SEGUROS/docs/tablas_livp_seguros.md`.

## Documentacion obligatoria

Cada creacion, modificacion o eliminacion de tabla local debe registrarse en:

```text
LIVP_SEGUROS/docs/tablas_livp_seguros.md
```

El registro debe incluir:

- fecha,
- tabla,
- tipo de cambio,
- modulo relacionado,
- proposito,
- columnas principales,
- relaciones,
- indices y unique,
- reglas de estado o eliminacion,
- motivo del cambio.
