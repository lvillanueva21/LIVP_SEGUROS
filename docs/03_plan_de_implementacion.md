# Plan incremental de implementación

## Estado actual

- [x] Maqueta visual inicial y login demo.
- [x] Sesión, rutas y permisos básicos.
- [x] Catálogos demo.
- [x] Notificaciones y capa visual común para modales.
- [x] Expediente flexible sin flujo obligatorio.
- [x] Contactos de gestión, Empresas y Consorcios.
- [x] Pólizas v1 dentro de Expedientes.
- [x] PDF principal opcional de póliza con almacenamiento organizado.
- [ ] Validación visual completa en navegador/Hostinger.
- [ ] Persistencia MySQL real.

## Siguiente bloque recomendado

### Cotizaciones flexibles v1

- Cotización opcional vinculada a Expediente.
- Varias alternativas dentro de una cotización.
- Fecha/hora de vencimiento Lima.
- Estado libre con aviso de vencimiento, sin bloquear.
- Sin plantillas ni constructor de ítems aún.

## Fases posteriores

1. Timeline y gestión documental general.
2. Requisitos y formatos reutilizables.
3. Pagos, vouchers, garantías y siniestros.
4. Constructor de ítems configurables.
5. Vista cliente por RUC.
6. Migración MySQL y control de permisos por página/acción.
7. OCR/PDF y QR.

## Regla de avance

No iniciar cotizaciones hasta verificar: creación de póliza, vigencia inválida, carga/reemplazo de PDF, desactivación y visualización protegida de documento.
