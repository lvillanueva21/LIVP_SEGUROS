<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';
$user=requireAuth();$role=(string)($user['role']??'');if(!in_array($role,['gerente','ejecutivo','cliente'],true)){http_response_code(403);exit('No tienes permiso para ver este comprobante.');}
$path=str_replace('\\','/',trim((string)($_GET['path']??'')));if(!preg_match('#^comprobantes/\d{4}/\d{2}/\d{2}/[A-Za-z0-9_-]+\.(pdf|png|jpg|webp)$#',$path)){http_response_code(404);exit('Comprobante no disponible.');}
$base=realpath(__DIR__.'/../almacen');$file=$base?realpath($base.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path)):false;if($file===false||$base===false||!str_starts_with($file,$base.DIRECTORY_SEPARATOR)||!is_file($file)){http_response_code(404);exit('Comprobante no disponible.');}
if($role==='cliente'){$meta=is_file($file.'.meta')?parse_ini_file($file.'.meta',false,INI_SCANNER_RAW):false;$owner=is_array($meta)?(string)($meta['owner_document']??''):'';if($owner===''||!hash_equals($owner,(string)($user['document']??''))){http_response_code(403);exit('No tienes permiso para ver este comprobante.');}}
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file);if(!in_array($mime,['application/pdf','image/png','image/jpeg','image/webp'],true)){http_response_code(404);exit('Comprobante no disponible.');}
header('Content-Type: '.$mime);header('Content-Length: '.(string)filesize($file));header('Content-Disposition: inline; filename="'.rawurlencode(basename($file)).'"');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store, max-age=0');readfile($file);
