PIZZARRA v1 - Primera versión visual funcional
================================================

Contenido del ZIP:
- index.php
- pizzarra.css
- pizzarra.js
- upload_image.php
- logo_pizzarra.png
- favicon.png
- almacen/.htaccess

Qué hace esta versión:
- No usa base de datos.
- No tiene login.
- Usa información genérica inicial.
- Los cambios persisten solo mientras NO recargues la página.
- Permite crear cajas/proyectos en memoria.
- Permite crear, editar, eliminar y mover piezas entre columnas.
- Permite navegar por día, semana y mes.
- Permite filtrar por búsqueda, imágenes, urgentes, culminadas y extras.
- Permite subir/previsualizar imágenes.
- Si el servidor permite escritura, upload_image.php guarda imágenes en:
  almacen/año/mes/día/

Concepto implementado:
- Proyecto = Caja
- Board diario = Pizarra
- Tarea = Pieza
- Adjuntos/contenido = Extra

Estados:
- PENDIENTE  | Queso por fundir
- EN PROCESO | Bañando en salsa
- CULMINADA  | Toque final de albahaca
- PAUSADA    | Aceituna aparte

Instalación rápida:
1. Descomprime el ZIP.
2. Sube los archivos a tu servidor.
3. Abre index.php en el navegador.
4. Si tienes AdminLTE 3 en tu raíz, deja estas carpetas junto al index:
   - plugins/
   - dist/
   - adminltev32/ si tu proyecto la usa

Notas técnicas:
- Las rutas son relativas, no están amarradas a la raíz del dominio.
- index.php puede usarse directo o incluirse desde otro archivo.
- Si se incluye desde otro archivo, puedes definir antes:

  $PZ_ASSET_PREFIX = './';
  $PZ_UPLOAD_URL = './upload_image.php';
  $PZ_STANDALONE = false;
  include 'index.php';

- Summernote se activa automáticamente si existen:
  plugins/jquery/jquery.min.js
  plugins/bootstrap/js/bootstrap.bundle.min.js
  plugins/summernote/summernote-bs4.min.js
  plugins/summernote/summernote-bs4.min.css

- Si Summernote no está disponible, el editor interno sigue funcionando.
- No usa PDO todavía porque esta versión no conecta a BD.

Permisos:
- Para guardar imágenes, la carpeta almacen/ debe poder escribirse por PHP.
- En hosting compartido normalmente basta con permisos 755 para carpetas y 644 para archivos.

Seguridad básica incluida:
- upload_image.php solo acepta JPG, PNG, GIF y WEBP.
- Máximo 8 MB por imagen.
- Se valida que el archivo sea imagen real con getimagesize().
- almacen/.htaccess bloquea ejecución de scripts dentro del almacén.

