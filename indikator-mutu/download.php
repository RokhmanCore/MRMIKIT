<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();

$id=(int)($_GET['id']??0);
$st=$pdo->prepare("SELECT * FROM mutu_bukti WHERE id=?");
$st->execute([$id]);
$b=$st->fetch();
if(!$b){ http_response_code(404); exit('Bukti tidak ditemukan.'); }

$path=__DIR__.'/../uploads/mutu-indikator/'.$b['nama_file'];
if(!is_file($path)){ http_response_code(404); exit('File fisik tidak ditemukan.'); }

$mime=$b['mime_type'] ?: 'application/octet-stream';
$downloadName=str_replace(["","
",'"'],'_',basename($b['original_name']));
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($path));
header('Content-Disposition: inline; filename="'.$downloadName.'"');
readfile($path);
exit;
?>