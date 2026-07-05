# Clientes v1: Empresas, Consorcios y Contactos

## Versión

`BS-20260627-173744-PET` — 27/06/2026 17:37:44 (America/Lima).

## Pantalla

El módulo único **Clientes** tiene tres pestañas: Empresas, Consorcios y Contactos de gestión. Gerente y Ejecutivo tienen el mismo alcance demo.

## Empresas

- Crear con razón social o RUC.
- RUC completo, válido y no repetido cuando se registre.
- Datos parciales permitidos.
- Desactivar con motivo, sin borrado definitivo.

## Consorcios

- Mínimo dos empresas activas.
- Con RUC propio: RUC válido y único.
- Sin RUC propio: una y solo una empresa como operador tributario.
- La empresa operadora determina el RUC principal calculado.
- Se conserva participación retirada como antecedente.

## Contactos

- Nombre y celular obligatorios.
- Vínculos múltiples con etiqueta por entidad.
- Un vínculo puede marcarse como principal para esa entidad.
- Se pueden crear desde Clientes y desde Expedientes.

## Integración con Expedientes

El selector de cliente usa Empresas y Consorcios activos. Cuando el contacto elegido tiene una sola relación activa, el sistema sugiere la entidad sin obligarla.

## No incluido

Usuarios cliente, cotizaciones, pólizas, documentos, requisitos, pagos, timeline, constructor de ítems, SUNAT y MySQL.
