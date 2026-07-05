<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';
$user=requireAuth();header('Content-Type: application/json; charset=utf-8');
function out(bool $ok,string $message,array $extra=[],int $status=200):never{http_response_code($status);echo json_encode(array_merge(['ok'=>$ok,'message'=>$message],$extra),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if((string)($user['role']??'')!=='cliente')out(false,'Solo el Cliente puede enviar comprobantes desde este portal.',[],403);
if($_SERVER['REQUEST_METHOD']!=='POST')out(false,'Método no permitido.',[],405);
if(!isset($_FILES['receipt'])||!is_array($_FILES['receipt']))out(false,'Selecciona un comprobante antes de continuar.',[],422);
$f=$_FILES['receipt'];if((int)($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)out(false,'No se pudo recibir el archivo seleccionado.',[],422);
if((int)($f['size']??0)<=0||(int)$f['size']>7*1024*1024)out(false,'El comprobante debe pesar como máximo 7 MB.',[],422);
$mime=(new finfo(FILEINFO_MIME_TYPE))->file((string)$f['tmp_name']);$ext=['application/pdf'=>'pdf','image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];if(!isset($ext[$mime]))out(false,'Formato no permitido. Usa PDF, PNG, JPG o WEBP.',[],422);
$now=new DateTimeImmutable('now',new DateTimeZone('America/Lima'));$base=realpath(__DIR__.'/../almacen');if($base===false)out(false,'No se encontró la carpeta de almacenamiento.',[],500);
$folder='comprobantes/'.$now->format('Y/m/d');$dir=$base.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$folder);if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir))out(false,'No se pudo preparar la carpeta de comprobantes.',[],500);
$ref=trim((string)preg_replace('/[^a-z0-9_-]/i','-',(string)($_POST['payment_reference']??'pago')),'-')?:'pago';$name=$ref.'-'.bin2hex(random_bytes(12)).'.'.$ext[$mime];$target=$dir.DIRECTORY_SEPARATOR.$name;if(!move_uploaded_file((string)$f['tmp_name'],$target))out(false,'No se pudo guardar el comprobante.',[],500);
$meta='owner_document='.str_replace(["\r","\n"],'',(string)($user['document']??''))."\nowner_user_id=".(int)($user['id']??0)."\nuploaded_at=".$now->format('Y-m-d H:i:s')."\n";if(file_put_contents($target.'.meta',$meta,LOCK_EX)===false){@unlink($target);out(false,'No se pudo registrar la seguridad del comprobante.',[],500);}
out(true,'Comprobante guardado correctamente.',['file'=>['relative_path'=>$folder.'/'.$name,'original_name'=>(string)($f['name']??''),'internal_name'=>$name,'mime'=>$mime,'size_bytes'=>(int)$f['size'],'uploaded_at'=>$now->format('Y-m-d H:i:s'),'uploaded_by'=>(string)($user['name']??'')]]);
