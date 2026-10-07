<?php
require_once __DIR__.'/../config/config.php'; require_once __DIR__.'/../config/auth.php'; require_login();
$id=(int)($_GET['id']??0); $s=$pdo->prepare('SELECT * FROM evaluasi_it WHERE id=?'); $s->execute([$id]); $r=$s->fetch(); if(!$r) die('Data tidak ditemukan');
$page_title='Detail Evaluasi Berkala'; require __DIR__.'/../partials/header.php';
?>
<div class="d-flex justify-content-between mb-3"><div><h2>Evaluasi Berkala <?=$r['periode_label']?></h2><div class="text-muted">Tanggal <?=$r['tanggal_evaluasi']?> · <?=htmlspecialchars($r['petugas'])?></div></div><button class="btn btn-primary" onclick="window.print()">🖨 Cetak</button></div>
<div class="card shadow-sm border-0"><div class="card-body">
<?php foreach(['ringkasan'=>'Ringkasan','statistik_gangguan'=>'Statistik Gangguan','evaluasi_kinerja'=>'Evaluasi Kinerja','rekomendasi'=>'Rekomendasi','tindak_lanjut'=>'Tindak Lanjut'] as $k=>$label): ?><h5><?=$label?></h5><div class="border rounded p-3 mb-3"><?=nl2br(htmlspecialchars($r[$k]??''))?:'<span class="text-muted">Belum diisi.</span>'?></div><?php endforeach; ?>
<div class="text-end mt-5"><div>Petugas Evaluasi</div><div style="height:70px"></div><strong><?=htmlspecialchars($r['petugas'])?></strong></div>
</div></div>
<style>@media print{.btn,.navbar,.sidebar,.global-search{display:none!important}.card{box-shadow:none!important}}</style>
<?php require __DIR__.'/../partials/footer.php'; ?>