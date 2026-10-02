<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();

$page_title='Mode Survei';

$eps=$pdo->query("
    SELECT ep.*,
           COUNT(DISTINCT de.dokumen_id) AS dokumen_count,
           COUNT(DISTINCT CASE
               WHEN d.status_akreditasi='aktif_2026' AND d.tahun_aktif=2026
               THEN d.id END) AS aktif_count,
           COUNT(DISTINCT be.bukti_id) AS bukti_count
    FROM elemen_penilaian ep
    LEFT JOIN dokumen_ep de ON de.ep_id=ep.id
    LEFT JOIN dokumen d ON d.id=de.dokumen_id
    LEFT JOIN bukti_ep be ON be.ep_id=ep.id
    GROUP BY ep.id
    ORDER BY ep.urutan
")->fetchAll();

require __DIR__.'/../partials/header.php';
?>

<style>
.survei-hero{background:linear-gradient(135deg,#123f34,#176c54);color:#fff;border-radius:18px;padding:22px;margin-bottom:20px}
.ep-card{border:1px solid #dfe8e4;border-left:6px solid #adb5bd;border-radius:12px;background:#fff;margin-bottom:10px;transition:.15s}
.ep-card:hover{transform:translateX(3px);box-shadow:0 5px 16px rgba(0,0,0,.07)}
.ep-card-active{border-left-color:#198754;background:#f0fff7}
.ep-card-review{border-left-color:#ffc107;background:#fffaf0}
.ep-card-empty{border-left-color:#dc3545;background:#fff7f7}
.ep-status{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:700;white-space:nowrap}
.status-active{background:#198754;color:#fff}
.status-review{background:#ffc107;color:#212529}
.status-empty{background:#dc3545;color:#fff}
.ep-meta{font-size:12px;color:#6c757d}
</style>

<div class="survei-hero">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div>
            <h2 class="mb-1">Mode Survei LARSI</h2>
            <div>Checklist kesiapan MRMIK IT berdasarkan EP dan bukti yang sudah dikumpulkan.</div>
        </div>
        <div class="text-end">
            <div class="small opacity-75">EP MRMIK IT</div>
            <div class="fs-3 fw-bold"><?=count($eps)?></div>
        </div>
    </div>
</div>

<div class="alert alert-info border-0">
    <strong>Cara menggunakan:</strong> pilih EP untuk melihat checklist persyaratan, dokumen aktif 2026, bukti implementasi, PIC, dan tanggal review.
</div>

<div class="d-flex gap-2 flex-wrap mb-3">
    <span class="ep-status status-active">● Aktif 2026</span>
    <span class="ep-status status-review">● Ada bahan, perlu review</span>
    <span class="ep-status status-empty">● Belum ada bukti</span>
</div>

<?php foreach($eps as $ep):
    $aktif=(int)$ep['aktif_count'];
    $dok=(int)$ep['dokumen_count'];
    $bukti=(int)$ep['bukti_count'];

    if($aktif>0){
        $card='ep-card-active'; $status='status-active'; $label='✓ AKTIF 2026';
    } elseif($dok>0 || $bukti>0){
        $card='ep-card-review'; $status='status-review'; $label='⚠ PERLU REVIEW';
    } else {
        $card='ep-card-empty'; $status='status-empty'; $label='✕ BELUM ADA BUKTI';
    }
?>
<a href="detail.php?kode=<?=urlencode($ep['kode'])?>" class="text-decoration-none text-dark">
    <div class="ep-card <?=$card?>">
        <div class="p-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div>
                <div class="fs-6">
                    <strong><?=htmlspecialchars($ep['kode'])?></strong>
                    <span class="text-muted">—</span>
                    <?=htmlspecialchars($ep['judul'])?>
                </div>
                <div class="ep-meta mt-2">
                    📄 <?=$dok?> dokumen &nbsp; · &nbsp; 🧾 <?=$bukti?> bukti implementasi
                </div>
            </div>
            <span class="ep-status <?=$status?>"><?=$label?></span>
        </div>
    </div>
</a>
<?php endforeach; ?>

<?php require __DIR__.'/../partials/footer.php';