<?php require_once __DIR__.'/../config/config.php'; require_once __DIR__.'/../config/auth.php'; require_login(); $page_title='Tambah PDCA IT'; require __DIR__.'/../partials/header.php'; ?>
<h2>Tambah Log PDCA / Continuous Improvement</h2>
<form method="post" action="save.php"><div class="card shadow-sm border-0"><div class="card-body"><div class="row g-3">
<div class="col-md-3"><label class="form-label">Tanggal Temuan *</label><input name="tanggal_temuan" type="date" class="form-control" value="<?=date('Y-m-d')?>" required></div>
<div class="col-md-3"><label class="form-label">Sumber</label><input name="sumber" class="form-control" value="Monitoring IT"></div>
<div class="col-md-6"><label class="form-label">PIC *</label><input name="pic" class="form-control" required></div>
<div class="col-12"><label class="form-label">Masalah yang Ditemukan *</label><textarea name="masalah" class="form-control" rows="3" required></textarea></div>
<div class="col-12"><label class="form-label">Analisis Penyebab</label><textarea name="analisis_penyebab" class="form-control" rows="3"></textarea></div>
<div class="col-12"><label class="form-label">Rencana Tindakan</label><textarea name="rencana_tindakan" class="form-control" rows="3"></textarea></div>
<div class="col-12"><label class="form-label">Tindakan Perbaikan</label><textarea name="tindakan_perbaikan" class="form-control" rows="3"></textarea></div>
<div class="col-md-4"><label class="form-label">Target Selesai</label><input name="target_selesai" type="date" class="form-control"></div>
<div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option>Open</option><option>In Progress</option><option>Closed</option></select></div>
<div class="col-12"><label class="form-label">Hasil Verifikasi</label><textarea name="hasil_verifikasi" class="form-control" rows="3"></textarea></div>
<div class="col-12"><label class="form-label">Bukti / Keterangan Evidence</label><textarea name="bukti" class="form-control" rows="2" placeholder="Nama file/foto/screenshot atau keterangan bukti..."></textarea></div>
</div><button class="btn btn-success mt-4">Simpan PDCA</button> <a class="btn btn-outline-secondary mt-4" href="./">Batal</a></div></div></form>
<?php require __DIR__.'/../partials/footer.php'; ?>