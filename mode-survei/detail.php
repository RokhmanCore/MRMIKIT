<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();

$kode=$_GET['kode']??'';
$st=$pdo->prepare('SELECT * FROM elemen_penilaian WHERE kode=?');
$st->execute([$kode]);
$ep=$st->fetch();
if(!$ep) die('EP tidak ditemukan');

$st=$pdo->prepare("
    SELECT er.*,
           (SELECT COUNT(*) FROM bukti_ep be WHERE be.requirement_id=er.id) AS bukti_count,
           (SELECT COUNT(*) FROM dokumen_ep de
              JOIN dokumen d ON d.id=de.dokumen_id
             WHERE de.ep_id=er.ep_id
               AND d.status_akreditasi='aktif_2026'
               AND d.tahun_aktif=2026) AS dok_count
    FROM evidence_requirement er
    WHERE er.ep_id=?
    ORDER BY er.urutan
");
$st->execute([$ep['id']]);
$reqs=$st->fetchAll();

$docs=$pdo->prepare("
    SELECT d.*, p.nama AS pic_nama
    FROM dokumen d
    JOIN dokumen_ep de ON de.dokumen_id=d.id
    LEFT JOIN pic p ON p.id=d.pic_id
    WHERE de.ep_id=?
      AND d.status_akreditasi='aktif_2026'
      AND d.tahun_aktif=2026
    ORDER BY d.updated_at DESC
");
$docs->execute([$ep['id']]);
$docs=$docs->fetchAll();

$b=$pdo->prepare("
    SELECT b.*, p.nama AS pic_nama
    FROM bukti_implementasi b
    JOIN bukti_ep be ON be.bukti_id=b.id
    LEFT JOIN pic p ON p.id=b.pic_id
    WHERE be.ep_id=?
    ORDER BY b.created_at DESC
");
$b->execute([$ep['id']]);
$b=$b->fetchAll();

$total=count($reqs);
$ready=0;
foreach($reqs as $r){
    if((int)$r['bukti_count']>0 || (int)$r['dok_count']>0) $ready++;
}
$percent=$total?round($ready/$total*100):0;

if($percent===100 && $total>0){
    $statusClass='bg-success'; $statusText='SIAP';
} elseif($percent>0){
    $statusClass='bg-warning text-dark'; $statusText='PERLU DILENGKAPI';
} else {
    $statusClass='bg-danger'; $statusText='BELUM SIAP';
}

$page_title=$ep['kode'].' Mode Survei';
require __DIR__.'/../partials/header.php';
?>

<style>
.survei-header{background:linear-gradient(135deg,#123f34,#176c54);color:#fff;border-radius:18px;padding:22px}
.progress-readiness{height:14px;border-radius:999px}
.section-card{border:0;border-radius:15px}
.req-ok{background:#effbf5}
.req-missing{background:#fff5f5}
.badge-active{background:#198754;color:#fff}
.badge-old{background:#6c757d;color:#fff}
</style>

<div class="survei-header mb-4">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <div class="small opacity-75 mb-1">MODE SURVEI LARSI · MRMIK IT</div>
            <h2 class="mb-1"><?=htmlspecialchars($ep['kode'])?></h2>
            <div><?=htmlspecialchars($ep['judul'])?></div>
        </div>
        <div class="text-end">
            <div class="display-6 fw-bold"><?=$percent?>%</div>
            <span class="badge <?=$statusClass?>"><?=$statusText?></span>
        </div>
    </div>

    <div class="mt-3">
        <div class="d-flex justify-content-between small mb-1">
            <span>Kelengkapan checklist</span>
            <span><?=$ready?> / <?=$total?> terpenuhi</span>
        </div>
        <div class="progress progress-readiness bg-light">
            <div class="progress-bar <?=$statusClass?>" style="width:<?=$percent?>%"></div>
        </div>
    </div>
</div>

<div class="alert alert-info">
    <strong>Mode survei:</strong> halaman ini digunakan untuk melihat bukti yang siap ditunjukkan kepada surveyor. Dokumen lama tidak dihitung sebagai aktif kecuali sudah diaktifkan untuk 2026.
</div>

<div class="card shadow-sm section-card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">① Checklist Bukti / Persyaratan</h5>
            <span class="badge text-bg-secondary"><?=$ready?> dari <?=$total?></span>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr><th>Kode</th><th>Persyaratan</th><th>Jenis</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach($reqs as $r):
                    $ok=((int)$r['bukti_count']>0 || (int)$r['dok_count']>0);
                ?>
                    <tr class="<?=$ok?'req-ok':'req-missing'?>">
                        <td><strong><?=htmlspecialchars($r['kode'])?></strong></td>
                        <td><?=htmlspecialchars($r['nama'])?></td>
                        <td><span class="badge text-bg-light"><?=htmlspecialchars($r['jenis'])?></span></td>
                        <td>
                            <?php if($ok): ?>
                                <span class="badge badge-active">✓ TERPENUHI</span>
                            <?php else: ?>
                                <span class="badge bg-danger">✕ BELUM ADA</span>
                            <?php endif; ?>
                            <div class="mt-2 d-flex gap-1 flex-wrap">
                                <a class="btn btn-sm btn-success" href="/MRMIKIT/dokumen/upload.php?ep_id=<?=urlencode($ep['id'])?>">
                                    + Upload Dokumen
                                </a>
                                <a class="btn btn-sm btn-outline-primary" href="/MRMIKIT/dokumen/index.php?ep_id=<?=urlencode($ep['id'])?>">
                                    Lihat Dokumen
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm section-card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">② Dokumen Aktif 2026</h5>
            <span class="badge badge-active"><?=$docs?count($docs):0?> dokumen</span>
        </div>

        <?php if(!$docs): ?>
            <div class="alert alert-danger mb-0">Belum ada dokumen yang berstatus <strong>AKTIF 2026</strong> untuk EP ini.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Dokumen</th><th>Versi</th><th>PIC</th><th>Review</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach($docs as $x): ?>
                        <tr>
                            <td>
                                <strong><?=htmlspecialchars($x['nama'])?></strong><br>
                                <span class="badge badge-active mt-1">✓ AKTIF 2026</span>
                            </td>
                            <td><?=htmlspecialchars($x['versi']??'-')?></td>
                            <td><?=htmlspecialchars($x['pic_nama']??'-')?></td>
                            <td><?=htmlspecialchars($x['tanggal_review']??'-')?></td>
                            <td><a class="btn btn-sm btn-outline-success" href="/MRMIKIT/dokumen/view.php?id=<?=$x['id']?>">Lihat</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm section-card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">③ Bukti Implementasi</h5>
            <span class="badge text-bg-primary"><?=$b?count($b):0?> bukti</span>
        </div>

        <?php if(!$b): ?>
            <div class="alert alert-warning mb-0">Belum ada bukti implementasi yang terhubung ke EP ini.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Jenis</th><th>Judul</th><th>PIC</th><th>Tanggal</th></tr></thead>
                    <tbody>
                    <?php foreach($b as $x): ?>
                        <tr>
                            <td><span class="badge text-bg-light"><?=htmlspecialchars($x['jenis'])?></span></td>
                            <td><strong><?=htmlspecialchars($x['judul'])?></strong></td>
                            <td><?=htmlspecialchars($x['pic_nama']??'-')?></td>
                            <td><?=htmlspecialchars($x['tanggal_bukti']??'-')?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="d-flex gap-2 flex-wrap">
    <a href="index.php" class="btn btn-outline-secondary">← Kembali ke Mode Survei</a>
    <a href="/MRMIKIT/dokumen/" class="btn btn-outline-success">Kelola Dokumen IT</a>
    <a href="/MRMIKIT/bukti/" class="btn btn-outline-primary">Kelola Bukti Implementasi</a>
</div>

<?php require __DIR__.'/../partials/footer.php';