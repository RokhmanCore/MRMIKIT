<?php
require_once __DIR__.'/../config/config.php'; require_once __DIR__.'/../config/auth.php'; require_login();
$id=(int)($_GET['id']??0); if(!$id) die('ID tidak valid');
$s=$pdo->prepare('SELECT * FROM audit_monitoring_it WHERE id=?'); $s->execute([$id]); $row=$s->fetch(); if(!$row) die('Data tidak ditemukan');
$e=$pdo->prepare('SELECT * FROM audit_monitoring_it_evidence WHERE audit_id=? ORDER BY id DESC'); $e->execute([$id]); $files=$e->fetchAll();
$page_title='Detail Monitoring IT'; require __DIR__.'/../partials/header.php';
$keluhanAda=($row['keluhan_status']==='Ada');
$bulan= date('F Y', strtotime($row['tanggal_monitoring']));
?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h2>Detail Monitoring IT #<?=$row['id']?></h2><div class="text-muted">Monitoring Bulanan <?=$bulan?> · <?=htmlspecialchars($row['petugas'])?></div></div><div><button onclick="window.print()" class="btn btn-primary">🖨 Cetak</button> <a class="btn btn-outline-secondary" href="./">Kembali</a></div></div>
<?php if(isset($_GET['saved'])): ?><div class="alert alert-success">Monitoring berhasil disimpan.</div><?php endif; ?>
<div class="card shadow-sm border-0 mb-3"><div class="card-body">
<h5>Ringkasan Monitoring</h5><div class="alert alert-light border">Monitoring IT periode <strong><?=$bulan?></strong> telah dilakukan oleh <strong><?=htmlspecialchars($row['petugas'])?></strong>.</div>
<h5>1. Kepatuhan Sistem</h5><p><span class="badge text-bg-success"><?=htmlspecialchars($row['kepatuhan_status'])?></span><?php if(trim($row['kepatuhan_catatan']??'')): ?> — <?=nl2br(htmlspecialchars($row['kepatuhan_catatan']))?><?php endif; ?></p>
<h5>2. Kestabilan Jaringan</h5><div class="row g-2"><div class="col-md-3">LAN: <b><?=htmlspecialchars($row['jaringan_lan'])?></b></div><div class="col-md-3">Wi-Fi: <b><?=htmlspecialchars($row['jaringan_wifi'])?></b></div><div class="col-md-3">Internet: <b><?=htmlspecialchars($row['jaringan_internet'])?></b></div><div class="col-md-3">Server SIMRS: <b><?=htmlspecialchars($row['jaringan_server'])?></b></div></div><?php if(trim($row['jaringan_catatan']??'')): ?><p class="mt-2"><?=nl2br(htmlspecialchars($row['jaringan_catatan']))?></p><?php endif; ?>
<h5 class="mt-4">3. Kecepatan / Loading RME</h5><div class="table-responsive"><table class="table table-bordered table-sm"><thead><tr><th>Modul</th><th>Hasil</th></tr></thead><tbody><?php foreach(['rme_login'=>'Login','rme_data_pasien'=>'Data Pasien','rme_soap'=>'Pemeriksaan / SOAP','rme_resep'=>'Resep','rme_pencarian'=>'Pencarian Pasien'] as $k=>$label): ?><tr><td><?=$label?></td><td><b><?=htmlspecialchars($row[$k])?></b></td></tr><?php endforeach; ?></tbody></table></div><?php if(trim($row['rme_catatan']??'')): ?><p><?=nl2br(htmlspecialchars($row['rme_catatan']))?></p><?php endif; ?>
<h5 class="mt-4">4. Keluhan User</h5><?php if($keluhanAda): ?><div class="alert alert-warning"><strong>Ada keluhan.</strong><br><?=nl2br(htmlspecialchars($row['keluhan_detail']??''))?></div><?php else: ?><div class="alert alert-success">Tidak ditemukan keluhan pengguna terkait SIMRS, RME, jaringan, maupun layanan IT selama periode monitoring.</div><?php endif; ?>
<h5>Kesimpulan</h5><p><?=trim($row['kesimpulan']??'')?nl2br(htmlspecialchars($row['kesimpulan'])):'Monitoring berjalan sesuai checklist dan tidak ada temuan yang dicatat.'?></p>
<h5>Tindak Lanjut</h5><p><?=trim($row['tindak_lanjut']??'')?nl2br(htmlspecialchars($row['tindak_lanjut'])):'Tidak ada tindak lanjut khusus.'?></p>
</div></div>
<div class="card shadow-sm border-0"><div class="card-body"><h5>Evidence / Bukti (<?=count($files)?> file)</h5><?php if(!$files): ?><div class="text-muted">Belum ada evidence.</div><?php else: ?><div class="list-group"><?php foreach($files as $f): ?><a class="list-group-item list-group-item-action" href="<?=htmlspecialchars($f['file_path'])?>" target="_blank">📎 <?=htmlspecialchars($f['nama_file'])?></a><?php endforeach; ?></div><?php endif; ?></div></div>
<style>@media print{.btn,.alert-success{display:none!important}.card{box-shadow:none!important;border:1px solid #ddd!important}.container,.container-fluid{width:100%!important;max-width:none!important}}</style>
<?php require __DIR__.'/../partials/footer.php'; ?>