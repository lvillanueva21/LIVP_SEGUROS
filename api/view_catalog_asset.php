<?php
declare(strict_types=1);
require __DIR__.'/../config/bootstrap.php'; requireAuth();
$p=str_replace('\\','/',(string)($_GET['path']??''));if(!preg_match('#^catalogos/aseguradoras/\d{4}/\d{2}/\d{2}/[a-zA-Z0-9_-]+\.(png|jpg|webp)$#',$p)){http_response_code(404);exit;}$base=realpath(__DIR__.'/../almacen');$f=$base?realpath($base.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$p)):false;if($f===false||!str_starts_with($f,$base.DIRECTORY_SEPARATOR)||!is_file($f)){http_response_code(404);exit;}$mime=(new finfo(FILEINFO_MIME_TYPE))->file($f);if(!in_array($mime,['image/png','image/jpeg','image/webp'],true)){http_response_code(404);exit;}header('Content-Type: '.$mime);header('Content-Length: '.filesize($f));header('Cache-Control: private, max-age=3600');readfile($f);
