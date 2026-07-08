# Control vivo del proyecto — Broker Seguros

> Este documento se actualiza con cada mejora, corrección, tabla, regla técnica o decisión relevante. Mantiene el mapa general del proyecto y no reemplaza las pruebas específicas.

## Estado actual

- Sistema: **Broker Seguros**.
- Entorno: maqueta PHP/JavaScript con datos principalmente en `localStorage`, JSON temporal y archivos en `almacen/`.
- Base visual interna: se conserva la maqueta existente.
- Autenticación real inicial: rol `desarrollo` mediante MySQL/PDO.
- Configuración de Correos V1: núcleo real para configurar, probar, enviar y auditar correos SMTP Zoho desde una cuenta global del sistema.

## Autenticación Desarrollo V1

### Funcionalidades reales

- Login real para `desarrollo` usando DNI de ocho dígitos y contraseña.
- Registro temporal abierto en `registro.php` para crear usuarios Desarrollo.
- Contraseñas con `password_hash()` y `password_verify()`.
- Consultas PDO preparadas.
- Sesión PHP con regeneración de ID al iniciar.
- CSRF en login, registro y cierre de sesión.
- Auditoría de intentos de acceso.
- Bloqueo temporal: cinco fallos dentro de 15 minutos bloquean por 15 minutos.
- Validación de usuario activo en páginas protegidas.
- Logout exclusivamente por `POST` con token CSRF.
- Protección de acceso web directo para `config/` mediante `.htaccess`.

### Compatibilidad obligatoria

- Siguen funcionando demos: Gerente, Ejecutivo, Empresa y Consorcio.
- DNI reservados para demos: `12345678` y `87654321`.
- Siguen funcionando cuentas Cliente temporales almacenadas en JSON.
- La sesión conserva `$_SESSION['livp_user']`.
- Las rutas usan `appRelativeUrl()` para funcionar en dominio, subdominio o subcarpeta.

## Corrección UI y navegación V1.2

- El logout superior se corrige mediante formulario `POST` con CSRF; no se usa enlace `GET`.
- El botón de navegación lateral ahora es solo un icono con tooltip, sin texto literal.
- El sidebar comprimido se guarda en `localStorage` y en modo comprimido muestra solo iconos.
- Los estilos y JavaScript del sidebar se cargan desde archivos dedicados con versión propia para evitar caché antigua.
- El carrusel del login ya **no abre modal al hacer clic**. Solo permite deslizar o rotar imágenes, evitando overlays que bloqueen el login.
- El login ya no pide seleccionar tipo de documento:
  - ocho dígitos: DNI;
  - once dígitos: RUC;
  - otro formato: CE.
- Se agregan páginas iniciales para Desarrollo:
  - Usuarios;
  - Sesión;
  - Configuración.
- Configuración contiene submenús:
  - Gestión de Archivos;
  - Gestión de Correos;
  - Gestión de WhatsApp.

## Configuración de Correos V1

### Objetivo

Crear el núcleo inicial de notificaciones por correo para Broker Seguros, sin decidir todavía qué módulos futuros enviarán recordatorios o alertas.

### Alcance implementado

- Nueva página protegida para Desarrollo: `configuracion_correos.php`.
- Acceso desde `Configuración -> Gestión de Correos`.
- Tres pestañas internas:
  - Resumen y prueba;
  - Configuración;
  - Historial.
- Un único correo remitente global Zoho para todo el sistema.
- Nombre visible editable. Valor sugerido por defecto: `Broker Seguros - Helmut Leiva`.
- Clave de aplicación Zoho cifrada en MySQL.
- Llave maestra de cifrado definida en `config/correo.php`.
- Host SMTP fijo en sistema: `smtppro.zoho.com`, puerto `587`, TLS/STARTTLS.
- Uno o dos destinatarios de prueba.
- Correo de copia administrativa opcional como CC visible.
- Asunto y mensaje de prueba editables.
- Botón `Probar notificaciones por correo`.
- Limpieza automática de espacios en la clave Zoho desde JavaScript y PHP.
- Icono de ojo para ver u ocultar la clave que se está escribiendo.
- Historial con asunto, mensaje enviado, remitente, destinatarios, CC, estado y error técnico.
- Métricas simples: correos generados, enviados, fallidos, parciales, destinatarios aceptados y fallidos.
- Servicio reutilizable en `services/correo_sistema.php` para que futuros módulos llamen el envío centralizado.

