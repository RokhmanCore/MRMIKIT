<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();
$page_title='Input Monitoring IT';
require __DIR__.'/../partials/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h2>Input Monitoring IT</h2><div class="text-muted">Isi satu form untuk satu periode monitoring.</div></div>
  <a class="btn btn-outline-secondary" href="./">Kembali</a>
</div>
<div class="card shadow-sm border-0">
<div class="card-body">
<form>
  <div class="row g-3 mb-3">
    <div class="col-md-4"><label class="form-label">Tanggal Monitoring</label><input type="date" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">Periode</label><select class="form-select"><option>Bulanan</option><option>Triwulan</option></select></div>
    <div class="col-md-4"><label class="form-label">Petugas</label><input class="form-control" placeholder="Nama pemeriksa"></div>
  </div>
  <h5 class="mt-4">1. Kepatuhan Sistem</h5>
  <div class="row g-3"><div class="col-md-8"><label class="form-label">SIMRS/RME digunakan sesuai alur dan kewenangan</label></div><div class="col-md-4"><select class="form-select"><option>Memenuhi</option><option>Tidak Memenuhi</option><option>N/A</option></select></div></div>
  <textarea class="form-control mt-2" rows="2" placeholder="Catatan / hasil pemeriksaan"></textarea>
  <h5 class="mt-4">2. Kestabilan Jaringan</h5>
  <div class="row g-2">
    <div class="col-md-3"><label>LAN<select class="form-select"><option>Stabil</option><option>Tidak stabil</option></select></label></div>
    <div class="col-md-3"><label>Wi-Fi<select class="form-select"><option>Stabil</option><option>Tidak stabil</option></select></label></div>
    <div class="col-md-3"><label>Internet<select class="form-select"><option>Stabil</option><option>Tidak stabil</option></select></label></div>
    <div class="col-md-3"><label>Server SIMRS<select class="form-select"><option>Stabil</option><option>Tidak stabil</option></select></label></div>
  </div>
  <textarea class="form-control mt-2" rows="2" placeholder="Catatan / bukti monitoring jaringan"></textarea>
  <h5 class="mt-4">3. Kecepatan / Loading RME</h5>
  <div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Modul</th><th>Hasil</th><th>Catatan</th></tr></thead><tbody>
    <tr><td>Login</td><td><select class="form-select"><option>Normal</option><option>Lambat</option></select></td><td><input class="form-control"></td></tr>
    <tr><td>Data Pasien</td><td><select class="form-select"><option>Normal</option><option>Lambat</option></select></td><td><input class="form-control"></td></tr>
    <tr><td>Pemeriksaan / SOAP</td><td><select class="form-select"><option>Normal</option><option>Lambat</option></select></td><td><input class="form-control"></td></tr>
    <tr><td>Resep</td><td><select class="form-select"><option>Normal</option><option>Lambat</option></select></td><td><input class="form-control"></td></tr>
    <tr><td>Pencarian Pasien</td><td><select class="form-select"><option>Normal</option><option>Lambat</option></select></td><td><input class="form-control"></td></tr>
  </tbody></table></div>
  <h5 class="mt-4">4. Keluhan User</h5>
  <div class="mb-2"><label class="form-label">Ada keluhan?</label><select class="form-select"><option>Tidak ada</option><option>Ada</option></select></div>
  <textarea class="form-control" rows="3" placeholder="Tuliskan keluhan, unit, hasil pemeriksaan dan tindak lanjut bila ada"></textarea>
  <h5 class="mt-4">Kesimpulan &amp; Tindak Lanjut</h5>
  <textarea class="form-control" rows="3" placeholder="Kesimpulan monitoring dan tindak lanjut jika ada temuan"></textarea>
  <div class="mt-4 d-flex gap-2"><button type="button" class="btn btn-success">Simpan Monitoring</button><button type="button" class="btn btn-outline-secondary">Simpan Draft</button></div>
</form>
</div></div>
<?php require __DIR__.'/../partials/footer.php';