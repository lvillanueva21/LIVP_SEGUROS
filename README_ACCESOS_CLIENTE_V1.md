# Accesos Cliente V1

**Versión:** `BS-20260627-234916-CLIENTACCOUNTSV1`  
**Entrega:** 27/06/2026 23:49:16 (America/Lima)

## Qué hace

Gerencia puede crear una cuenta de acceso para cualquier Empresa o Consorcio que exista en Clientes y tenga RUC propio de 11 dígitos.

Cada cuenta Cliente queda vinculada a una sola entidad y puede ver:

- Sus expedientes publicados.
- Sus pólizas.
- Sus pagos.
- Sus siniestros.

No puede ver datos de otras entidades.

## Proceso de uso

1. Gerente entra a **Usuarios**.
2. Selecciona la Empresa o Consorcio.
3. Asigna una contraseña de mínimo 8 caracteres.
4. Guarda el acceso.
5. Pulsa **Publicar cartera actual**.
6. El Cliente ingresa en otro navegador con su RUC y contraseña.

## Límites del prototipo

- Las cuentas se guardan en `almacen/client_accounts.json`.
- La cartera publicada se guarda en `almacen/demo_portal_state.json`.
- Esto permite una demo multi-navegador básica, pero no sustituye MySQL, auditoría, recuperación de contraseña ni gestión de múltiples usuarios por entidad.
- Los consorcios sin RUC propio no pueden tener una cuenta de portal independiente todavía.

## Sincronización de acciones Cliente

Cuando un Cliente reporte un siniestro o suba un comprobante, la acción se guarda en la cartera publicada. Gerente o Ejecutivo deben abrir **Expedientes** y pulsar **Actualizar cartera publicada** antes de revisar el Timeline en su sesión.
