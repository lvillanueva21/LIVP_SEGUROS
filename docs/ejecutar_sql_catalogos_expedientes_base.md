# Ejecucion manual - Catalogos base para expedientes

Archivo SQL:

```text
docs/sql/2026-06-20_catalogos_expedientes_base.sql
```

## Antes de ejecutar

1. Abrir phpMyAdmin en Hostinger.
2. Seleccionar la base de datos local de LIVP_SEGUROS.
3. Hacer backup/exportacion completa de la base de datos.
4. Confirmar que existen las tablas `seg_ramos`, `seg_aseguradoras` y `seg_productos`.
5. Revisar que no se este ejecutando sobre la base de datos de LIVP_LSISTEMAS.

## Ejecucion

1. Abrir la pestana SQL de phpMyAdmin.
2. Pegar el contenido completo de `docs/sql/2026-06-20_catalogos_expedientes_base.sql`.
3. Ejecutar una sola vez.
4. No modificar el SQL para insertar datos semilla.

## Verificacion

Ejecutar estas consultas de solo lectura:

```sql
SHOW TABLES LIKE 'seg_tipos_seguro';
SHOW TABLES LIKE 'seg_estados_expediente';

SHOW COLUMNS FROM seg_tipos_seguro;
SHOW COLUMNS FROM seg_estados_expediente;

SELECT COUNT(*) AS tipos_seguro FROM seg_tipos_seguro;
SELECT COUNT(*) AS estados_expediente FROM seg_estados_expediente;

SELECT
  CONSTRAINT_NAME,
  TABLE_NAME,
  REFERENCED_TABLE_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('seg_tipos_seguro', 'seg_estados_expediente')
  AND REFERENCED_TABLE_NAME IS NOT NULL;
```

Los conteos deben ser `0` si no se crearon registros desde formularios.

## Despues de ejecutar

1. Entrar a LIVP_SEGUROS con un usuario que tenga permisos de `catalogos`.
2. Abrir `modulo.php?m=catalogos`.
3. Crear primero al menos un ramo activo si no existe.
4. Probar las pestanas `Tipos de seguro` y `Estados de expediente`.
