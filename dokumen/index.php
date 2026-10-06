<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();

$page_title='Dokumen IT';
$ep_filter=(int)($_GET['ep_id']??0);

if($ep_filter){
    $st=$pdo->prepare("SELECT d.*, COUNT(de2.ep_id) ep_count
        FROM dokumen d
        JOIN dokumen_ep de ON de.dokumen_id=d.id AND de.ep_id=?
        LEFT JOIN dokumen_ep de2 ON de2.dokumen_id=d.id
        GROUP BY d.id ORDER BY d.updated_at DESC");
    $st->execute([$ep_filter]);
    $rows=$st->fetchAll();
    $epst=$pdo->prepare("SELECT kode,judul FROM elemen_penilaian WHERE id=?");
    $epst->execute([$ep_filter]);
    $ep_filter_data=$epst->fetch();
}else{
    $rows=$pdo->query('SELECT d.*, COUNT(de.ep_id) ep_count FROM dokumen d LEFT JOIN dokumen_ep de ON de.dokumen_id=d.id GROUP BY d.id ORDER BY d.updated_at DESC')->fetchAll();
    $ep_filter_data=null;
}
require __DIR__.'/../partials/header.php';
?><div class="d-flex justify-content-between mb-3">
<div><h2>Dokumen IT</h2>
<div class="text-muted">Regulasi, pedoman, SOP, SK, laporan dan dokumen pendukung.</div>
<?php if($ep_filter_data): ?><div class="mt-2"><span class="badge text-bg-success"><?=htmlspecialchars($ep_filter_data['kode'])?></span> <?=htmlspecialchars($ep_filter_data['judul'])?></div><?php endif; ?>
</div>
<a class="btn btn-success" href="upload.php<?= $ep_filter ? '?ep_id='.$ep_filter : '' ?>">+ Upload Dokumen</a></div><div class="card shadow-sm border-0"><div class="card-body table-responsive"><table class="table"><thead><tr><th>Nama</th><th>Kategori</th><th>Versi</th><th>Status</th><th>Review</th><th></th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=htmlspecialchars($r['nama'])?></td><td><?=htmlspecialchars($r['kategori'])?></td><td><?=htmlspecialchars($r['versi'])?></td><td><?=htmlspecialchars($r['status'])?></td><td><?=htmlspecialchars($r['tanggal_review']??'-')?></td><td class="d-flex gap-1">
<a class="btn btn-sm btn-outline-success" href="view.php?id=<?=$r['id']?>">Lihat</a>
<a class="btn btn-sm btn-outline-danger" href="delete.php?id=<?=$r['id']?>"
   onclick="return confirm('Hapus dokumen ini beserta seluruh keterkaitan EP dan versi filenya? Tindakan ini tidak dapat dibatalkan.')">Hapus</a>
</td></tr><?php endforeach;?></tbody></table></div></div><?php require __DIR__.'/../partials/footer.php';