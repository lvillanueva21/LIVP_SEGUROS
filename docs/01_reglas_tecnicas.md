# Reglas técnicas

## Marca, rutas y zona horaria

1. El nombre oficial visible es **BROKER SEGUROS**.
2. Las rutas internas usan referencias relativas; no se fija dominio ni base URL.
3. PHP se configura en `America/Lima`.
4. Fechas y horas demo de JavaScript se generan con `Intl` en `America/Lima`.
5. Los campos `datetime-local` de pólizas se interpretan y guardan como fecha/hora Lima, sin conversión automática de zona del navegador.

## Versionado

1. Toda entrega actualiza `docs/00_version_actual.md` y `docs/05_historial_de_cambios.md`.
2. Formato: `BS-YYYYMMDD-HHMMSS-PET`.
3. La hora de la entrega corresponde a `America/Lima`.

## Pólizas

1. Una póliza pertenece a un expediente.
2. El expediente puede tener cero, una o varias pólizas.
3. No se exige cotización previa.
4. Para registrar una póliza el expediente debe tener Cliente o entidad definido.
5. Son obligatorios: título, tipo de seguro, aseguradora, inicio y fin de vigencia.
6. Fin de vigencia debe ser posterior al inicio; el guardado se bloquea si no se cumple.
7. Estado inicial: `En emisión`.
8. Se genera un código interno `POL-YYYY-NNNN`; no se usan códigos externos en este bloque.
9. La póliza conserva una copia histórica del cliente del expediente.
10. Suma asegurada y moneda son opcionales.
11. No se implementan beneficiarios, prima, cuotas, requisitos, formatos, garantías, endosos ni renovación relacionada.

## PDFs de póliza

1. Un PDF principal opcional por póliza.
2. La ausencia de PDF muestra una advertencia visible.
3. No existe límite impuesto por la aplicación; siguen aplicando límites físicos de PHP/Hostinger.
4. Si el servidor rechaza una carga, el sistema debe explicar el motivo.
5. El archivo se valida como PDF por extensión, firma y tipo MIME.
6. Nunca se usa el nombre original como nombre físico.
7. El archivo se guarda en `almacen/polizas/{tipo}/{año}/{mes}/{día}`.
8. Al reemplazar un PDF se sube primero el nuevo; solo luego se elimina físicamente el anterior.
9. `almacen` no permite acceso directo. El PDF se visualiza mediante `api/view_policy_pdf.php`, protegido para gerente y ejecutivo.
10. Una póliza desactivada permanece disponible a gerente y ejecutivo; queda preparada para ocultarse a clientes futuros.

## Persistencia demo

1. Los metadatos de pólizas viven en `localStorage` del navegador.
2. El PDF físico se guarda en el servidor.
3. Esta separación es temporal: antes de producción, metadatos y vínculos deben migrarse a MySQL.
