<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();
$page_title='Downtime';
$rows=$pdo->query("SELECT d.*, p.nama AS pic_nama FROM downtime d LEFT JOIN pic p ON p.id=d.pic_id ORDER BY d.mulai DESC")->fetchAll();
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function durasi($mulai,$selesai){
 if(!$selesai) return 'Belum selesai';
 $a=new DateTime($mulai); $b=new DateTime($selesai); $s=max(0,$b->getTimestamp()-$a->getTimestamp());
 $jam=intdiv($s,3600); $men=intdiv($s%3600,60);
 return ($jam?$jam.' jam ':'').$men.' menit';
}
require __DIR__.'/../partials/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
 <div><h2>Downtime SIMRS</h2><div class="text-muted">Catat setiap kejadian. Semua kejadian dalam bulan yang sama akan dijumlahkan otomatis untuk IM-IT-01.</div></div>
 <a class="btn btn-success" href="tambah.php">+ Catat Downtime</a>
</div>
<div class="alert alert-info small"><strong>Alur:</strong> catat mulai–selesai → durasi dihitung otomatis → tentukan dampak, penyebab, tindakan, PIC dan sumber data → lampirkan bukti bila ada → IM-IT-01 menjumlahkan seluruh downtime per bulan.</div>
<div class="card shadow-sm border-0"><div class="card-body table-responsive">
<table class="table table-hover align-middle">
<thead><tr><th>Mulai</th><th>Selesai</th><th>Durasi</th><th>Dampak</th><th>Penyebab</th><th>PIC</th><th>Sumber</th><th>Bukti</th></tr></thead>
<tbody>
<?php foreach($rows as $r): ?>
<tr>
<td><?=h($r['mulai'])?></td><td><?=h($r['selesai']??'-')?></td><td><span class="badge text-bg-light"><?=h(durasi($r['mulai'],$r['selesai']))?></span></td>
<td><?=h($r['dampak']??'-')?></td><td><?=h($r['penyebab']??'-')?></td><td><?=h($r['pic_nama']??'-')?></td><td><?=h($r['sumber_data']??'-')?></td>
<td><?php if(!empty($r['bukti_filename'])): ?><a href="../uploads/downtime/<?=rawurlencode(basename($r['bukti_filename']))?>" target="_blank">Lihat</a><?php else: ?>-<?php endif; ?></td>
</tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">Belum ada catatan downtime.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__.'/../partials/footer.php';