# Funcionalidad: Catálogos demo básicos

## Versión de entrega

- **Código:** `BS-20260627-122826-PET`
- **Fecha y hora Perú:** 27/06/2026 12:28:26 (America/Lima)

## Propósito

Validar los datos maestros mínimos que usarán los módulos de expedientes, pólizas, pagos y siniestros antes de crear tablas definitivas.

## Ruta y permisos

- Ruta: `catalogos.php`
- Roles permitidos: gerente y ejecutivo.
- Gerente: consulta y edición temporal.
- Ejecutivo: solo consulta.
- Cliente empresa y consorcio: sin acceso.

## Grupos iniciales

1. Aseguradoras.
2. Tipos de seguro.
3. Monedas.
4. Estados de expediente.
5. Estados de póliza.
6. Estados de pago.
7. Estados de siniestro.

## Campos validados

Todo elemento demo tiene:

- código;
- nombre;
- detalle;
- estado de disponibilidad: Activo o Inactivo.

Estos campos representan la estructura mínima que luego se revisará para la base de datos.

## Persistencia temporal

- La fuente inicial está en `config/demo_catalogs.php`.
- Los cambios realizados por gerente se guardan con `localStorage`.
- No se escriben cambios en PHP ni se guardan en servidor.
- Restaurar demo elimina únicamente la modificación local del navegador.

## Pruebas esenciales

- Probar visualización con gerente y ejecutivo.
- Crear, editar, activar, desactivar y restaurar como gerente.
- Validar que ejecutivo no vea acciones de edición.
- Validar acceso denegado para perfiles cliente.
- Probar el diseño móvil y la tabla horizontal.
