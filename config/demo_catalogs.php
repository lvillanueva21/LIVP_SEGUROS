<?php
declare(strict_types=1);

/**
 * Catálogos demo de BROKER SEGUROS.
 *
 * Esta fuente temporal permite validar los catálogos que necesitarán los
 * expedientes, cotizaciones, pólizas, pagos y siniestros antes de diseñar
 * tablas MySQL.
 *
 * Cada elemento usa la misma estructura:
 * - id: identificador demo estable.
 * - code: código visible.
 * - name: nombre del elemento.
 * - detail: información complementaria.
 * - status: Activo o Inactivo.
 */
return [
    'aseguradoras' => [
        'id' => 'aseguradoras',
        'label' => 'Aseguradoras',
        'icon' => '▣',
        'description' => 'Empresas aseguradoras disponibles para cotizaciones y pólizas.',
        'items' => [
            ['id' => 'ase-001', 'code' => 'ASE-001', 'name' => 'Pacífico Seguros', 'detail' => 'Aseguradora general', 'status' => 'Activo'],
            ['id' => 'ase-002', 'code' => 'ASE-002', 'name' => 'Rímac Seguros', 'detail' => 'Aseguradora general', 'status' => 'Activo'],
            ['id' => 'ase-003', 'code' => 'ASE-003', 'name' => 'Mapfre Perú', 'detail' => 'Aseguradora general', 'status' => 'Activo'],
            ['id' => 'ase-004', 'code' => 'ASE-004', 'name' => 'La Positiva', 'detail' => 'Aseguradora general', 'status' => 'Activo'],
            ['id' => 'ase-005', 'code' => 'ASE-005', 'name' => 'Interseguro', 'detail' => 'Vida y seguros personales', 'status' => 'Inactivo'],
        ],
    ],
    'tipos_seguro' => [
        'id' => 'tipos_seguro',
        'label' => 'Tipos de seguro',
        'icon' => '◈',
        'description' => 'Tipos de seguro que podrán vincularse a las alternativas de una cotización y a pólizas futuras.',
        'items' => [
            ['id' => 'seg-001', 'code' => 'CAR', 'name' => 'Todo Riesgo Construcción', 'detail' => 'Construcción y montaje', 'status' => 'Activo'],
            ['id' => 'seg-002', 'code' => 'SCTR', 'name' => 'SCTR Salud y Pensión', 'detail' => 'Seguro complementario de trabajo de riesgo', 'status' => 'Activo'],
            ['id' => 'seg-003', 'code' => 'RC', 'name' => 'Responsabilidad Civil', 'detail' => 'Cobertura de responsabilidad frente a terceros', 'status' => 'Activo'],
            ['id' => 'seg-004', 'code' => 'EQC', 'name' => 'Equipo Contratista', 'detail' => 'Maquinaria y equipos de obra', 'status' => 'Activo'],
            ['id' => 'seg-005', 'code' => 'VIDA', 'name' => 'Vida Ley', 'detail' => 'Seguro obligatorio laboral', 'status' => 'Activo'],
        ],
    ],
    'monedas' => [
        'id' => 'monedas',
        'label' => 'Monedas',
        'icon' => 'S/',
        'description' => 'Monedas disponibles para primas, cuotas, pagos y reportes.',
        'items' => [
            ['id' => 'mon-001', 'code' => 'PEN', 'name' => 'Soles peruanos', 'detail' => 'Símbolo S/', 'status' => 'Activo'],
            ['id' => 'mon-002', 'code' => 'USD', 'name' => 'Dólares americanos', 'detail' => 'Símbolo US$', 'status' => 'Activo'],
            ['id' => 'mon-003', 'code' => 'EUR', 'name' => 'Euros', 'detail' => 'Símbolo €', 'status' => 'Inactivo'],
        ],
    ],
    'estados_expediente' => [
        'id' => 'estados_expediente',
        'label' => 'Situaciones de expediente',
        'icon' => '▤',
        'description' => 'Situaciones generales y flexibles de un expediente. No representan pasos obligatorios ni un flujo forzado.',
        'items' => [
            ['id' => 'exp-001', 'code' => 'ABI', 'name' => 'Abierto', 'detail' => 'Caso registrado y disponible para continuar cuando corresponda', 'status' => 'Activo'],
            ['id' => 'exp-002', 'code' => 'SEG', 'name' => 'En seguimiento', 'detail' => 'Existe una acción o coordinación en curso', 'status' => 'Activo'],
            ['id' => 'exp-003', 'code' => 'ESP', 'name' => 'En espera', 'detail' => 'A la espera de información, respuesta o decisión', 'status' => 'Activo'],
            ['id' => 'exp-004', 'code' => 'CER', 'name' => 'Cerrado', 'detail' => 'Caso finalizado sin importar si hubo cotización, póliza o pago', 'status' => 'Activo'],
            ['id' => 'exp-005', 'code' => 'CAN', 'name' => 'Cancelado', 'detail' => 'Caso desistido, descartado o anulado', 'status' => 'Activo'],
        ],
    ],
    'estados_poliza' => [
        'id' => 'estados_poliza',
        'label' => 'Estados de póliza',
        'icon' => '✓',
        'description' => 'Estados que describen la vigencia y situación comercial de una póliza.',
        'items' => [
            ['id' => 'pol-001', 'code' => 'EMI', 'name' => 'En emisión', 'detail' => 'Pendiente de emisión o confirmación', 'status' => 'Activo'],
            ['id' => 'pol-002', 'code' => 'VIG', 'name' => 'Vigente', 'detail' => 'Cobertura activa', 'status' => 'Activo'],
            ['id' => 'pol-003', 'code' => 'REN', 'name' => 'Por renovar', 'detail' => 'Vigencia próxima a terminar', 'status' => 'Activo'],
            ['id' => 'pol-004', 'code' => 'VEN', 'name' => 'Vencida', 'detail' => 'Vigencia finalizada', 'status' => 'Activo'],
            ['id' => 'pol-005', 'code' => 'CAN', 'name' => 'Cancelada', 'detail' => 'Póliza anulada o cancelada', 'status' => 'Activo'],
        ],
    ],
    'estados_pago' => [
        'id' => 'estados_pago',
        'label' => 'Estados de pago',
        'icon' => '◉',
        'description' => 'Estados para cuotas, primas, vouchers y movimientos de cobranza.',
        'items' => [
            ['id' => 'pag-001', 'code' => 'PEN', 'name' => 'Pendiente', 'detail' => 'Pago aún no registrado', 'status' => 'Activo'],
            ['id' => 'pag-002', 'code' => 'PAG', 'name' => 'Pagado', 'detail' => 'Pago validado', 'status' => 'Activo'],
            ['id' => 'pag-003', 'code' => 'VEN', 'name' => 'Vencido', 'detail' => 'Plazo de pago superado', 'status' => 'Activo'],
            ['id' => 'pag-004', 'code' => 'OBS', 'name' => 'Observado', 'detail' => 'Voucher o monto requiere revisión', 'status' => 'Activo'],
            ['id' => 'pag-005', 'code' => 'ANU', 'name' => 'Anulado', 'detail' => 'Movimiento sin vigencia', 'status' => 'Activo'],
        ],
    ],
    'estados_siniestro' => [
        'id' => 'estados_siniestro',
        'label' => 'Estados de siniestro',
        'icon' => '⚑',
        'description' => 'Estados de atención para siniestros y solicitudes relacionadas.',
        'items' => [
            ['id' => 'sin-001', 'code' => 'REG', 'name' => 'Registrado', 'detail' => 'Caso creado en el sistema', 'status' => 'Activo'],
            ['id' => 'sin-002', 'code' => 'ANA', 'name' => 'En análisis', 'detail' => 'Información en revisión', 'status' => 'Activo'],
            ['id' => 'sin-003', 'code' => 'DOC', 'name' => 'Pendiente de documentos', 'detail' => 'Falta sustento del caso', 'status' => 'Activo'],
            ['id' => 'sin-004', 'code' => 'RES', 'name' => 'Resuelto', 'detail' => 'Atención cerrada', 'status' => 'Activo'],
            ['id' => 'sin-005', 'code' => 'REC', 'name' => 'Rechazado', 'detail' => 'Solicitud no procedente', 'status' => 'Activo'],
        ],
    ],
];
