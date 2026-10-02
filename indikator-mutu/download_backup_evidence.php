<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();

$id=(int)($_GET['id']??0);
$st=$pdo->prepare("SELECT b.*,i.kode FROM mutu_backup_bukti b JOIN mutu_indikator i ON i.id=b.indikator_id WHERE b.id=?");
$st->execute([$id]);
$b=$st->fetch();
if(!$b){http_response_code(404);exit('Bukti backup tidak ditemukan.');}
$path=__DIR__.'/../uploads/mutu-indikator/backup-evidence/'.$b['nama_file'];
if(!is_file($path)){http_response_code(404);exit('File fisik bukti tidak ditemukan.');}
$mime=$b['mime_type']?:'application/octet-stream';
$name=str_replace(["\r","\n",'"'],'_',basename($b['original_name']));
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($path));
header('Content-Disposition: inline; filename="'.$name.'"');
readfile($path);
exit;
?>