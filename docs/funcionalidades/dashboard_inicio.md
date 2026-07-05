# Funcionalidad: dashboard de Inicio con datos demo

## Propósito

Validar qué información necesita ver cada perfil al ingresar a **BROKER SEGUROS**, sin usar todavía una base de datos.

## Fuente de datos

Los datos se definen en:

```text
config/demo_dashboard_data.php
```

Cada bloque se identifica con el `id` del usuario demo. El controlador `dashboard.php` carga solo el bloque que corresponde a la sesión autenticada.

## Contenido por perfil

### Gerente

- Resumen global de pólizas, vencimientos, cobranza y siniestros.
- Alertas gerenciales.
- Cartera por aseguradora.
- Seguimiento de gestiones ejecutivas.

### Ejecutivo

- Clientes asignados, renovaciones, tareas y cobranza.
- Clientes de atención prioritaria.
- Agenda operativa diaria.

### Empresa cliente

- Pólizas vigentes.
- Próximo vencimiento, pago pendiente y solicitud en atención.
- Acciones disponibles sobre seguros, pagos, documentos y renovación.

### Consorcio

- Resumen consolidado de pólizas, empresas, vencimientos y pagos.
- Tabla de pólizas separada por empresa participante.
- Tarjetas resumen por empresa.
- Gestiones consolidadas separadas por participante.

## Usabilidad y navegación

- El menú lateral permanece visible en escritorio aun cuando Inicio tenga mucho contenido.
- El menú lateral puede desplazarse internamente si no entra completo en el alto de pantalla.
- El usuario puede cerrar sesión desde el menú lateral o desde la barra superior.
- En móvil, la barra superior conserva el ícono de salida aunque se oculte su etiqueta visual.

## Reglas

1. Los datos son demostrativos y no representan información real.
2. Un perfil solo debe ver su bloque de datos.
3. Las tablas deben mantenerse en HTML normal; en pantallas pequeñas pueden usar desplazamiento horizontal.
4. Para cambiar cifras o filas demo, se modifica `config/demo_dashboard_data.php`, no `dashboard.php`.
5. Al incorporar MySQL, se debe conservar la estructura de datos que recibe el dashboard para reducir cambios en la vista.

## Pruebas esenciales

- Acceder con los cuatro perfiles y verificar que el contenido sea distinto.
- Confirmar que el consorcio identifique sus dos empresas.
- Desplazarse hasta el final de un Inicio extenso: el cierre de sesión debe seguir disponible en la barra lateral y en la barra superior.
- Recargar la página y verificar que no haya errores.
- Probar la visualización móvil.
- Navegar a un módulo en construcción y volver a Inicio.