### Reglas importantes

- La prueba usa siempre la configuración guardada, no campos sin guardar.
- Cambiar correo remitente o clave Zoho obliga a nueva prueba.
- Si la clave ya está guardada, el input aparece vacío; dejarlo vacío conserva la clave actual.
- Si se escribe una nueva clave, reemplaza la anterior.
- El sistema registra si Zoho aceptó o rechazó el envío SMTP, pero no puede garantizar bandeja principal/spam.
- El correo de copia administrativa se incluye en pruebas si está configurado.
- Futuros módulos no deben conectarse directo a SMTP; deben usar el servicio central.

### Tablas MySQL agregadas

- `seg_correo_configuracion`
- `seg_correo_destinatarios_prueba`
- `seg_correo_envios`
- `seg_correo_envio_destinatarios`
- `seg_correo_auditoria`

## Tablas MySQL actuales

- `seg_roles`
- `seg_usuarios`
- `seg_usuario_roles`
- `seg_login_intentos`
- `seg_correo_configuracion`
- `seg_correo_destinatarios_prueba`
- `seg_correo_envios`
- `seg_correo_envio_destinatarios`
- `seg_correo_auditoria`

## Recursos del login

Carpetas controladas manualmente:

- `assets/login/default/favicon/`: un `.ico`, `.png` o `.svg`.
- `assets/login/default/logo/`: un `.png`, `.jpg`, `.jpeg`, `.webp` o `.svg`.
- `assets/login/default/carrusel/`: cero o más `.png`, `.jpg`, `.jpeg` o `.webp`.

Reglas:

- Se decide por carpeta y extensión, no por nombre fijo.
- Se usa el primer favicon/logo por orden natural.
- El carrusel se ordena de forma natural; para controlarlo conviene `01.webp`, `02.webp`, `03.webp`.
- Una carpeta vacía no muestra ese recurso.
- Las imágenes del carrusel no son botones ni abren modales.

## Preferencias de programación vigentes

- PHP actual + MySQL/MariaDB mediante PDO.
- Zona horaria `America/Lima`.
- Rutas relativas; sin URL base fija ni dependencia del dominio.
- Cambios incrementales: no reemplazar la maqueta completa para añadir una función real.
- Una entidad que pase a MySQL debe tener una única fuente oficial; evitar duplicados entre MySQL y caché.
- Entregar siempre pruebas de humo y funcionales.
- Entregar siempre un nombre de commit recomendado.
- Mantener este archivo actualizado.

## Cosas a evitar

- No copiar AdminLTE, `dist`, Tesseract ni módulos completos de LIVP_LSISTEMAS para el login.
- No cambiar roles demo ni estructura de sesión sin revisar las interfaces existentes.
- No crear Desarrollo con DNI reservados para demos.
- No eliminar `config/client_accounts.php` ni `config/demo_users.php` mientras existan demos.
- No usar GET para logout.
- No aplicar los estilos del login a páginas internas.
- No volver a usar clic de imágenes de carrusel para abrir modal sin una prueba completa de cierre, foco y navegación.
- No duplicar lógica SMTP en módulos futuros; usar `services/correo_sistema.php`.

## Próximo paso recomendado

Probar Configuración de Correos V1 en Hostinger con una cuenta Zoho real: guardar remitente, clave de aplicación, destinatarios, copia opcional y ejecutar la prueba real. Después de confirmar envío, integrar el servicio central con un primer módulo real, por ejemplo alertas de pagos o pólizas.
