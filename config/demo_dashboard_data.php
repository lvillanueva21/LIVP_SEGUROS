<?php
declare(strict_types=1);

/**
 * Datos temporales del dashboard.
 *
 * Esta fuente reemplazará progresivamente a MySQL cuando las pantallas,
 * indicadores y reglas de negocio hayan sido validados en la maqueta.
 */
return [
    'default' => [
        'summary' => 'No se encontró información demo específica para este perfil.',
        'metrics' => [],
        'alerts' => [],
        'primary_table' => [
            'eyebrow' => 'INFORMACIÓN',
            'title' => 'Sin datos configurados',
            'description' => 'Este perfil todavía no tiene datos demo definidos.',
            'columns' => [],
            'rows' => [],
        ],
        'secondary_table' => [
            'eyebrow' => 'SEGUIMIENTO',
            'title' => 'Sin información adicional',
            'description' => 'No hay acciones configuradas para mostrar.',
            'columns' => [],
            'rows' => [],
        ],
    ],

    1 => [
        'summary' => 'Revisa la cartera general, los vencimientos cercanos y las alertas que requieren decisión de gerencia.',
        'metrics' => [
            ['label' => 'Pólizas activas', 'value' => '184', 'note' => 'Cartera vigente', 'icon' => '▣', 'tone' => 'primary'],
            ['label' => 'Por vencer', 'value' => '12', 'note' => 'Próximos 30 días', 'icon' => '◷', 'tone' => 'warning'],
            ['label' => 'Cobranza pendiente', 'value' => 'S/ 48,250', 'note' => '17 operaciones', 'icon' => '◉', 'tone' => 'warning'],
            ['label' => 'Siniestros abiertos', 'value' => '5', 'note' => '2 requieren seguimiento', 'icon' => '⚑', 'tone' => 'danger'],
        ],
        'alerts' => [
            [
                'level' => 'warning',
                'title' => '12 pólizas requieren gestión de renovación',
                'description' => 'Cinco vencen durante los próximos siete días.',
                'meta' => 'Prioridad alta',
            ],
            [
                'level' => 'danger',
                'title' => 'Dos siniestros superan el plazo de seguimiento interno',
                'description' => 'Se requiere confirmar la respuesta de la aseguradora.',
                'meta' => 'Revisar hoy',
            ],
            [
                'level' => 'success',
                'title' => 'Cobranza del mes avanza dentro de la meta',
                'description' => 'Se registró el 78% del objetivo proyectado.',
                'meta' => 'Estado favorable',
            ],
        ],
        'primary_table' => [
            'eyebrow' => 'CARTERA GENERAL',
            'title' => 'Cartera por aseguradora',
            'description' => 'Resumen demo de la cartera vigente administrada por BROKER SEGUROS.',
            'columns' => ['Aseguradora', 'Pólizas', 'Prima anual', 'Vencen en 30 días'],
            'rows' => [
                ['Pacífico Seguros', '68', 'S/ 352,400', '4'],
                ['Rímac Seguros', '51', 'S/ 286,100', '3'],
                ['Mapfre Perú', '39', 'S/ 198,700', '2'],
                ['La Positiva', '26', 'S/ 126,900', '3'],
            ],
        ],
        'secondary_table' => [
            'eyebrow' => 'SEGUIMIENTO EJECUTIVO',
            'title' => 'Gestiones que requieren revisión',
            'description' => 'Actividades relevantes para validar la distribución de carga y decisiones.',
            'columns' => ['Responsable', 'Gestión', 'Vencimiento', 'Estado'],
            'rows' => [
                ['María Torres', 'Renovación de póliza CAR', '30/06/2026', 'En seguimiento'],
                ['Jorge Ramírez', 'Cobranza de cuota pendiente', '01/07/2026', 'Por contactar'],
                ['María Torres', 'Documento de siniestro', '02/07/2026', 'Observado'],
                ['Ana Pérez', 'Emisión de endoso', '03/07/2026', 'En proceso'],
            ],
        ],
    ],

    2 => [
        'summary' => 'Prioriza tus renovaciones, pagos por gestionar y tareas asignadas para hoy.',
        'metrics' => [
            ['label' => 'Clientes asignados', 'value' => '38', 'note' => 'Cartera a cargo', 'icon' => '♙', 'tone' => 'primary'],
            ['label' => 'Renovaciones', 'value' => '6', 'note' => 'Próximos 30 días', 'icon' => '◷', 'tone' => 'warning'],
            ['label' => 'Tareas para hoy', 'value' => '4', 'note' => '2 de prioridad alta', 'icon' => '✓', 'tone' => 'danger'],
            ['label' => 'Cobranza por gestionar', 'value' => 'S/ 11,850', 'note' => '6 operaciones', 'icon' => '◉', 'tone' => 'warning'],
        ],
        'alerts' => [
            [
                'level' => 'danger',
                'title' => 'Renovación de Constructora Norte vence en siete días',
                'description' => 'Falta confirmar propuesta y condiciones con el cliente.',
                'meta' => 'Atender primero',
            ],
            [
                'level' => 'warning',
                'title' => 'Dos vouchers continúan pendientes de validación',
                'description' => 'Revisa que los comprobantes correspondan a la póliza correcta.',
                'meta' => 'Validar hoy',
            ],
            [
                'level' => 'primary',
                'title' => 'Nueva solicitud asignada',
                'description' => 'El cliente Ingeniería Andina solicitó una cotización de equipo contratista.',
                'meta' => 'Recibida hoy',
            ],
        ],
        'primary_table' => [
            'eyebrow' => 'MI CARTERA',
            'title' => 'Clientes con atención prioritaria',
            'description' => 'Clientes demo que requieren gestión durante los próximos días.',
            'columns' => ['Cliente', 'Gestión', 'Fecha límite', 'Prioridad'],
            'rows' => [
                ['Constructora Norte S.A.C.', 'Renovación CAR', '30/06/2026', 'Alta'],
                ['Comercial Valle Norte E.I.R.L.', 'Cobranza de cuota', '01/07/2026', 'Alta'],
                ['Transportes El Sol S.A.C.', 'Entrega de endoso', '03/07/2026', 'Media'],
                ['Grupo Andino S.R.L.', 'Solicitud de cotización', '04/07/2026', 'Media'],
            ],
        ],
        'secondary_table' => [
            'eyebrow' => 'AGENDA OPERATIVA',
            'title' => 'Tareas de hoy',
            'description' => 'Lista demo para validar el bloque de seguimiento operativo.',
            'columns' => ['Hora', 'Tarea', 'Cliente', 'Estado'],
            'rows' => [
                ['09:30', 'Llamar por renovación', 'Constructora Norte S.A.C.', 'Pendiente'],
                ['11:00', 'Validar voucher', 'Comercial Valle Norte E.I.R.L.', 'Pendiente'],
                ['15:00', 'Enviar propuesta', 'Ingeniería Andina S.A.C.', 'Programada'],
                ['16:30', 'Actualizar solicitud', 'Transportes El Sol S.A.C.', 'Programada'],
            ],
        ],
    ],

    3 => [
        'summary' => 'Consulta el estado de tus seguros, los próximos vencimientos y las acciones que debes realizar.',
        'metrics' => [
            ['label' => 'Pólizas activas', 'value' => '4', 'note' => 'Coberturas vigentes', 'icon' => '▣', 'tone' => 'primary'],
            ['label' => 'Próximo vencimiento', 'value' => '1', 'note' => 'Dentro de 30 días', 'icon' => '◷', 'tone' => 'warning'],
            ['label' => 'Pago pendiente', 'value' => 'S/ 2,480', 'note' => 'Cuota de julio', 'icon' => '◉', 'tone' => 'warning'],
            ['label' => 'Solicitudes activas', 'value' => '1', 'note' => 'En atención', 'icon' => '⚑', 'tone' => 'primary'],
        ],
        'alerts' => [
            [
                'level' => 'warning',
                'title' => 'Tu póliza de equipos contratistas vence pronto',
                'description' => 'La vigencia termina el 18/07/2026. Revisa la propuesta de renovación.',
                'meta' => 'Vence en 21 días',
            ],
            [
                'level' => 'warning',
                'title' => 'Tienes una cuota pendiente',
                'description' => 'Registra el pago o adjunta el voucher correspondiente cuando esté disponible.',
                'meta' => 'Monto: S/ 2,480',
            ],
            [
                'level' => 'primary',
                'title' => 'Solicitud en seguimiento',
                'description' => 'La consulta sobre la póliza CAR está siendo revisada por tu ejecutivo.',
                'meta' => 'Actualizada hoy',
            ],
        ],
        'primary_table' => [
            'eyebrow' => 'MIS SEGUROS',
            'title' => 'Pólizas vigentes',
            'description' => 'Información demo de pólizas asociadas a Constructora Norte S.A.C.',
            'columns' => ['Seguro', 'Aseguradora', 'Vigencia', 'Estado'],
            'rows' => [
                ['Todo Riesgo CAR', 'Pacífico Seguros', '18/07/2026', 'Activa'],
                ['Responsabilidad Civil', 'Rímac Seguros', '31/12/2026', 'Activa'],
                ['SCTR Salud y Pensión', 'La Positiva', '30/09/2026', 'Activa'],
                ['Equipo contratista', 'Mapfre Perú', '18/07/2026', 'Por renovar'],
            ],
        ],
        'secondary_table' => [
            'eyebrow' => 'ACCIONES DISPONIBLES',
            'title' => 'Seguimiento de pagos y solicitudes',
            'description' => 'Bloque demo que luego se conectará con pagos, documentos y solicitudes reales.',
            'columns' => ['Tipo', 'Detalle', 'Fecha', 'Estado'],
            'rows' => [
                ['Pago', 'Cuota de póliza CAR', '05/07/2026', 'Pendiente'],
                ['Solicitud', 'Consulta de cobertura CAR', '26/06/2026', 'En atención'],
                ['Documento', 'Constancia SCTR', '20/06/2026', 'Disponible'],
                ['Renovación', 'Equipo contratista', '18/07/2026', 'Por revisar'],
            ],
        ],
    ],

    4 => [
        'summary' => 'Revisa la información consolidada de las empresas participantes, sus coberturas y vencimientos cercanos.',
        'metrics' => [
            ['label' => 'Pólizas consolidadas', 'value' => '7', 'note' => 'Coberturas vigentes', 'icon' => '▣', 'tone' => 'primary'],
            ['label' => 'Empresas participantes', 'value' => '2', 'note' => 'Con información disponible', 'icon' => '♙', 'tone' => 'primary'],
            ['label' => 'Vencimientos cercanos', 'value' => '2', 'note' => 'Próximos 30 días', 'icon' => '◷', 'tone' => 'warning'],
            ['label' => 'Pagos pendientes', 'value' => 'S/ 5,760', 'note' => 'Dos operaciones', 'icon' => '◉', 'tone' => 'warning'],
        ],
        'alerts' => [
            [
                'level' => 'warning',
                'title' => 'Constructora Norte tiene una póliza por renovar',
                'description' => 'Equipo contratista vence el 18/07/2026.',
                'meta' => 'Vence en 21 días',
            ],
            [
                'level' => 'warning',
                'title' => 'Ingeniería Andina tiene una cuota pendiente',
                'description' => 'Se encuentra pendiente el comprobante de pago de junio.',
                'meta' => 'Monto: S/ 3,280',
            ],
            [
                'level' => 'primary',
                'title' => 'Documentación consolidada disponible',
                'description' => 'Las constancias SCTR de ambas empresas ya están registradas.',
                'meta' => 'Última actualización hoy',
            ],
        ],
        'primary_table' => [
            'eyebrow' => 'PÓLIZAS POR EMPRESA',
            'title' => 'Coberturas consolidadas del consorcio',
            'description' => 'Información demo para validar el manejo de consorcios y empresas participantes.',
            'columns' => ['Empresa', 'Seguro', 'Aseguradora', 'Vigencia'],
            'rows' => [
                ['Constructora Norte S.A.C.', 'Todo Riesgo CAR', 'Pacífico Seguros', '18/07/2026'],
                ['Constructora Norte S.A.C.', 'Responsabilidad Civil', 'Rímac Seguros', '31/12/2026'],
                ['Ingeniería Andina S.A.C.', 'SCTR Salud y Pensión', 'La Positiva', '30/09/2026'],
                ['Ingeniería Andina S.A.C.', 'Equipo contratista', 'Mapfre Perú', '25/08/2026'],
            ],
        ],
        'secondary_table' => [
            'eyebrow' => 'GESTIONES CONSOLIDADAS',
            'title' => 'Acciones pendientes por empresa',
            'description' => 'Resumen demo de gestiones que deben quedar claramente separadas por participante.',
            'columns' => ['Empresa', 'Gestión', 'Fecha límite', 'Estado'],
            'rows' => [
                ['Constructora Norte S.A.C.', 'Renovación de equipo contratista', '18/07/2026', 'Por revisar'],
                ['Ingeniería Andina S.A.C.', 'Validación de pago', '30/06/2026', 'Pendiente'],
                ['Constructora Norte S.A.C.', 'Descarga de constancia SCTR', 'Disponible', 'Completada'],
                ['Ingeniería Andina S.A.C.', 'Solicitud de endoso', '04/07/2026', 'En atención'],
            ],
        ],
        'company_summary' => [
            [
                'name' => 'Constructora Norte S.A.C.',
                'ruc' => 'RUC 20123456789',
                'policies' => '4 pólizas vigentes',
                'pending' => '1 renovación pendiente',
            ],
            [
                'name' => 'Ingeniería Andina S.A.C.',
                'ruc' => 'RUC 20456789123',
                'policies' => '3 pólizas vigentes',
                'pending' => '1 pago por validar',
            ],
        ],
    ],
];
