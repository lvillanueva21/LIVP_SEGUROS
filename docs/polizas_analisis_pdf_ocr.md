# Analisis de PDF de polizas y base OCR

## Objetivo

Esta implementacion agrega una ventana grande dentro de `Expedientes > Polizas` para registrar una poliza desde un PDF:

- PDF visible a la izquierda.
- Formulario editable a la derecha.
- Boton de extraccion de datos.
- Guardado final usando el flujo existente de `seg_polizas`, `seg_archivos` y `seg_archivos_vinculos`.

No crea una pagina logica nueva en LIVP_LSISTEMAS.

## Archivos principales

- `modules/expedientes/index.php`
  - Agrega el boton `Analizar PDF`.
  - Agrega el modal `modalPolizaAnalisisExp`.
  - Muestra el PDF con `URL.createObjectURL(...)` en un `iframe`.
  - Permite editar los datos antes de guardar.

- `api/expedientes/poliza_analisis.php`
  - Endpoint protegido por sesion, permisos y CSRF.
  - Recibe un PDF temporal.
  - Intenta extraer texto simple con `pdftotext` si el servidor lo tiene disponible.
  - Aplica reglas de extraccion sobre el texto.
  - Devuelve campos sugeridos y mensajes de estado.

- `api/expedientes/polizas.php`
  - Mantiene el guardado normal de polizas.
  - Si recibe datos de extraccion y existe `seg_poliza_extracciones`, guarda evidencia auxiliar.
  - Si la tabla auxiliar aun no existe, el guardado normal de poliza no se rompe.

- `data/polizas_extraccion_conocimiento.json`
  - Base editable de terminos, campos prioritarios, aseguradoras comunes y estrategia.

- `plugins/tesseract/`
  - Copia local de Tesseract.js tomada desde `E:\github_clones\livp_extractor\EXTRACTOR_DE_SEGUROS\assets\tesseract`.

## Estados de extraccion

- `pendiente`: aun no se analizo o se guardo manualmente.
- `extraida`: se extrajo texto util y se aplicaron reglas.
- `revisada`: el usuario reviso/edito los datos antes de guardar.
- `guardada`: reservado para flujos futuros.
- `fallida`: reservado para errores persistidos futuros.
- `ocr_requerido`: el PDF parece escaneado o sin texto util.

## Metodos de extraccion

- `manual`: datos llenados por el usuario.
- `texto_pdf`: texto extraido del PDF con herramienta del servidor.
- `ocr`: reservado para OCR completo.
- `mixto`: reservado para texto + OCR.
- `ocr_pendiente`: el sistema detecto que haria falta OCR, pero no hubo motor completo disponible.

## OCR actual

La carpeta OCR local esta copiada, pero el OCR completo de PDF escaneado requiere convertir paginas PDF a imagen antes de enviarlas a Tesseract.

El demo `EXTRACTOR_DE_SEGUROS` resolvia esa conversion con PDF.js desde CDN. En LIVP_SEGUROS no se incorporo PDF.js por CDN ni se instalo dependencia nueva. Por eso:

- PDF con texto seleccionable: puede analizarse si el servidor tiene `pdftotext`.
- PDF escaneado: queda detectado como `ocr_requerido` / `ocr_pendiente`.
- El usuario puede llenar el formulario manualmente mirando el PDF y guardar la poliza.

Para completar OCR real en una fase futura hay dos caminos:

1. Agregar PDF.js local en `plugins/pdfjs/` y usar `plugins/tesseract/` en navegador.
2. Usar OCR de servidor si Hostinger o el entorno disponible ofrece `pdftotext`, `tesseract` y motor de renderizado PDF a imagen.

## Campos que se intentan extraer

- Tipo de documento emitido.
- Numero de documento.
- Aseguradora.
- Fecha de emision.
- Vigencia inicio.
- Vigencia fin.
- Moneda.
- Suma asegurada.
- Prima comercial.
- IGV.
- Prima total.
- Beneficiario.
- Contratante y RUC como candidatos auxiliares.

## Guardado

El PDF principal se guarda igual que antes:

- Carpeta: `almacen/polizas/documentos/YYYY/MM/DD/`
- `codigo_uso = poliza_documento_principal`
- `entidad_tipo = poliza`
- `slot = documento_principal`

La poliza se guarda en `seg_polizas`.

La evidencia de extraccion, si la tabla existe, se guarda en `seg_poliza_extracciones`.

## Reglas de seguridad

- No se expone ruta fisica del PDF.
- No se usa BLOB ni Base64.
- La subida final usa `almacen_core.php`.
- Cambios protegidos con CSRF.
- Acciones protegidas por permisos de Expedientes.
- No se modifica LIVP_LSISTEMAS.
