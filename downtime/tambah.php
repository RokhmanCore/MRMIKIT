<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function ensureDowntimeSchema($pdo){
 foreach([
  "ALTER TABLE downtime ADD COLUMN dampak VARCHAR(30) NOT NULL DEFAULT 'total' AFTER unit_terdampak",
  "ALTER TABLE downtime ADD COLUMN pic_id INT NULL AFTER tindak_lanjut",
  "ALTER TABLE downtime ADD COLUMN sumber_data VARCHAR(30) NOT NULL DEFAULT 'monitoring' AFTER pic_id",
  "ALTER TABLE downtime ADD COLUMN bukti_filename VARCHAR(255) NULL AFTER sumber_data",
  "ALTER TABLE downtime ADD COLUMN bukti_original_name VARCHAR(255) NULL AFTER bukti_filename",
  "ALTER TABLE downtime ADD COLUMN bukti_mime VARCHAR(150) NULL AFTER bukti_original_name",
  "ALTER TABLE downtime ADD COLUMN bukti_size BIGINT NOT NULL DEFAULT 0 AFTER bukti_mime"
 ] as $sql){try{$pdo->exec($sql);}catch(Throwable $e){}}
}
ensureDowntimeSchema($pdo);

if($_SERVER['REQUEST_METHOD']==='POST'){
 $mulai=trim($_POST['mulai']??''); $selesai=trim($_POST['selesai']??'');
 if(!$mulai) die('Tanggal/jam mulai wajib diisi.');
 if($selesai && strtotime($selesai)<strtotime($mulai)) die('Tanggal/jam selesai tidak boleh lebih awal dari mulai.');
 $fileName=$orig=$mime=null; $size=0;
 if(isset($_FILES['bukti']) && $_FILES['bukti']['error']!==UPLOAD_ERR_NO_FILE){
   if($_FILES['bukti']['error']!==UPLOAD_ERR_OK) die('Upload bukti gagal.');
   if($_FILES['bukti']['size']>10*1024*1024) die('Ukuran bukti maksimal 10 MB.');
   $allowed=['application/pdf','image/jpeg','image/png','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/msword','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/vnd.ms-excel'];
   $mime=(new finfo(FILEINFO_MIME_TYPE))->file($_FILES['bukti']['tmp_name']);
   if(!in_array($mime,$allowed,true)) die('Format bukti harus PDF, JPG, PNG, DOC/DOCX atau XLS/XLSX.');
   $ext=strtolower(pathinfo($_FILES['bukti']['name'],PATHINFO_EXTENSION));
   $fileName=date('YmdHis').'_'.bin2hex(random_bytes(6)).'.'.$ext; $orig=$_FILES['bukti']['name']; $size=(int)$_FILES['bukti']['size'];
   $dir=__DIR__.'/../uploads/downtime'; if(!is_dir($dir)) mkdir($dir,0775,true);
   if(!move_uploaded_file($_FILES['bukti']['tmp_name'],$dir.'/'.$fileName)) die('Bukti tidak dapat disimpan.');
 }
 $st=$pdo->prepare('INSERT INTO downtime(mulai,selesai,jenis,penyebab,unit_terdampak,dampak,tindakan,evaluasi,tindak_lanjut,pic_id,sumber_data,bukti_filename,bukti_original_name,bukti_mime,bukti_size,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
 $st->execute([$mulai,$selesai?:null,$_POST['jenis']??'Tidak Terencana',$_POST['penyebab']??'',$_POST['unit_terdampak']??'',$_POST['dampak']??'total',$_POST['tindakan']??'',$_POST['evaluasi']??'',$_POST['tindak_lanjut']??'',($_POST['pic_id']??'')?:null,$_POST['sumber_data']??'monitoring',$fileName,$orig,$mime,$size,$_SESSION['user']['id']]);
 // Sinkronkan IM-IT-01 hanya untuk bulan yang terkena kejadian downtime.
 // Bulan tanpa downtime TIDAK dibuat sebagai capaian 100%.
 $tahun=(int)date('Y',strtotime($mulai));
 $indSt=$pdo->prepare("SELECT * FROM mutu_indikator WHERE kode='IM-IT-01' AND aktif=1 LIMIT 1");
 $indSt->execute(); $ind=$indSt->fetch();
 if($ind){
   $ev=$pdo->query("SELECT mulai,selesai FROM downtime WHERE selesai IS NOT NULL AND selesai>mulai ORDER BY mulai")->fetchAll();
   for($bulan=1;$bulan<=12;$bulan++){
     $start=new DateTime(sprintf('%04d-%02d-01 00:00:00',$tahun,$bulan));
     $end=(clone $start)->modify('+1 month');
     $totalSeconds=$end->getTimestamp()-$start->getTimestamp();
     $intervals=[];
     foreach($ev as $x){
       $ds=new DateTime($x['mulai']); $de=new DateTime($x['selesai']);
       $cs=$ds>$start?$ds:$start; $ce=$de<$end?$de:$end;
       if($ce>$cs) $intervals[]=[$cs->getTimestamp(),$ce->getTimestamp()];
     }
     usort($intervals,fn($a,$b)=>$a[0]<=>$b[0]);
     $merged=[];
     foreach($intervals as $iv){
       if(!$merged || $iv[0]>$merged[count($merged)-1][1]) $merged[]=$iv;
       else $merged[count($merged)-1][1]=max($merged[count($merged)-1][1],$iv[1]);
     }
     if(!$merged) continue;

     $downSeconds=0;
     foreach($merged as $iv) $downSeconds += $iv[1]-$iv[0];
     $totalHours=$totalSeconds/3600;
     $downHours=$downSeconds/3600;
     $availableHours=max(0,$totalHours-$downHours);
     $cap=$totalHours>0?($availableHours/$totalHours)*100:0;
     $target=$ind['target']!==null?(float)$ind['target']:null;
     $status=$target===null?'belum_dinilai':($cap >= $target?'tercapai':'tidak_tercapai');

     $oldSt=$pdo->prepare("SELECT analisis,tindak_lanjut FROM mutu_capaian WHERE indikator_id=? AND periode=?");
     $oldSt->execute([$ind['id'],$start->format('Y-m-d')]);
     $old=$oldSt->fetch();

     $up=$pdo->prepare("INSERT INTO mutu_capaian
       (indikator_id,periode,numerator,denominator,capaian,target_snapshot,analisis,tindak_lanjut,status,created_by)
       VALUES(?,?,?,?,?,?,?,?,?,?)
       ON DUPLICATE KEY UPDATE numerator=VALUES(numerator),denominator=VALUES(denominator),
       capaian=VALUES(capaian),target_snapshot=VALUES(target_snapshot),status=VALUES(status),
       updated_at=CURRENT_TIMESTAMP");
     $up->execute([$ind['id'],$start->format('Y-m-d'),round($availableHours,4),round($totalHours,4),
       round($cap,4),$target,$old['analisis']??'',$old['tindak_lanjut']??'',$status,$_SESSION['user']['id']]);
   }
 }
 header('Location:index.php'); exit;
}
$pics=$pdo->query("SELECT id,nama,unit,jabatan FROM pic WHERE aktif=1 ORDER BY nama")->fetchAll();
$page_title='Catat Downtime';
require __DIR__.'/../partials/header.php';
?>
<h2>Catat Downtime SIMRS</h2>
<div class="card shadow-sm border-0"><div class="card-body">
<form method="post" enctype="multipart/form-data">
<div class="row">
 <div class="col-md-6 mb-3"><label class="form-label">Tanggal & jam mulai *</label><input class="form-control" type="datetime-local" name="mulai" id="mulai" required></div>
 <div class="col-md-6 mb-3"><label class="form-label">Tanggal & jam selesai</label><input class="form-control" type="datetime-local" name="selesai" id="selesai"><div class="form-text">Kosongkan hanya jika kejadian masih berlangsung.</div></div>
</div>
<div class="row">
 <div class="col-md-6 mb-3"><label class="form-label">Durasi otomatis</label><input class="form-control" id="durasi" readonly value="Otomatis setelah mulai & selesai diisi"></div>
 <div class="col-md-6 mb-3"><label class="form-label">Dampak *</label><select class="form-select" name="dampak" required><option value="total">SIMRS total tidak dapat digunakan</option><option value="sebagian">Sebagian modul tidak dapat digunakan</option></select></div>
</div>
<div class="row">
 <div class="col-md-6 mb-3"><label class="form-label">Jenis</label><select class="form-select" name="jenis"><option>Terencana</option><option selected>Tidak Terencana</option></select></div>
 <div class="col-md-6 mb-3"><label class="form-label">PIC</label><select class="form-select" name="pic_id"><option value="">-- pilih PIC --</option><?php foreach($pics as $p): ?><option value="<?=$p['id']?>"><?=h($p['nama'])?><?= $p['unit']?' — '.h($p['unit']):'' ?></option><?php endforeach; ?></select></div>
</div>
<div class="mb-3"><label class="form-label">Penyebab</label><textarea class="form-control" name="penyebab" rows="2"></textarea></div>
<div class="mb-3"><label class="form-label">Unit/Modul terdampak</label><textarea class="form-control" name="unit_terdampak" rows="2" placeholder="Contoh: seluruh unit / pendaftaran / farmasi / kasir"></textarea></div>
<div class="mb-3"><label class="form-label">Tindakan perbaikan</label><textarea class="form-control" name="tindakan" rows="2"></textarea></div>
<div class="mb-3"><label class="form-label">Evaluasi</label><textarea class="form-control" name="evaluasi" rows="2"></textarea></div>
<div class="mb-3"><label class="form-label">Tindak lanjut / RTL</label><textarea class="form-control" name="tindak_lanjut" rows="2"></textarea></div>
<div class="row">
 <div class="col-md-6 mb-3"><label class="form-label">Sumber data *</label><select class="form-select" name="sumber_data" required><option value="log">Log sistem</option><option value="monitoring" selected>Monitoring IT</option><option value="laporan">Laporan gangguan</option><option value="rekonstruksi">Rekonstruksi/catatan petugas IT</option></select></div>
 <div class="col-md-6 mb-3"><label class="form-label">Bukti dokumen</label><input class="form-control" type="file" name="bukti" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"><div class="form-text">Maks. 10 MB. PDF/Office/gambar.</div></div>
</div>
<div class="d-flex gap-2"><button class="btn btn-success">Simpan Downtime</button><a class="btn btn-outline-secondary" href="index.php">Batal</a></div>
</form></div></div>
<script>
(function(){
 const a=document.getElementById('mulai'),b=document.getElementById('selesai'),o=document.getElementById('durasi');
 function calc(){if(!a.value||!b.value){o.value='Otomatis setelah mulai & selesai diisi';return} const s=new Date(a.value),e=new Date(b.value),sec=Math.max(0,(e-s)/1000); const h=Math.floor(sec/3600),m=Math.floor((sec%3600)/60),d=Math.floor(sec%60); o.value=(h?h+' jam ':'')+(m?m+' menit ':'')+(d?d+' detik':'')||'0 detik';}
 a.addEventListener('change',calc);b.addEventListener('change',calc);
})();
</script>
<?php require __DIR__.'/../partials/footer.php';