<?php
require_once __DIR__.'/../config/config.php'; require_once __DIR__.'/../config/auth.php'; require_login(); $page_title='Input Monitoring IT'; require __DIR__.'/../partials/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h2>Input Monitoring IT</h2><div class="text-muted">Checklist cepat monitoring rutin IT.</div></div><a class="btn btn-outline-secondary" href="./">Kembali</a></div>
<?php if(($_GET['error']??'')==='required'): ?><div class="alert alert-danger">Tanggal monitoring dan petugas wajib diisi.</div><?php endif; ?>
<form method="post" action="save.php" enctype="multipart/form-data" id="auditForm">
<div class="card shadow-sm border-0"><div class="card-body">
<div class="d-flex justify-content-end mb-3"><button type="button" class="btn btn-success" id="normalAll">✓ Kondisi Normal Semua</button></div>
<div class="row g-3"><div class="col-md-4"><label class="form-label">Tanggal Monitoring *</label><input name="tanggal_monitoring" type="date" class="form-control" required></div><div class="col-md-4"><label class="form-label">Periode</label><select name="periode" class="form-select"><option>Bulanan</option><option>Triwulan</option><option>Semester</option><option>Tahunan</option></select></div><div class="col-md-4"><label class="form-label">Petugas *</label><input name="petugas" class="form-control" required placeholder="Nama pemeriksa"></div></div>
<hr><h5>1. Kepatuhan Sistem</h5><div class="row g-2"><div class="col-md-4"><select name="kepatuhan_status" class="form-select normal-field"><option>Memenuhi</option><option>Tidak Memenuhi</option><option>N/A</option></select></div><div class="col-md-8"><input name="kepatuhan_catatan" class="form-control" placeholder="Isi hanya jika ada temuan"></div></div>
<hr><h5>2. Kestabilan Jaringan</h5><div class="row g-2"><div class="col-md-3"><label>LAN<select name="jaringan_lan" class="form-select normal-field"><option>Stabil</option><option>Tidak stabil</option><option>N/A</option></select></label></div><div class="col-md-3"><label>Wi-Fi<select name="jaringan_wifi" class="form-select normal-field"><option>Stabil</option><option>Tidak stabil</option><option>N/A</option></select></label></div><div class="col-md-3"><label>Internet<select name="jaringan_internet" class="form-select normal-field"><option>Stabil</option><option>Tidak stabil</option><option>N/A</option></select></label></div><div class="col-md-3"><label>Server SIMRS<select name="jaringan_server" class="form-select normal-field"><option>Stabil</option><option>Tidak stabil</option><option>N/A</option></select></label></div></div><textarea name="jaringan_catatan" class="form-control mt-2" rows="2" placeholder="Isi hanya jika ada temuan"></textarea>
<hr><h5>3. Kecepatan / Loading RME</h5><div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Modul</th><th>Hasil</th></tr></thead><tbody><?php foreach(['login'=>'Login','data_pasien'=>'Data Pasien','soap'=>'Pemeriksaan / SOAP','resep'=>'Resep','pencarian'=>'Pencarian Pasien'] as $k=>$label): ?><tr><td><?=$label?></td><td><select name="rme_<?=$k?>" class="form-select normal-field"><option>Normal</option><option>Lambat</option><option>N/A</option></select></td></tr><?php endforeach; ?></tbody></table></div><textarea name="rme_catatan" class="form-control" rows="2" placeholder="Isi hanya jika ada temuan"></textarea>
<hr><h5>4. Keluhan User</h5><div class="d-flex gap-3 mb-2"><div class="form-check"><input class="form-check-input" type="radio" name="keluhan_status" id="keluhanTidak" value="Tidak ada" checked><label class="form-check-label" for="keluhanTidak">✓ Tidak ada keluhan</label></div><div class="form-check"><input class="form-check-input" type="radio" name="keluhan_status" id="keluhanAda" value="Ada"><label class="form-check-label" for="keluhanAda">Ada keluhan</label></div></div><div id="keluhanBox" class="d-none"><textarea name="keluhan_detail" class="form-control" rows="3" placeholder="Unit, keluhan, hasil pemeriksaan dan tindak lanjut"></textarea></div>
<hr><h5>Kesimpulan &amp; Tindak Lanjut</h5><textarea name="kesimpulan" class="form-control mb-2" rows="2" placeholder="Kesimpulan (boleh dikosongkan jika normal)"></textarea><textarea name="tindak_lanjut" class="form-control" rows="2" placeholder="Tindak lanjut jika ada temuan"></textarea>
<hr><h5>Evidence / Bukti</h5><input type="file" name="evidence[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.pdf,.webp"><div class="form-text">Bisa pilih beberapa file sekaligus.</div>
<div class="mt-4"><button class="btn btn-success btn-lg">Simpan Monitoring</button></div>
</div></div></form>
<script>
(function(){
const form=document.getElementById('auditForm');
document.getElementById('normalAll').addEventListener('click',function(){
 form.querySelector('[name="kepatuhan_status"]').value='Memenuhi';
 ['jaringan_lan','jaringan_wifi','jaringan_internet','jaringan_server'].forEach(n=>form.querySelector('[name="'+n+'"]').value='Stabil');
 ['rme_login','rme_data_pasien','rme_soap','rme_resep','rme_pencarian'].forEach(n=>form.querySelector('[name="'+n+'"]').value='Normal');
 document.getElementById('keluhanTidak').checked=true;
 document.getElementById('keluhanBox').classList.add('d-none');
});
document.querySelectorAll('input[name="keluhan_status"]').forEach(r=>r.addEventListener('change',function(){
 document.getElementById('keluhanBox').classList.toggle('d-none', document.getElementById('keluhanAda').checked);
}));
const t=form.querySelector('[name="tanggal_monitoring"]');
if(t && !t.value) t.value=new Date().toISOString().slice(0,10);
})();
</script>
<?php require __DIR__.'/../partials/footer.php'; ?>