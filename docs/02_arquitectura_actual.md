# Arquitectura actual

```text
Expedientes (localStorage)
└── policies[]
    ├── datos de póliza
    ├── copia histórica del cliente
    ├── estado y desactivación
    └── metadatos del PDF principal

assets/js/cache-migrations.js
├── migra entidades, contactos, expedientes y políticas
└── carga assets/js/polizas.js solo en Expedientes

assets/js/polizas.js
├── sección Pólizas dentro de la ficha de expediente
├── validación de vigencia
├── barra de carga por XMLHttpRequest
├── reemplazo seguro de PDF
└── desactivación con motivo

api/upload_policy_pdf.php
└── recibe y valida PDF, crea carpetas y guarda archivo físico

api/delete_policy_pdf.php
└── elimina el PDF anterior solo dentro de almacen/polizas

api/view_policy_pdf.php
└── entrega PDF protegido a gerente y ejecutivo

almacen/
└── polizas/{tipo}/{año}/{mes}/{día}/{codigo}_{aleatorio}.pdf
```

## Nota de transición

Actualmente el PDF está en servidor y la relación archivo-póliza está en `localStorage`. Por ello no se comparte entre navegadores ni equipos hasta migrar a MySQL.
