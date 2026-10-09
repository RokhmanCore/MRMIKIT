<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();
$id=(int)($_GET['id']??0);
if(!$id) die('ID monitoring tidak valid.');
$s=$pdo->prepare('SELECT * FROM audit_monitoring_it WHERE id=?'); $s->execute([$id]); $r=$s->fetch();
if(!$r) die('Data monitoring tidak ditemukan.');
$ev=$pdo->prepare('SELECT * FROM audit_monitoring_it_evidence WHERE audit_id=? ORDER BY id'); $ev->execute([$id]); $files=$ev->fetchAll();
$page_title='Edit Monitoring IT'; require __DIR__.'/../partials/header.php';
function v($r,$k){return htmlspecialchars((string)($r[$k]??''),ENT_QUOTES,'UTF-8');}
?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h2>Edit Monitoring IT #<?=$id?></h2><div class="text-muted">Perbaiki data yang salah, lalu simpan perubahan.</div></div><a class="btn btn-outline-secondary" href="detail.php?id=<?=$id?>">Kembali</a></div>
<form method="post" action="update.php" enctype="multipart/form-data">
<input type="hidden" name="id" value="<?=$id?>">
<div class="card shadow-sm border-0"><div class="card-body">
<div class="row g-3">
<div class="col-md-6"><label class="form-label">Tanggal Monitoring *</label><input name="tanggal_monitoring" type="date" class="form-control" required value="<?=v($r,'tanggal_monitoring')?>"></div>
<div class="col-md-6"><label class="form-label">Petugas *</label><input name="petugas" class="form-control" required value="<?=v($r,'petugas')?>"></div>
</div><input type="hidden" name="periode" value="Bulanan">
<hr><h5>1. Kepatuhan Sistem</h5><div class="row g-2"><div class="col-md-4"><select name="kepatuhan_status" class="form-select"><?php foreach(['Memenuhi','Tidak Memenuhi','N/A'] as $x): ?><option <?=$r['kepatuhan_status']===$x?'selected':''?>><?=$x?></option><?php endforeach; ?></select></div><div class="col-md-8"><input name="kepatuhan_catatan" class="form-control" placeholder="Catatan temuan" value="<?=v($r,'kepatuhan_catatan')?>"></div></div>
<hr><h5>2. Kestabilan Jaringan</h5><div class="row g-2">
<?php foreach(['jaringan_lan'=>'LAN','jaringan_wifi'=>'Wi-Fi','jaringan_internet'=>'Internet','jaringan_server'=>'Server SIMRS'] as $k=>$label): ?><div class="col-md-3"><label><?=$label?><select name="<?=$k?>" class="form-select"><?php foreach(['Stabil','Tidak stabil','N/A'] as $x): ?><option <?=$r[$k]===$x?'selected':''?>><?=$x?></option><?php endforeach; ?></select></label></div><?php endforeach; ?>
</div><textarea name="jaringan_catatan" class="form-control mt-2" rows="2" placeholder="Catatan jaringan"><?=v($r,'jaringan_catatan')?></textarea>
<hr><h5>3. Kecepatan / Loading RME</h5><div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Modul</th><th>Hasil</th></tr></thead><tbody>
<?php foreach(['rme_login'=>'Login','rme_data_pasien'=>'Data Pasien','rme_soap'=>'Pemeriksaan / SOAP','rme_resep'=>'Resep','rme_pencarian'=>'Pencarian Pasien'] as $k=>$label): ?><tr><td><?=$label?></td><td><select name="<?=$k?>" class="form-select"><?php foreach(['Normal','Lambat','N/A'] as $x): ?><option <?=$r[$k]===$x?'selected':''?>><?=$x?></option><?php endforeach; ?></select></td></tr><?php endforeach; ?>
</tbody></table></div><textarea name="rme_catatan" class="form-control" rows="2" placeholder="Catatan RME"><?=v($r,'rme_catatan')?></textarea>
<hr><h5>4. Keluhan User</h5><select name="keluhan_status" class="form-select mb-2" id="keluhanStatus"><option value="Tidak ada" <?=$r['keluhan_status']!=='Ada'?'selected':''?>>Tidak ada keluhan</option><option value="Ada" <?=$r['keluhan_status']==='Ada'?'selected':''?>>Ada keluhan</option></select><textarea name="keluhan_detail" class="form-control" rows="3" placeholder="Detail keluhan"><?=v($r,'keluhan_detail')?></textarea>
<hr><h5>Kesimpulan &amp; Tindak Lanjut</h5><textarea name="kesimpulan" class="form-control mb-2" rows="2" placeholder="Kesimpulan"><?=v($r,'kesimpulan')?></textarea><textarea name="tindak_lanjut" class="form-control" rows="2" placeholder="Tindak lanjut"><?=v($r,'tindak_lanjut')?></textarea>
<hr><h5>Evidence / Bukti yang sudah ada</h5>
<?php if(!$files): ?><p class="text-muted">Belum ada file bukti.</p><?php else: ?><ul><?php foreach($files as $f): ?><li><a href="<?=htmlspecialchars($f['file_path'])?>" target="_blank"><?=htmlspecialchars($f['nama_file'])?></a> (tetap disimpan)</li><?php endforeach; ?></ul><?php endif; ?>
<label class="form-label">Tambah bukti baru (opsional)</label><input type="file" name="evidence[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.pdf,.webp"><div class="form-text">Bukti lama tidak dihapus. File baru akan ditambahkan.</div>
<div class="mt-4"><button class="btn btn-success btn-lg" type="submit">Simpan Perubahan</button></div>
</div></div></form>
<?php require __DIR__.'/../partials/footer.php'; ?>