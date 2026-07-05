# Contactos de gestión y Expedientes v3

## Versión

- Código: `BS-20260627-164101-PET`
- Fecha y hora Perú: 27/06/2026 16:41:01 (America/Lima)
- Base remota revisada: `9b8533ac209cfd15cf96a68a11d3a32e1a782476`

## Propósito

Corregir la confusión anterior entre un usuario interno ejecutivo y la persona natural que se acerca o participa en la gestión comercial o administrativa del seguro.

## Conceptos

| Concepto | Definición |
|---|---|
| Contacto de gestión | Persona natural: solicitante, secretaria, representante, gerente o encargado administrativo. |
| Cliente | Empresa o consorcio que contrata el seguro. |
| Usuario interno | Gerente o ejecutivo de BROKER SEGUROS. |

No se debe usar un ejecutivo interno como contacto de gestión.

## Creación mínima de expediente

Obligatorio:

- contacto de gestión;
- nombre;
- descripción;
- código y fecha/hora generados automáticamente.

Opcional:

- cliente o entidad;
- situación;
- cotización;
- seguro;
- póliza;
- documentos;
- pagos.

## Comportamiento

- El cliente puede quedar pendiente de definir.
- Gerente y ejecutivo ven y trabajan sobre todos los expedientes de esta maqueta.
- Un contacto puede existir sin entidad.
- Un contacto puede relacionarse con una o varias entidades en la estructura de datos.
- La interfaz rápida permite crear el contacto con nombre, celular y un vínculo inicial opcional.
- Desde la ficha se puede completar o corregir contacto, cliente, situación, nombre y descripción.

## Migración

La nueva clave es:

```text
broker_seguros_demo_expedients_v3
```

Cuando existe caché v1 o v2:

- se conservan código, título, descripción, cliente, fechas, situación y `quotes[]`;
- `responsible_user_id` y `responsible_name` se preservan únicamente como referencia técnica `legacy_assigned_executive_*`;
- esos valores no se muestran como contacto;
- el expediente migrado puede regularizarse al abrir su ficha y elegir un contacto real.

## No incluido

- Módulo completo de Contactos.
- Módulo completo de Empresas y Consorcios.
- Pólizas.
- Cotizaciones reales.
- Tipos de seguro.
- Timeline.
- Documentos.
- Ítems configurables.
