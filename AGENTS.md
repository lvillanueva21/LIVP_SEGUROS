# AGENTS.md - LIVP_SEGUROS

Este repositorio corresponde al sistema esclavo LIVP_SEGUROS, dedicado exclusivamente a Broker Seguros.

El objetivo de este documento es fijar reglas operativas para cualquier cambio futuro en el sistema, especialmente para modulos de negocio locales.

## Prioridad documental

Cuando exista conflicto o duda, respetar este orden:

1. Reglas de plataforma o sistema.
2. `LIVP_SEGUROS/AGENTS.md`.
3. `LIVP_SEGUROS/README_CLIENTE_BASE.md`.
4. `LIVP_SEGUROS/docs/control_visual_luigisistemas.md`.
5. Documentacion especifica del modulo.
6. Requerimiento puntual del desarrollador.

Un requerimiento puntual del desarrollador puede ampliar funcionalidades, campos, endpoints, tablas o diseno visual, pero no puede omitir seguridad, sesion, permisos, PDO, validacion backend, trazabilidad ni documentacion de BD.

## A. Reglas no negociables

### Arquitectura maestro/esclavo

- El maestro LIVP_LSISTEMAS controla identidad, servicios, usuarios externos, roles, paginas logicas, permisos, menu dinamico, login remoto y configuracion visual.
- LIVP_SEGUROS administra solamente datos reales de negocio de Broker Seguros.
- Regla central: el maestro decide quien puede hacer algo; LIVP_SEGUROS guarda que se hizo.
- Los modulos de negocio no deben consultar al maestro en cada accion normal.
- No replicar en LIVP_SEGUROS usuarios de login, claves, hashes, `API_SECRET`, credenciales API, `lsis_usuarios`, `lsis_roles`, `lsis_usuario_roles`, `pag_paginas`, `pag_permisos` ni `pag_roles_permisos`.
- Cada pagina logica creada en el maestro debe coincidir con `LIVP_SEGUROS/modules/{codigo_pagina}/index.php`.

### Seguridad

- Usar PDO y prepared statements.
- No usar `mysqli` en nuevas implementaciones.
- Toda pagina privada y todo endpoint privado local debe incluir o pasar por `includes/session_guard.php`.
- Todo modulo debe validar sesion local y permiso antes de mostrar o ejecutar acciones.
- Toda accion de negocio debe validar permiso especifico: `puede_ver`, `puede_crear`, `puede_editar` o `puede_eliminar`.
- CSRF es obligatorio para cambios de datos cuando la infraestructura correspondiente exista.
- Prohibido exponer `API_SECRET` en HTML, JavaScript, sesion, logs, respuestas JSON o mensajes de error.
- Prohibido incluir rutas dinamicas sin lista blanca o sin validacion estricta contra path traversal.
- Prohibido acceso directo a archivos de modulos fuera del router autorizado.
- No guardar secretos reales en nuevos documentos o respuestas.

### Base de datos

- No ejecutar SQL automaticamente.
- No crear migraciones automaticas.
- No crear archivos `.sql`.
- Todo SQL se entrega por chat para ejecucion manual en phpMyAdmin.
- Toda tabla local de negocio debe documentarse en `LIVP_SEGUROS/docs/tablas_livp_seguros.md`.
- La BD local de LIVP_SEGUROS es exclusiva de Broker Seguros; no usar `id_servicio` salvo decision explicita de convertir el sistema a multicliente.
- No crear tablas locales de autenticacion.
- La hora estandar de aplicacion y auditoria es Lima, Peru: PHP `America/Lima` y sesion MySQL/MariaDB `-05:00`.

### UX y visual

- Mantener AdminLTE 3 + Bootstrap 4.
- Mantener rutas relativas.
- Mantener menu dinamico, layout actual y configuracion visual remota con fallback.
- No usar `alert()`, `confirm()` ni `prompt()`.
- Usar modales, toasts/notificaciones, loading y estados vacios coherentes.
- Las tablas deben paginar en 10 registros cuando aplique.
- Los botones de acciones en tablas deben ser icon-only, apilados verticalmente, con `title` y `aria-label`.
- No tocar `pizzarra/`, `dist/`, `plugins/` ni `includes/config_cliente.php` sin justificacion explicita.
- La plantilla tecnica oficial de modulos vive en `modules/_plantilla/`.

## B. Reglas flexibles

- Se permiten nuevos campos, tablas, endpoints, pestanas, cards, dashboards, flujos, archivos JS/CSS y disenos especiales cuando el requerimiento lo justifique.
- Los modulos pueden tener endpoints adicionales, subcarpetas, scripts o estilos propios si respetan la arquitectura global.
- Los requerimientos explicitos del desarrollador pueden ampliar o cambiar la estructura funcional y visual.
- La flexibilidad no permite omitir sesion, permisos, PDO, validacion backend, CSRF cuando corresponda, auditoria ni documentacion.
- Si una necesidad nueva obliga a tocar seguridad central, login, router, configuracion o permisos, detenerse y explicar el riesgo antes de implementar.

## Checklist minimo antes de cerrar cambios

- Sesion validada.
- Permiso correcto validado.
- Endpoint protegido si existe.
- PDO y prepared statements usados si hay BD.
- CSRF aplicado en cambios de datos cuando exista la infraestructura.
- Tabla local documentada si hubo BD.
- Sin secretos expuestos.
- Sin rutas absolutas innecesarias.
- Sin dialogos nativos.
- Sin cambios injustificados en `pizzarra/`, `dist/`, `plugins/` o `includes/config_cliente.php`.
