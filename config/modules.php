<?php
declare(strict_types=1);
return [
 'inicio'=>['id'=>'inicio','label'=>'Inicio','icon'=>'⌂','roles'=>['gerente','ejecutivo','cliente'],'description'=>'Panel principal con indicadores y alertas según el perfil autenticado.','scope'=>'Panel de control'],
 'reportes'=>['id'=>'reportes','label'=>'Reportes','icon'=>'▥','roles'=>['gerente'],'description'=>'Consultas globales de cartera, vencimientos, cobranzas y siniestros.','scope'=>'Gestión gerencial'],
 'usuarios'=>['id'=>'usuarios','label'=>'Usuarios','icon'=>'♙','roles'=>['gerente'],'description'=>'Administración futura de usuarios, roles, accesos y responsables.','scope'=>'Administración'],
 'clientes'=>['id'=>'clientes','label'=>'Clientes','icon'=>'♙','roles'=>['gerente','ejecutivo'],'description'=>'Gestión de empresas, consorcios y contactos de gestión.','scope'=>'Gestión operativa'],
 'expedientes'=>['id'=>'expedientes','label'=>'Expedientes','icon'=>'▧','roles'=>['gerente','ejecutivo'],'description'=>'Registro y seguimiento flexible de un proceso asegurador.','scope'=>'Núcleo operativo'],
 'cotizaciones'=>['id'=>'cotizaciones','label'=>'Cotizaciones','icon'=>'▤','roles'=>['gerente','ejecutivo'],'description'=>'Plantillas, propuestas comparativas y vista previa imprimible.','scope'=>'Núcleo comercial'],
 'seguros'=>['id'=>'seguros','label'=>'Seguros','icon'=>'▣','roles'=>['gerente','ejecutivo'],'description'=>'Gestión futura de pólizas, renovaciones y coberturas emitidas.','scope'=>'Gestión operativa'],
 'cobranzas'=>['id'=>'cobranzas','label'=>'Cobranzas','icon'=>'◉','roles'=>['gerente','ejecutivo'],'description'=>'Control futuro de pagos, cuotas, vouchers y cartera pendiente.','scope'=>'Gestión operativa'],
 'siniestros'=>['id'=>'siniestros','label'=>'Siniestros','icon'=>'⚑','roles'=>['gerente','ejecutivo'],'description'=>'Seguimiento futuro de siniestros, solicitudes, documentos y plazos.','scope'=>'Gestión operativa'],
 'catalogos'=>['id'=>'catalogos','label'=>'Catálogos','icon'=>'▤','roles'=>['gerente','ejecutivo'],'description'=>'Consulta y mantenimiento futuro de aseguradoras, tipos de seguro y estados.','scope'=>'Configuración operativa'],
 'mis-seguros'=>['id'=>'mis-seguros','label'=>'Mis Seguros','icon'=>'▣','roles'=>['cliente'],'description'=>'Consulta de pólizas, coberturas, renovaciones y documentos del cliente.','scope'=>'Autoconsulta del cliente'],
 'mis-pagos'=>['id'=>'mis-pagos','label'=>'Mis Pagos','icon'=>'◉','roles'=>['cliente'],'description'=>'Consulta de cuotas, pagos, vouchers y estados relacionados al cliente.','scope'=>'Autoconsulta del cliente'],
 'mis-siniestros'=>['id'=>'mis-siniestros','label'=>'Mis Siniestros','icon'=>'⚑','roles'=>['cliente'],'description'=>'Consulta de solicitudes y siniestros vinculados a la entidad del cliente.','scope'=>'Autoconsulta del cliente'],
 'mi-perfil'=>['id'=>'mi-perfil','label'=>'Mi Perfil','icon'=>'◌','roles'=>['cliente'],'description'=>'Consulta futura de datos de contacto, representante y preferencias.','scope'=>'Autoconsulta del cliente'],
];
