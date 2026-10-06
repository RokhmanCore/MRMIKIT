<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();

$id=(int)($_GET['id']??0);
if($id<=0) die('ID dokumen tidak valid.');

$st=$pdo->prepare('SELECT * FROM dokumen WHERE id=?');
$st->execute([$id]);
$d=$st->fetch();
if(!$d) die('Dokumen tidak ditemukan.');

/* Ambil seluruh file versi agar tidak meninggalkan file fisik. */
$vs=$pdo->prepare('SELECT filename FROM dokumen_versi WHERE dokumen_id=?');
$vs->execute([$id]);
$files=[];
foreach($vs->fetchAll() as $v){
    if(!empty($v['filename'])) $files[]=$v['filename'];
}
if(!empty($d['filename'])) $files[]=$d['filename'];
$files=array_values(array_unique($files));

try{
    $pdo->beginTransaction();

    /* Hapus keterkaitan EP terlebih dahulu. */
    $pdo->prepare('DELETE FROM dokumen_ep WHERE dokumen_id=?')->execute([$id]);

    /* Hapus seluruh riwayat versi dokumen. */
    $pdo->prepare('DELETE FROM dokumen_versi WHERE dokumen_id=?')->execute([$id]);

    /* Hapus dokumen utama. */
    $pdo->prepare('DELETE FROM dokumen WHERE id=?')->execute([$id]);

    $pdo->commit();

    /* Hapus file fisik setelah data DB berhasil dihapus. */
    foreach($files as $filename){
        $path=__DIR__.'/../uploads/'.$filename;
        if(is_file($path)) @unlink($path);
    }

    header('Location:index.php?deleted=1');
    exit;
}catch(Throwable $e){
    if($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    die('Gagal menghapus dokumen: '.htmlspecialchars($e->getMessage()));
}
