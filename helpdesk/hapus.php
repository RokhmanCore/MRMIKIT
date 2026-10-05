<?php
require_once __DIR__.'/../config/config.php';require_once __DIR__.'/../config/auth.php';require_login();
$id=(int)($_GET['id']??0);if(!$id)die('ID tidak valid.');
$st=$pdo->prepare("SELECT nama_file FROM helpdesk_bukti WHERE insiden_id=?");$st->execute([$id]);$dir=__DIR__.'/../uploads/helpdesk';foreach($st as $b){$f=$dir.'/'.$b['nama_file'];if(is_file($f))@unlink($f);}
$pdo->prepare("DELETE FROM helpdesk_insiden WHERE id=?")->execute([$id]);
header('Location:index.php');exit;