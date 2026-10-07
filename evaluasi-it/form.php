<?php
require_once __DIR__.'/../config/config.php'; require_once __DIR__.'/../config/auth.php'; require_login(); $page_title='Buat Evaluasi Berkala'; require __DIR__.'/../partials/header.php';
?>
<h2>Buat Evaluasi Berkala</h2><p class="text-muted">Buat satu laporan untuk setiap kuartal. Statistik dapat diambil dari monitoring IT.</p>
<form method="post" action="save.php"><div class="card shadow-sm border-0"><div class="card-body"><div class="row g-3">
<div class="col-md-3"><label class="form-label">Tahun</label><input name="tahun" type="number" class="form-control" value="<?=date('Y')?>" required></div>
<div class="col-md-3"><label class="form-label">Triwulan</label><select name="triwulan" class="form-select"><option value="1">Q1 (Jan–Mar)</option><option value="2">Q2 (Apr–Jun)</option><option value="3">Q3 (Jul–Sep)</option><option value="4">Q4 (Okt–Des)</option></select></div>
<div class="col-md-3"><label class="form-label">Tanggal Evaluasi</label><input name="tanggal_evaluasi" type="date" class="form-control" value="<?=date('Y-m-d')?>" required></div>
<div class="col-md-3"><label class="form-label">Petugas</label><input name="petugas" class="form-control" required></div>
<div class="col-12"><label class="form-label">Ringkasan</label><textarea name="ringkasan" class="form-control" rows="3" placeholder="Ringkasan hasil evaluasi..."></textarea></div>
<div class="col-12"><label class="form-label">Statistik Gangguan</label><textarea name="statistik_gangguan" class="form-control" rows="3" placeholder="Jumlah gangguan, keluhan, waktu tanggap, dan status penyelesaian..."></textarea></div>
<div class="col-12"><label class="form-label">Evaluasi Kinerja</label><textarea name="evaluasi_kinerja" class="form-control" rows="3" placeholder="Evaluasi SIMRS/RME, jaringan, dan kualitas layanan IT..."></textarea></div>
<div class="col-12"><label class="form-label">Rekomendasi</label><textarea name="rekomendasi" class="form-control" rows="3"></textarea></div>
<div class="col-12"><label class="form-label">Tindak Lanjut</label><textarea name="tindak_lanjut" class="form-control" rows="3"></textarea></div>
</div><button class="btn btn-success mt-4">Simpan Evaluasi</button> <a class="btn btn-outline-secondary mt-4" href="./">Batal</a></div></div></form>
<?php require __DIR__.'/../partials/footer.php'; ?>