<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}

$pdo->exec("CREATE TABLE IF NOT EXISTS helpdesk_insiden (
 id INT AUTO_INCREMENT PRIMARY KEY,
 nomor VARCHAR(50) NOT NULL UNIQUE,
 tanggal_lapor DATETIME NOT NULL,
 tanggal_selesai DATETIME NULL,
 unit_pelapor VARCHAR(150) NULL,
 pelapor VARCHAR(150) NULL,
 masalah TEXT NOT NULL,
 prioritas ENUM('kritikal','tinggi','sedang','rendah') NOT NULL DEFAULT 'sedang',
 sla_menit INT NOT NULL DEFAULT 240,
 durasi_menit DECIMAL(12,2) NULL,
 status ENUM('open','selesai','batal') NOT NULL DEFAULT 'open',
 status_sla ENUM('sesuai','tidak_sesuai','belum_dinilai') NOT NULL DEFAULT 'belum_dinilai',
 penyelesaian TEXT NULL,
 pic_id INT NULL,
 sumber VARCHAR(50) NOT NULL DEFAULT 'laporan',
 catatan TEXT NULL,
 created_by INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_helpdesk_tanggal(tanggal_lapor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function norm($s){
 $s=trim(mb_strtolower((string)$s,'UTF-8'));
 $s=strtr($s,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','/'=>' ','-'=>' ','_'=>' ']);
 return preg_replace('/\s+/',' ',$s);
}
function pickCol($headers,$patterns){
 foreach($headers as $i=>$v){$n=norm($v);foreach($patterns as $p){if(strpos($n,$p)!==false)return $i;}}
 return null;
}
function parseDateValue($v){
 $v=trim((string)$v); if($v==='') return null;
 if(is_numeric($v)){
   $n=(float)$v;
   if($n>20000 && $n<60000){$ts=($n-25569)*86400;return date('Y-m-d H:i:s',$ts);}
 }
 $v=str_replace(['.','/'],'-',$v);
 $ts=strtotime($v);
 return $ts?date('Y-m-d H:i:s',$ts):null;
}
function docxTables($file){
 if(!class_exists('ZipArchive')) throw new RuntimeException('PHP ZipArchive belum aktif. Aktifkan extension=zip pada php.ini XAMPP.');
 $z=new ZipArchive();
 if($z->open($file)!==true) throw new RuntimeException('File DOCX tidak dapat dibuka.');
 $xml=$z->getFromName('word/document.xml');$z->close();
 if($xml===false) throw new RuntimeException('Dokumen Word tidak memiliki document.xml.');
 $dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadXML($xml);libxml_clear_errors();
 $xp=new DOMXPath($dom);$xp->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
 $tables=[];
 foreach($xp->query('//w:tbl') as $tbl){
   $rows=[];
   foreach($xp->query('./w:tr',$tbl) as $tr){
     $cells=[];
     foreach($xp->query('./w:tc',$tr) as $tc){
       $parts=[];foreach($xp->query('.//w:t',$tc) as $t)$parts[]=$t->nodeValue;
       $cells[]=trim(preg_replace('/\s+/',' ',implode(' ',$parts)));
     }
     if(array_filter($cells,fn($x)=>$x!==''))$rows[]=$cells;
   }
   if(count($rows)>=2)$tables[]=$rows;
 }
 return $tables;
}
function mapRows($tables){
 foreach($tables as $rows){
   $headers=$rows[0];
   $date=pickCol($headers,['tanggal','tgl','date']);
   $issue=pickCol($headers,['kerusakan','masalah','keluhan','gangguan','permasalahan','uraian']);
   $unit=pickCol($headers,['unit','ruangan','bagian','lokasi']);
   $reporter=pickCol($headers,['pelapor','pemohon','user']);
   $action=pickCol($headers,['tindakan','penanganan','perbaikan','solusi']);
   $start=pickCol($headers,['mulai','start','ditangani']);
   $finish=pickCol($headers,['selesai','finish']);
   $pic=pickCol($headers,['petugas','pic','teknisi']);
   if($date!==null && $issue!==null){
     $out=[];
     foreach(array_slice($rows,1) as $row){
       $v=function($idx)use($row){return $idx!==null?trim((string)($row[$idx]??'')):'';};
       $tgl=parseDateValue($v($date));$masalah=$v($issue);
       if(!$tgl||$masalah==='')continue;
       $sel=parseDateValue($v($finish)); if(!$sel && $start!==null && $action!==null){ /* tetap belum dinilai jika waktu selesai tidak tersedia */ }
       $out[]=[
         'tanggal_lapor'=>$tgl,'tanggal_selesai'=>$sel,'unit_pelapor'=>$v($unit),
         'pelapor'=>$v($reporter),'masalah'=>$masalah,'penyelesaian'=>$v($action),
         'pic_text'=>$v($pic),'sumber'=>'laporan','prioritas'=>'sedang'
       ];
     }
     return $out;
   }
 }
 return [];
}

$err=null;$preview=$_SESSION['helpdesk_import_preview']??[];$saved=0;
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
   $act=$_POST['action']??'upload';
   if($act==='upload'){
     if(empty($_FILES['word']['tmp_name'])||$_FILES['word']['error']!==0)throw new RuntimeException('Pilih file Word .docx terlebih dahulu.');
     $ext=strtolower(pathinfo($_FILES['word']['name'],PATHINFO_EXTENSION));
     if($ext!=='docx')throw new RuntimeException('Untuk tahap ini gunakan Word modern berformat .docx. File .doc lama perlu disimpan ulang sebagai .docx.');
     if($_FILES['word']['size']>20*1024*1024)throw new RuntimeException('Maksimal 20 MB.');
     $tables=docxTables($_FILES['word']['tmp_name']);
     $preview=mapRows($tables);
     if(!$preview)throw new RuntimeException('Tabel tidak terbaca. Pastikan baris pertama adalah judul kolom dan minimal ada kolom Tanggal serta Kerusakan/Masalah.');
     $_SESSION['helpdesk_import_preview']=$preview;
   }elseif($act==='save'){
     $preview=$_SESSION['helpdesk_import_preview']??[];
     if(!$preview)throw new RuntimeException('Data import sudah kosong. Upload ulang.');
     $defaultSla=max(1,(int)($_POST['default_sla']??240));
     $selected=$_POST['selected']??[];
     $ins=$pdo->prepare("INSERT INTO helpdesk_insiden(nomor,tanggal_lapor,tanggal_selesai,unit_pelapor,pelapor,masalah,prioritas,sla_menit,durasi_menit,status,status_sla,penyelesaian,pic_id,sumber,catatan,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
     foreach($selected as $idx){
       $idx=(int)$idx;if(!isset($preview[$idx]))continue;$r=$preview[$idx];
       $sel=$r['tanggal_selesai'];$dur=null;$ss='belum_dinilai';$status=$sel?'selesai':'open';
       if($sel){$a=new DateTime($r['tanggal_lapor']);$b=new DateTime($sel);$dur=round(($b->getTimestamp()-$a->getTimestamp())/60,2);if($dur<0){$sel=null;$dur=null;$status='open';}else{$ss=$dur<=$defaultSla?'sesuai':'tidak_sesuai';}}
       $nomor='MTN-'.date('YmdHis').'-'.random_int(10,99);
       $ins->execute([$nomor,$r['tanggal_lapor'],$sel,$r['unit_pelapor'],$r['pelapor'],$r['masalah'],$r['prioritas'],$defaultSla,$dur,$status,$ss,$r['penyelesaian'],null,'laporan','Import laporan maintenance Word',$_SESSION['user']['id']??null]);
       $saved++;
     }
     unset($_SESSION['helpdesk_import_preview']);$preview=[];
   }
 }catch(Throwable $e){$err=$e->getMessage();}
}
$pics=$pdo->query("SELECT id,nama FROM pic WHERE aktif=1 ORDER BY nama")->fetchAll();
$page_title='Import Laporan Maintenance';require __DIR__.'/../partials/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
 <div><h2 class="mb-1">Import Laporan Maintenance</h2><div class="text-muted">Masukkan arsip Word bulanan ke Helpdesk tanpa mengetik ulang. Data yang tidak tersedia tidak akan dibuat-buat.</div></div>
 <a class="btn btn-outline-secondary" href="index.php">Kembali Helpdesk</a>
</div>
<?php if($err):?><div class="alert alert-danger"><?=h($err)?></div><?php endif;?>
<?php if($saved):?><div class="alert alert-success"><strong><?=$saved?> data berhasil dimasukkan.</strong> Data berasal dari laporan maintenance dan status SLA tetap Belum Dinilai jika waktu selesai tidak tersedia.</div><?php endif;?>
<div class="alert alert-warning"><strong>Format yang disarankan:</strong> Word <b>.docx</b> dengan tabel dan baris pertama berisi judul kolom seperti Tanggal, Unit, Kerusakan/Masalah, Tindakan/Perbaikan, dan bila ada Tanggal/Jam Selesai.</div>
<div class="card shadow-sm border-0 mb-3"><div class="card-body">
<form method="post" enctype="multipart/form-data" class="row g-3">
<input type="hidden" name="action" value="upload">
<div class="col-md-8"><label class="form-label">File laporan maintenance (.docx)</label><input class="form-control" type="file" name="word" accept=".docx" required></div>
<div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary">📥 Baca &amp; Tampilkan Data</button></div>
</form></div></div>
<?php if($preview):?>
<div class="card shadow-sm border-0"><div class="card-body">
<h5>Preview <?=count($preview)?> baris</h5>
<form method="post">
<input type="hidden" name="action" value="save">
<div class="row g-2 mb-3"><div class="col-md-3"><label class="form-label">SLA default untuk data import</label><input name="default_sla" type="number" min="1" value="240" class="form-control"></div><div class="col-md-9 small text-muted d-flex align-items-end">Gunakan hanya jika memang ada ketentuan SLA yang berlaku. Untuk arsip lama tanpa jam selesai, data tetap <b>Belum Dinilai</b>.</div></div>
<div class="table-responsive"><table class="table table-sm table-bordered align-middle"><thead><tr><th><input type="checkbox" checked onclick="document.querySelectorAll('.pick').forEach(x=>x.checked=this.checked)"></th><th>Tanggal</th><th>Unit</th><th>Masalah</th><th>Tindakan</th><th>Selesai</th><th>Status SLA</th></tr></thead><tbody>
<?php foreach($preview as $n=>$r):?>
<tr><td><input class="pick" type="checkbox" name="selected[]" value="<?=$n?>" checked></td><td><?=h($r['tanggal_lapor'])?></td><td><?=h($r['unit_pelapor'])?></td><td><?=h($r['masalah'])?></td><td><?=h($r['penyelesaian'])?></td><td><?=h($r['tanggal_selesai']??'')?></td><td><?=$r['tanggal_selesai']?'Akan dihitung':'BELUM DINILAI'?></td></tr>
<?php endforeach;?>
</tbody></table></div>
<button class="btn btn-success">💾 Masukkan ke Helpdesk</button> <a class="btn btn-outline-secondary" href="import.php">Batalkan Preview</a>
</form></div></div>
<?php endif;?>
<?php require __DIR__.'/../partials/footer.php';