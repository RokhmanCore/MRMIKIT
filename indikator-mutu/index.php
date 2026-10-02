<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();

$page_title='Indikator Mutu IT';
$msg=''; $err='';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        $action=$_POST['action']??'';

        if ($action==='add_indicator') {
            $edit_post=(int)($_POST['edit_id']??0);
            $st=$pdo->prepare("INSERT INTO mutu_indikator
                (kode,nama,definisi_operasional,numerator_label,denominator_label,formula,target,satuan,arah,frekuensi,sumber_data,metode_pengumpulan,pic_id,created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            if ($edit_post) {
                $st=$pdo->prepare("UPDATE mutu_indikator SET kode=?,nama=?,definisi_operasional=?,numerator_label=?,denominator_label=?,formula=?,target=?,satuan=?,arah=?,frekuensi=?,sumber_data=?,metode_pengumpulan=?,pic_id=? WHERE id=?");
                $st->execute([trim($_POST['kode']),trim($_POST['nama']),trim($_POST['definisi_operasional']??''),trim($_POST['numerator_label']??''),trim($_POST['denominator_label']??''),trim($_POST['formula']??''),($_POST['target']!==''?$_POST['target']:null),trim($_POST['satuan']??'%'),$_POST['arah']??'sesuai_target',$_POST['frekuensi']??'bulanan',trim($_POST['sumber_data']??''),trim($_POST['metode_pengumpulan']??''),($_POST['pic_id']!==''?$_POST['pic_id']:null),$edit_post]);
                $msg='Indikator berhasil diperbarui.';
            } else {
            $st->execute([
                trim($_POST['kode']),trim($_POST['nama']),trim($_POST['definisi_operasional']??''),
                trim($_POST['numerator_label']??''),trim($_POST['denominator_label']??''),trim($_POST['formula']??''),
                ($_POST['target']!==''?$_POST['target']:null),trim($_POST['satuan']??'%'),
                $_POST['arah']??'sesuai_target',$_POST['frekuensi']??'bulanan',trim($_POST['sumber_data']??''),
                trim($_POST['metode_pengumpulan']??''),($_POST['pic_id']!==''?$_POST['pic_id']:null),$_SESSION['user']['id']??null
            ]);
            $msg='Indikator berhasil ditambahkan.';
            }
        }

        if ($action==='delete_capaian') {
            $id=(int)($_POST['capaian_id']??0);
            if(!$id) throw new RuntimeException('Capaian tidak valid.');
            $st=$pdo->prepare("SELECT id FROM mutu_capaian WHERE id=?");
            $st->execute([$id]);
            if(!$st->fetch()) throw new RuntimeException('Capaian tidak ditemukan.');
            $pdo->prepare("DELETE FROM mutu_capaian WHERE id=?")->execute([$id]);
            $msg='Capaian dan bukti terkait berhasil dihapus.';
        }

        if ($action==='save_capaian') {
            $indikator_id=(int)$_POST['indikator_id'];
            $periode=($_POST['periode']??'').' -01';
            $periode=str_replace(' ','',$periode);
            $num=($_POST['numerator']!==''?$_POST['numerator']:null);
            $den=($_POST['denominator']!==''?$_POST['denominator']:null);
            $target=($_POST['target_snapshot']!==''?$_POST['target_snapshot']:null);
            $cap=($_POST['capaian']!==''?$_POST['capaian']:null);
            if ($cap===null && $num!==null && $den!==null && (float)$den!=0) $cap=((float)$num/(float)$den)*100;

            $st=$pdo->prepare("SELECT arah,target FROM mutu_indikator WHERE id=?");
            $st->execute([$indikator_id]); $ind=$st->fetch();
            if ($target===null && $ind) $target=$ind['target'];

            $status='belum_dinilai';
            if ($cap!==null && $target!==null) {
                $status=((float)$cap >= (float)$target) ? 'tercapai' : 'tidak_tercapai';
                if (($ind['arah']??'sesuai_target')==='turun') {
                    $status=((float)$cap <= (float)$target) ? 'tercapai' : 'tidak_tercapai';
                }
            }
            $editCapaian=(int)($_POST['edit_capaian_id']??0);
            if($editCapaian){
                $st=$pdo->prepare("UPDATE mutu_capaian SET periode=?,numerator=?,denominator=?,capaian=?,target_snapshot=?,analisis=?,tindak_lanjut=?,status=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND indikator_id=?");
                $st->execute([$periode,$num,$den,$cap,$target,trim($_POST['analisis']??''),trim($_POST['tindak_lanjut']??''),$status,$editCapaian,$indikator_id]);
                $msg='Capaian berhasil diperbarui.';
            } else {
                $st=$pdo->prepare("INSERT INTO mutu_capaian
                    (indikator_id,periode,numerator,denominator,capaian,target_snapshot,analisis,tindak_lanjut,status,created_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE numerator=VALUES(numerator),denominator=VALUES(denominator),capaian=VALUES(capaian),
                    target_snapshot=VALUES(target_snapshot),analisis=VALUES(analisis),tindak_lanjut=VALUES(tindak_lanjut),
                    status=VALUES(status),updated_at=CURRENT_TIMESTAMP");
                $st->execute([$indikator_id,$periode,$num,$den,$cap,$target,trim($_POST['analisis']??''),trim($_POST['tindak_lanjut']??''),$status,$_SESSION['user']['id']??null]);
                $msg='Capaian periode berhasil disimpan.';
            }
        }

        if ($action==='hitung_ketersediaan_simrs') {
            $indikator_id=(int)($_POST['indikator_id']??0);
            $tahun=(int)($_POST['tahun']??date('Y'));
            if($indikator_id<1 || $tahun<2020 || $tahun>2100) throw new RuntimeException('Indikator atau tahun tidak valid.');

            $st=$pdo->prepare("SELECT * FROM mutu_indikator WHERE id=?");
            $st->execute([$indikator_id]); $ind=$st->fetch();
            if(!$ind) throw new RuntimeException('Indikator tidak ditemukan.');
            if($ind['kode']!=='IM-IT-01') throw new RuntimeException('Fitur ini khusus IM-IT-01 Ketersediaan SIMRS.');

            $events=$pdo->query("SELECT mulai,selesai FROM downtime WHERE selesai IS NOT NULL AND selesai>mulai ORDER BY mulai")->fetchAll();
            $st=$pdo->prepare("SELECT id,periode,numerator,denominator,capaian,target_snapshot,analisis,tindak_lanjut,status FROM mutu_capaian WHERE indikator_id=? AND periode BETWEEN ? AND ?");
            $st->execute([$indikator_id,sprintf('%04d-01-01',$tahun),sprintf('%04d-12-31',$tahun)]);
            $existing=[]; foreach($st as $rr) $existing[(int)date('n',strtotime($rr['periode']))]=$rr;

            $up=$pdo->prepare("INSERT INTO mutu_capaian
                (indikator_id,periode,numerator,denominator,capaian,target_snapshot,analisis,tindak_lanjut,status,created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE numerator=VALUES(numerator),denominator=VALUES(denominator),capaian=VALUES(capaian),
                target_snapshot=VALUES(target_snapshot),status=VALUES(status),updated_at=CURRENT_TIMESTAMP");

            // Sinkronisasi IM-IT-01 hanya memperbarui bulan yang benar-benar memiliki downtime.
            // Pilihan "Tidak Ada Downtime" dan "Belum Ada Data" tidak dihapus oleh tombol ini.
            $hasil=0;
            for($bulan=1;$bulan<=12;$bulan++){
                $start=new DateTime(sprintf('%04d-%02d-01 00:00:00',$tahun,$bulan));
                $end=(clone $start)->modify('+1 month');
                $totalSeconds=$end->getTimestamp()-$start->getTimestamp();
                $intervals=[];
                foreach($events as $ev){
                    $ds=new DateTime($ev['mulai']);
                    $de=new DateTime($ev['selesai']);
                    $clipStart=$ds>$start?$ds:$start;
                    $clipEnd=$de<$end?$de:$end;
                    if($clipEnd>$clipStart) $intervals[]=[$clipStart->getTimestamp(),$clipEnd->getTimestamp()];
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

                $up=$pdo->prepare("INSERT INTO mutu_capaian
                    (indikator_id,periode,numerator,denominator,capaian,target_snapshot,analisis,tindak_lanjut,status,created_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE numerator=VALUES(numerator),denominator=VALUES(denominator),
                    capaian=VALUES(capaian),target_snapshot=VALUES(target_snapshot),status=VALUES(status),
                    updated_at=CURRENT_TIMESTAMP");
                $up->execute([$indikator_id,$start->format('Y-m-d'),round($availableHours,4),round($totalHours,4),
                    round($cap,4),$target,'','',$status,$_SESSION['user']['id']??null]);
                $hasil++;
            }
            $msg="IM-IT-01 disinkronkan dari downtime. Hanya bulan yang memiliki kejadian downtime yang diperbarui: {$hasil} bulan.";
        }

        if ($action==='save_capaian_bulanan') {
            $indikator_id=(int)($_POST['indikator_id']??0);
            $tahun=(int)($_POST['tahun']??date('Y'));
            if($indikator_id<1 || $tahun<2020 || $tahun>2100) throw new RuntimeException('Indikator atau tahun tidak valid.');
            $st=$pdo->prepare("SELECT * FROM mutu_indikator WHERE id=?");
            $st->execute([$indikator_id]); $ind=$st->fetch();
            if(!$ind) throw new RuntimeException('Indikator tidak ditemukan.');
            $rowsPost=$_POST['bulanan']??[];
            $events=[];
            if($ind['kode']==='IM-IT-01'){
                $events=$pdo->query("SELECT mulai,selesai FROM downtime WHERE selesai IS NOT NULL AND selesai>mulai ORDER BY mulai")->fetchAll();
            }
            $sql=$pdo->prepare("INSERT INTO mutu_capaian
                (indikator_id,periode,numerator,denominator,capaian,target_snapshot,analisis,tindak_lanjut,status,created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE numerator=VALUES(numerator),denominator=VALUES(denominator),capaian=VALUES(capaian),
                target_snapshot=VALUES(target_snapshot),analisis=VALUES(analisis),tindak_lanjut=VALUES(tindak_lanjut),
                status=VALUES(status),updated_at=CURRENT_TIMESTAMP");
            $tersimpan=0;
            for($bulan=1;$bulan<=12;$bulan++){
                $r=is_array($rowsPost[$bulan]??null)?$rowsPost[$bulan]:[];
                $kondisi=trim((string)($r['kondisi']??''));
                $num=trim((string)($r['numerator']??'')); $den=trim((string)($r['denominator']??''));
                $cap=trim((string)($r['capaian']??'')); $target=trim((string)($r['target']??''));
                $analisis=trim((string)($r['analisis']??'')); $rtl=trim((string)($r['tindak_lanjut']??''));

                if($ind['kode']==='IM-IT-01'){
                    $periode=sprintf('%04d-%02d-01',$tahun,$bulan);
                    if($kondisi==='belum_ada_data'){
                        $pdo->prepare("DELETE FROM mutu_capaian WHERE indikator_id=? AND periode=?")->execute([$indikator_id,$periode]);
                        continue;
                    }
                    $start=new DateTime(sprintf('%04d-%02d-01 00:00:00',$tahun,$bulan));
                    $end=(clone $start)->modify('+1 month');
                    $totalSeconds=$end->getTimestamp()-$start->getTimestamp();
                    $targetVal=($target!=='')?(float)$target:($ind['target']!==null?(float)$ind['target']:null);

                    if($kondisi==='tidak_ada_downtime'){
                        $hours=$totalSeconds/3600; $capVal=100.0;
                        $status=$targetVal===null?'belum_dinilai':($capVal >= $targetVal?'tercapai':'tidak_tercapai');
                        $sql->execute([$indikator_id,$periode,$hours,$hours,$capVal,$targetVal,$analisis,$rtl,$status,$_SESSION['user']['id']??null]);
                        $tersimpan++; continue;
                    }

                    if($kondisi==='ada_downtime'){
                        $intervals=[];
                        foreach($events as $ev){
                            $ds=new DateTime($ev['mulai']); $de=new DateTime($ev['selesai']);
                            $cs=$ds>$start?$ds:$start; $ce=$de<$end?$de:$end;
                            if($ce>$cs) $intervals[]=[$cs->getTimestamp(),$ce->getTimestamp()];
                        }
                        usort($intervals,fn($a,$b)=>$a[0]<=>$b[0]);
                        $merged=[];
                        foreach($intervals as $iv){
                            if(!$merged || $iv[0]>$merged[count($merged)-1][1]) $merged[]=$iv;
                            else $merged[count($merged)-1][1]=max($merged[count($merged)-1][1],$iv[1]);
                        }
                        if(!$merged) throw new RuntimeException("Bulan {$bulan}/{$tahun} dipilih 'Ada Downtime', tetapi belum ada kejadian downtime yang selesai pada bulan tersebut.");
                        $downSeconds=0; foreach($merged as $iv) $downSeconds += $iv[1]-$iv[0];
                        $totalHours=$totalSeconds/3600; $availableHours=max(0,$totalHours-($downSeconds/3600));
                        $capVal=$totalHours>0?($availableHours/$totalHours)*100:0;
                        $status=$targetVal===null?'belum_dinilai':($capVal >= $targetVal?'tercapai':'tidak_tercapai');
                        $sql->execute([$indikator_id,$periode,round($availableHours,4),round($totalHours,4),round($capVal,4),$targetVal,$analisis,$rtl,$status,$_SESSION['user']['id']??null]);
                        $tersimpan++; continue;
                    }
                    continue;
                }

                if($num==='' && $den==='' && $cap==='' && $target==='' && $analisis==='' && $rtl==='') continue;
                $numVal=($num!=='')?(float)$num:null; $denVal=($den!=='')?(float)$den:null; $capVal=($cap!=='')?(float)$cap:null;
                $targetVal=($target!=='')?(float)$target:($ind['target']!==null?(float)$ind['target']:null);
                if($capVal===null && $numVal!==null && $denVal!==null && $denVal!=0) $capVal=($numVal/$denVal)*100;
                $status='belum_dinilai';
                if($capVal!==null && $targetVal!==null) $status=((($ind['arah']??'sesuai_target')==='turun')?($capVal<=$targetVal):($capVal>=$targetVal))?'tercapai':'tidak_tercapai';
                $periode=sprintf('%04d-%02d-01',$tahun,$bulan);
                $sql->execute([$indikator_id,$periode,$numVal,$denVal,$capVal,$targetVal,$analisis,$rtl,$status,$_SESSION['user']['id']??null]);
                $tersimpan++;
            }
            $msg=$tersimpan>0 ? "Capaian {$tersimpan} bulan berhasil disimpan." : 'Tidak ada perubahan yang diisi.';
        }

        if ($action==='upload_bukti') {
            $capaian_id=(int)($_POST['capaian_id']??0);
            if (!$capaian_id || empty($_FILES['bukti_file']) || $_FILES['bukti_file']['error']!==UPLOAD_ERR_OK) {
                throw new RuntimeException('File bukti belum dipilih atau gagal diunggah.');
            }
            $st=$pdo->prepare("SELECT c.id,c.indikator_id,c.periode,i.kode FROM mutu_capaian c JOIN mutu_indikator i ON i.id=c.indikator_id WHERE c.id=?");
            $st->execute([$capaian_id]); $caprow=$st->fetch();
            if (!$caprow) throw new RuntimeException('Data capaian tidak ditemukan.');

            $f=$_FILES['bukti_file'];
            $max=20*1024*1024;
            if ($f['size']>$max) throw new RuntimeException('Ukuran file maksimal 20 MB.');
            $allowed=['pdf','doc','docx','xls','xlsx','csv','jpg','jpeg','png','zip'];
            $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
            if (!in_array($ext,$allowed,true)) throw new RuntimeException('Format file tidak didukung. Gunakan PDF, Office, CSV, JPG/PNG atau ZIP.');

            $dir=__DIR__.'/../uploads/mutu-indikator';
            if (!is_dir($dir) && !mkdir($dir,0775,true) && !is_dir($dir)) throw new RuntimeException('Folder upload tidak dapat dibuat.');
            $safe=bin2hex(random_bytes(8)).'_'.preg_replace('/[^A-Za-z0-9._-]/','_',basename($f['name']));
            $dest=$dir.'/'.$safe;
            if (!move_uploaded_file($f['tmp_name'],$dest)) throw new RuntimeException('File gagal disimpan.');

            $st=$pdo->prepare("INSERT INTO mutu_bukti(capaian_id,nama_file,original_name,mime_type,size_bytes,catatan,uploaded_by) VALUES(?,?,?,?,?,?,?)");
            $st->execute([$capaian_id,$safe,$f['name'],$f['type']??'',(int)$f['size'],trim($_POST['catatan_bukti']??''),$_SESSION['user']['id']??null]);
            $msg='Bukti indikator berhasil diunggah.';
        }

        if ($action==='map_ep') {
            $indikator_id=(int)$_POST['indikator_id'];
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM mutu_indikator_ep WHERE indikator_id=?")->execute([$indikator_id]);
            foreach (($_POST['ep_ids']??[]) as $ep_id) {
                $pdo->prepare("INSERT INTO mutu_indikator_ep(indikator_id,ep_id) VALUES(?,?)")->execute([$indikator_id,(int)$ep_id]);
            }
            $pdo->commit();
            $msg='Pemetaan indikator ke EP berhasil disimpan.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err='Gagal menyimpan: '.$e->getMessage();
    }
}

$editId=(int)($_GET['edit']??0);
$edit=null;
if($editId){$st=$pdo->prepare("SELECT * FROM mutu_indikator WHERE id=?");$st->execute([$editId]);$edit=$st->fetch();}

$pics=$pdo->query("SELECT id,nama FROM pic WHERE aktif=1 ORDER BY nama")->fetchAll();
$eps=$pdo->query("SELECT id,kode,judul FROM elemen_penilaian ORDER BY urutan")->fetchAll();

$indikators=$pdo->query("
 SELECT i.*,p.nama pic_nama,
   (SELECT COUNT(*) FROM mutu_capaian c WHERE c.indikator_id=i.id) jumlah_periode,
   (SELECT c.status FROM mutu_capaian c WHERE c.indikator_id=i.id ORDER BY c.periode DESC LIMIT 1) status_terakhir,
   (SELECT c.capaian FROM mutu_capaian c WHERE c.indikator_id=i.id ORDER BY c.periode DESC LIMIT 1) capaian_terakhir
 FROM mutu_indikator i LEFT JOIN pic p ON p.id=i.pic_id
 WHERE i.aktif=1 ORDER BY i.kode")->fetchAll();

$selectedMap=[];
$mapId=(int)($_GET['map']??0);
if($mapId){
 $st=$pdo->prepare("SELECT ep_id FROM mutu_indikator_ep WHERE indikator_id=?");$st->execute([$mapId]);
 foreach($st as $r)$selectedMap[]=(int)$r['ep_id'];
}

$editCapaianId=(int)($_GET['edit_capaian']??0);
$editCapaian=null;
if($editCapaianId){
 $st=$pdo->prepare("SELECT * FROM mutu_capaian WHERE id=?");
 $st->execute([$editCapaianId]); $editCapaian=$st->fetch();
}

$year=(int)($_GET['tahun']??date('Y'));
if($year<2020 || $year>2100) $year=(int)date('Y');

$monthNames=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$byMonth=[];

$heatmapData=[];
foreach($indikators as $ii){
    $st=$pdo->prepare("SELECT MONTH(periode) bulan,capaian,status FROM mutu_capaian WHERE indikator_id=? AND YEAR(periode)=? ORDER BY periode");
    $st->execute([(int)$ii['id'],$year]);
    $m=[];
    foreach($st as $rr) $m[(int)$rr['bulan']]=['capaian'=>$rr['capaian'],'status'=>$rr['status']];
    $heatmapData[(int)$ii['id']]=$m;
}

$detailId=(int)($_GET['detail']??0);
$detail=null;$rows=[];
if($detailId){
 $st=$pdo->prepare("SELECT i.*,p.nama pic_nama FROM mutu_indikator i LEFT JOIN pic p ON p.id=i.pic_id WHERE i.id=?");
 $st->execute([$detailId]);$detail=$st->fetch();
 if($detail){
   $st=$pdo->prepare("SELECT c.*,DATE_FORMAT(c.periode,'%Y-%m') periode_label FROM mutu_capaian c WHERE c.indikator_id=? ORDER BY c.periode DESC");
   $st->execute([$detailId]);$rows=$st->fetchAll();
   $byMonth=[];
   foreach($rows as $rr){ $bulan=(int)date('n',strtotime($rr['periode'])); if((int)date('Y',strtotime($rr['periode']))===$year) $byMonth[$bulan]=$rr; }
   $buktiByCapaian=[];
   if($rows){ $ids=array_map(fn($r)=>(int)$r['id'],$rows); $ph=implode(',',array_fill(0,count($ids),'?')); $bs=$pdo->prepare("SELECT * FROM mutu_bukti WHERE capaian_id IN ($ph) ORDER BY created_at DESC"); $bs->execute($ids); foreach($bs as $b)$buktiByCapaian[(int)$b['capaian_id']][]=$b; }
 }
}

require __DIR__.'/../partials/header.php';
?>
<style>
.mutu-hero{background:linear-gradient(135deg,#123f34,#198754);color:#fff;border-radius:18px;padding:22px;margin-bottom:18px}
.mutu-card{border:0;border-radius:16px}
.status-pill{display:inline-block;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:700}
.status-tercapai{background:#198754;color:#fff}.status-perhatian{background:#ffc107;color:#212529}.status-tidak{background:#dc3545;color:#fff}.status-belum{background:#6c757d;color:#fff}
.kpi-number{font-size:26px;font-weight:800}
.progress-mini{height:8px}
.mutu-kpi{border:0;border-radius:16px;box-shadow:0 2px 10px rgba(0,0,0,.06)}
.heatmap{display:grid;grid-template-columns:minmax(220px,1.6fr) repeat(12,minmax(34px,1fr));gap:3px;align-items:stretch}
.heatmap>div{padding:7px 5px;text-align:center;font-size:12px;border-radius:5px}
.hm-head{font-weight:700;background:#f1f3f5}.hm-name{text-align:left!important;font-weight:600;background:#f8f9fa}
.hm-ok{background:#198754;color:#fff}.hm-bad{background:#dc3545;color:#fff}.hm-warn{background:#ffc107;color:#212529}.hm-empty{background:#e9ecef;color:#6c757d}
.chart-wrap{position:relative;height:310px}.monthly-input-table{min-width:1100px}.monthly-input-table th{white-space:nowrap}.monthly-input-table input{min-width:95px}.monthly-input-table td:nth-child(7),.monthly-input-table td:nth-child(8){min-width:180px}.profile-label{font-size:12px;color:#6c757d;text-transform:uppercase;font-weight:700}.profile-value{font-weight:600}
@media(max-width:900px){.heatmap{overflow-x:auto;grid-template-columns:180px repeat(12,45px);min-width:760px}}

</style>

<div class="mutu-hero">
 <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
  <div><div class="small opacity-75">MRMIKIT · MUTU TEKNOLOGI INFORMASI</div><h2 class="mb-1">Indikator Mutu IT</h2><div>Kelola indikator, capaian bulanan, analisis, tindak lanjut, dan pemetaan ke EP MRMIK.</div></div>
  <div class="text-end"><div class="small opacity-75">Total indikator</div><div class="display-6 fw-bold"><?=count($indikators)?></div></div>
 </div>
</div>

<?php if($msg):?><div class="alert alert-success"><?=h($msg)?></div><?php endif;?>
<?php if($err):?><div class="alert alert-danger"><?=h($err)?></div><?php endif;?>

<div class="alert alert-warning"><strong>Catatan:</strong> indikator dan target di modul ini adalah indikator mutu internal IT. Target harus ditetapkan/disahkan oleh RS sesuai kebijakan dan metode pengukuran yang berlaku; jangan menganggap angka contoh sebagai target nasional.</div>

<div class="card shadow-sm mutu-card mb-4 mutu-kpi"><div class="card-body">
 <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div><h5 class="mb-1">Dashboard Mutu IT</h5><div class="small text-muted">Ringkasan capaian indikator tahun <?=h($year)?></div></div>
  <form class="d-flex gap-2" method="get"><input type="hidden" name="detail" value="<?=h($detailId)?>"><select name="tahun" class="form-select form-select-sm" onchange="this.form.submit()"><?php for($yy=date('Y')-2;$yy<=date('Y')+1;$yy++):?><option value="<?=$yy?>" <?=$year===$yy?'selected':''?>><?=$yy?></option><?php endfor;?></select></form>
 </div>
 <div class="row g-3 mb-4">
  <?php $tot=count($indikators);$ter=0;$tid=0;$bel=0;foreach($indikators as $ix){if(($ix['status_terakhir']??'')==='tercapai')$ter++;elseif(($ix['status_terakhir']??'')==='tidak_tercapai')$tid++;else $bel++;}?>
  <div class="col-md-3"><div class="p-3 bg-light rounded-3"><div class="small text-muted">Total indikator</div><div class="kpi-number"><?=$tot?></div></div></div>
  <div class="col-md-3"><div class="p-3 rounded-3" style="background:#e8f5ee"><div class="small text-muted">Tercapai</div><div class="kpi-number text-success"><?=$ter?></div></div></div>
  <div class="col-md-3"><div class="p-3 rounded-3" style="background:#fff3cd"><div class="small text-muted">Tidak tercapai</div><div class="kpi-number text-danger"><?=$tid?></div></div></div>
  <div class="col-md-3"><div class="p-3 rounded-3" style="background:#eef1f3"><div class="small text-muted">Belum dinilai</div><div class="kpi-number text-secondary"><?=$bel?></div></div></div>
 </div>
 <h6 class="mb-3">Heatmap capaian <?=h($year)?></h6>
 <div class="table-responsive"><div class="heatmap">
  <div class="hm-head text-start">Indikator</div><?php foreach(['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'] as $mn):?><div class="hm-head"><?=$mn?></div><?php endforeach;?>
  <?php foreach($indikators as $ii):?><div class="hm-name"><?=h($ii['kode'])?><br><span class="small text-muted"><?=h($ii['nama'])?></span></div><?php for($mm=1;$mm<=12;$mm++):$hm=$heatmapData[(int)$ii['id']][$mm]??null;$hs=$hm['status']??'';$hc=$hs==='tercapai'?'hm-ok':($hs==='tidak_tercapai'?'hm-bad':($hs==='perlu_perhatian'?'hm-warn':'hm-empty'));?><div class="<?=$hc?>" title="<?=$hm&&$hm['capaian']!==null?h(round((float)$hm['capaian'],2).' '.$ii['satuan']):'Belum diinput'?>"><?=$hm&&$hm['capaian']!==null?h(round((float)$hm['capaian'],1)): '—'?></div><?php endfor;endforeach;?>
 </div></div>
 <div class="small text-muted mt-2">🟢 tercapai · 🟡 perlu perhatian · 🔴 tidak tercapai · ⚪ belum ada capaian.</div>
</div></div>

<div class="card shadow-sm mutu-card mb-4"><div class="card-body">
 <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"><h5 class="mb-0">Daftar Indikator</h5><button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#formIndikator">+ Tambah indikator</button></div>
 <div class="table-responsive"><table class="table align-middle">
 <thead><tr><th>Kode</th><th>Indikator</th><th>Target</th><th>PIC</th><th>Capaian terakhir</th><th>Status</th><th>Aksi</th></tr></thead>
 <tbody>
 <?php foreach($indikators as $i): ?>
 <tr>
  <td><strong><?=h($i['kode'])?></strong></td><td><?=h($i['nama'])?><div class="small text-muted"><?=$i['jumlah_periode']?> periode</div></td>
  <td><?= $i['target']!==null ? h($i['target']).' '.h($i['satuan']) : '<span class="text-muted">Belum diisi</span>'?></td>
  <td><?=h($i['pic_nama']??'-')?></td>
  <td><?= $i['capaian_terakhir']!==null ? h(round((float)$i['capaian_terakhir'],2)).' '.h($i['satuan']) : '-'?></td>
  <td><?php $s=$i['status_terakhir']??'belum_dinilai';?><span class="status-pill <?=($s==='tercapai'?'status-tercapai':($s==='tidak_tercapai'?'status-tidak':($s==='perlu_perhatian'?'status-perhatian':'status-belum')))?>"><?=strtoupper(str_replace('_',' ',$s))?></span></td>
  <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="?detail=<?=$i['id']?>">Capaian</a> <a class="btn btn-sm btn-outline-success" href="?map=<?=$i['id']?>">EP</a> <a class="btn btn-sm btn-outline-secondary" href="?edit=<?=$i['id']?>">Edit</a></td>
 </tr>
 <?php endforeach;?>
 </tbody></table></div>
</div></div>

<div class="collapse <?=($edit?'show':'')?>" id="formIndikator"><div class="card shadow-sm mutu-card mb-4"><div class="card-body">
<h5><?= $edit?'Edit indikator':'Tambah indikator baru'?></h5>
<form method="post"><input type="hidden" name="action" value="add_indicator"><input type="hidden" name="edit_id" value="<?=h($edit['id']??0)?>">
<div class="row g-3">
<div class="col-md-3"><label class="form-label">Kode</label><input name="kode" class="form-control" required value="<?=h($edit['kode']??'')?>"></div>
<div class="col-md-9"><label class="form-label">Nama indikator</label><input name="nama" class="form-control" required value="<?=h($edit['nama']??'')?>"></div>
<div class="col-md-6"><label class="form-label">Definisi operasional</label><textarea name="definisi_operasional" class="form-control" rows="3"><?=h($edit['definisi_operasional']??'')?></textarea></div>
<div class="col-md-3"><label class="form-label">Target</label><input type="number" step="0.0001" name="target" class="form-control" value="<?=h($edit['target']??'')?>"></div>
<div class="col-md-3"><label class="form-label">Satuan</label><input name="satuan" class="form-control" value="<?=h($edit['satuan']??'%')?>"></div>
<div class="col-md-3"><label class="form-label">Numerator</label><input name="numerator_label" class="form-control" value="<?=h($edit['numerator_label']??'')?>"></div>
<div class="col-md-3"><label class="form-label">Denominator</label><input name="denominator_label" class="form-control" value="<?=h($edit['denominator_label']??'')?>"></div>
<div class="col-md-6"><label class="form-label">Formula</label><input name="formula" class="form-control" placeholder="Contoh: numerator / denominator × 100" value="<?=h($edit['formula']??'')?>"></div>
<div class="col-md-3"><label class="form-label">Arah target</label><select name="arah" class="form-select"><option value="naik" <?=($edit['arah']??'')==='naik'?'selected':''?>>Semakin tinggi semakin baik</option><option value="turun" <?=($edit['arah']??'')==='turun'?'selected':''?>>Semakin rendah semakin baik</option><option value="sesuai_target" <?=($edit['arah']??'')==='sesuai_target'?'selected':''?>>Sesuai target</option></select></div>
<div class="col-md-3"><label class="form-label">Frekuensi</label><select name="frekuensi" class="form-select"><?php foreach(['bulanan','triwulan','semester','tahunan'] as $f):?><option <?=($edit['frekuensi']??'bulanan')===$f?'selected':''?>><?=$f?></option><?php endforeach;?></select></div>
<div class="col-md-4"><label class="form-label">Sumber data</label><input name="sumber_data" class="form-control" value="<?=h($edit['sumber_data']??'')?>" placeholder="SIMRS, monitoring server, tiket IT..."></div>
<div class="col-md-5"><label class="form-label">Metode pengumpulan</label><input name="metode_pengumpulan" class="form-control" value="<?=h($edit['metode_pengumpulan']??'')?>"></div>
<div class="col-md-3"><label class="form-label">PIC</label><select name="pic_id" class="form-select"><option value="">- pilih -</option><?php foreach($pics as $p):?><option value="<?=$p['id']?>" <?=((string)($edit['pic_id']??'')===(string)$p['id'])?'selected':''?>><?=h($p['nama'])?></option><?php endforeach;?></select></div>
</div><button class="btn btn-success mt-3">Simpan indikator</button></form>
</div></div></div>

<?php if($mapId): ?>
<div class="card shadow-sm mutu-card mb-4"><div class="card-body"><h5>Pemetaan indikator ke EP MRMIK</h5>
<form method="post"><input type="hidden" name="action" value="map_ep"><input type="hidden" name="indikator_id" value="<?=$mapId?>">
<div class="row"><?php foreach($eps as $e):?><div class="col-md-6 col-lg-4 mb-2"><label class="border rounded p-2 d-block"><input type="checkbox" name="ep_ids[]" value="<?=$e['id']?>" <?=in_array((int)$e['id'],$selectedMap,true)?'checked':''?>> <strong><?=h($e['kode'])?></strong><br><small><?=h($e['judul'])?></small></label></div><?php endforeach;?></div>
<button class="btn btn-success mt-2">Simpan Pemetaan</button></form></div></div>
<?php endif;?>

<?php if($detail) { ?>
<div class="card shadow-sm mutu-card mb-4"><div class="card-body">
<h5><?= $editCapaian ? 'Edit capaian' : 'Tambah capaian' ?>: <?=h($detail['kode'])?> — <?=h($detail['nama'])?></h5>
<div class="row g-3 mb-4">
 <div class="col-md-3"><div class="card mutu-kpi h-100"><div class="card-body"><div class="profile-label">Target</div><div class="fs-4 fw-bold"><?= $detail['target']!==null?h($detail['target'].' '.$detail['satuan']):'-'?></div></div></div></div>
 <div class="col-md-3"><div class="card mutu-kpi h-100"><div class="card-body"><div class="profile-label">PIC</div><div class="profile-value"><?=h($detail['pic_nama']??'-')?></div></div></div></div>
 <div class="col-md-3"><div class="card mutu-kpi h-100"><div class="card-body"><div class="profile-label">Frekuensi</div><div class="profile-value"><?=h($detail['frekuensi'])?></div></div></div></div>
 <div class="col-md-3 d-flex align-items-stretch"><a class="btn btn-outline-danger w-100 d-flex align-items-center justify-content-center" target="_blank" href="laporan.php?id=<?=$detail['id']?>&tahun=<?=$year?>">📄 Cetak Laporan / PDF</a></div>
</div>
<div class="card border-0 bg-light mb-4"><div class="card-body">
 <div class="row g-3">
  <div class="col-md-6"><div class="profile-label">Definisi operasional</div><div><?=nl2br(h($detail['definisi_operasional']??'-'))?></div></div>
  <div class="col-md-3"><div class="profile-label">Formula</div><div><?=h($detail['formula']??'N / D × 100')?></div></div>
  <div class="col-md-3"><div class="profile-label">Sumber data</div><div><?=h($detail['sumber_data']??'-')?></div></div>
  <div class="col-md-6"><div class="profile-label">Numerator</div><div><?=h($detail['numerator_label']??'-')?></div></div>
  <div class="col-md-6"><div class="profile-label">Denominator</div><div><?=h($detail['denominator_label']??'-')?></div></div>
 </div>
</div></div>
<div class="card shadow-sm mb-4"><div class="card-body">
 <div class="d-flex justify-content-between align-items-center"><h6 class="mb-0">Grafik tren 12 bulan <?=h($year)?></h6><span class="small text-muted">Garis target = <?=h($detail['target']??'-')?> <?=h($detail['satuan'])?></span></div>
 <div class="chart-wrap mt-2"><canvas id="trendChart" aria-label="Grafik tren capaian 12 bulan"></canvas></div>
</div></div>

<?php if(($detail['kode']??'')==='IM-IT-01'): ?>
<div class="card border-success shadow-sm mb-4"><div class="card-body">
 <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div><h6 class="mb-1">⚙️ Hitung otomatis dari Downtime SIMRS</h6>
   <div class="small text-muted">Ambil semua catatan Downtime yang tercatat di menu Downtime, potong otomatis jika melewati batas bulan, lalu hitung waktu tersedia dan persentase ketersediaan.</div>
  </div>
<div class="d-flex gap-2 flex-wrap">
   <a class="btn btn-outline-success" href="../downtime/tambah.php">➕ Catat Downtime</a>
   <form method="post" class="m-0" onsubmit="return confirm('Hitung ulang IM-IT-01 dari log Downtime untuk tahun <?=h($year)?>? Hanya bulan yang memiliki catatan downtime yang akan dihitung.');">
    <input type="hidden" name="action" value="hitung_ketersediaan_simrs">
    <input type="hidden" name="indikator_id" value="<?=$detail['id']?>">
    <input type="hidden" name="tahun" value="<?=$year?>">
    <button class="btn btn-success">🔄 Hitung dari Downtime</button>
   </form>
  </div>
 </div>
 <div class="alert alert-warning mt-3 mb-0 small">Untuk <strong>Ada Downtime</strong>, catat semua kejadian di menu <strong>Downtime</strong>. Untuk <strong>Tidak Ada Downtime</strong>, pilih hanya setelah diverifikasi dengan bukti monitoring/log/laporan IT. <strong>Belum Ada Data</strong> dibiarkan kosong.</div>
</div></div>
<?php endif; ?>

<div class="card shadow-sm mb-4"><div class="card-body">
 <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div><h6 class="mb-1">Input Capaian 12 Bulan <?=h($year)?></h6><div class="small text-muted">Untuk IM-IT-01 pilih kondisi setiap bulan: <strong>Ada Downtime</strong>, <strong>Tidak Ada Downtime</strong>, atau <strong>Belum Ada Data</strong>. N/D dan capaian dihitung otomatis.</div></div>
  <button type="submit" form="formCapaian12" class="btn btn-success">💾 Simpan Semua Capaian</button>
 </div>
 <form method="post" id="formCapaian12">
  <input type="hidden" name="action" value="save_capaian_bulanan">
  <input type="hidden" name="indikator_id" value="<?=$detail['id']?>">
  <input type="hidden" name="tahun" value="<?=$year?>">
  <div class="table-responsive mt-3">
   <table class="table table-bordered table-sm align-middle monthly-input-table">
    <thead><tr><th>Bulan</th><?php if(($detail['kode']??'')==='IM-IT-01'): ?><th>Kondisi</th><?php endif; ?><th>N</th><th>D</th><th>Capaian</th><th>Target</th><th>Status</th><th>Analisis</th><th>Tindak lanjut</th></tr></thead>
    <tbody>
     <?php for($mm=1;$mm<=12;$mm++): $rr=$byMonth[$mm]??null; $ss=$rr['status']??'belum_dinilai'; ?>
     <?php
       $kondisiLama='';
       if(($detail['kode']??'')==='IM-IT-01' && $rr){
         $hasData=($rr['capaian']!==null || $rr['numerator']!==null || $rr['denominator']!==null);
         if($hasData) $kondisiLama=(abs((float)$rr['capaian']-100)<0.0001 && abs((float)$rr['numerator']-(float)$rr['denominator'])<0.0001) ? 'tidak_ada_downtime' : 'ada_downtime';
       }
     ?>
     <tr class="<?=($ss==='tercapai'?'table-success':($ss==='tidak_tercapai'?'table-danger':''))?>">
       <td><strong><?=h($monthNames[$mm-1])?></strong><div class="small text-muted"><?=$year?>-<?=str_pad($mm,2,'0',STR_PAD_LEFT)?></div></td>
       <?php if(($detail['kode']??'')==='IM-IT-01'): ?>
       <td><select class="form-select form-select-sm" name="bulanan[<?=$mm?>][kondisi]">
         <option value="" <?=$kondisiLama===''?'selected':''?>>-- pilih kondisi --</option>
         <option value="ada_downtime" <?=$kondisiLama==='ada_downtime'?'selected':''?>>🔴 Ada Downtime</option>
         <option value="tidak_ada_downtime" <?=$kondisiLama==='tidak_ada_downtime'?'selected':''?>>🟢 Tidak Ada Downtime</option>
         <option value="belum_ada_data">⚪ Belum Ada Data</option>
       </select></td>
       <?php endif; ?>
       <td><input type="number" step="0.0001" class="form-control form-control-sm month-num" name="bulanan[<?=$mm?>][numerator]" value="<?=h($rr['numerator']??'')?>" placeholder="otomatis"></td>
       <td><input type="number" step="0.0001" class="form-control form-control-sm month-den" name="bulanan[<?=$mm?>][denominator]" value="<?=h($rr['denominator']??'')?>" placeholder="otomatis"></td>
       <td><input type="number" step="0.0001" class="form-control form-control-sm month-cap" name="bulanan[<?=$mm?>][capaian]" value="<?=h($rr['capaian']??'')?>" placeholder="otomatis"></td>
       <td><input type="number" step="0.0001" class="form-control form-control-sm" name="bulanan[<?=$mm?>][target]" value="<?=h($rr['target_snapshot']??$detail['target']??'')?>"></td>
       <td><span class="status-pill <?=($ss==='tercapai'?'status-tercapai':($ss==='tidak_tercapai'?'status-tidak':($ss==='perlu_perhatian'?'status-perhatian':'status-belum')))?>"><?=h(strtoupper(str_replace('_',' ',$ss)))?></span></td>
       <td><input type="text" class="form-control form-control-sm" name="bulanan[<?=$mm?>][analisis]" value="<?=h($rr['analisis']??'')?>" placeholder="Analisis singkat"></td>
       <td><input type="text" class="form-control form-control-sm" name="bulanan[<?=$mm?>][tindak_lanjut]" value="<?=h($rr['tindak_lanjut']??'')?>" placeholder="RTL"></td>
     </tr>
    </tbody>
   </table>
  </div>
 </form>
</div></div>

<div class="card shadow-sm mb-4"><div class="card-body">
 <h6>Tabel Capaian 12 Bulan <?=h($year)?></h6>
 <div class="table-responsive">
  <table class="table table-sm align-middle">
   <thead><tr><th>Bulan</th><th>Numerator</th><th>Denominator</th><th>Capaian</th><th>Target</th><th>Status</th><th>Analisis / RTL</th></tr></thead>
   <tbody>
   <?php for($mm=1;$mm<=12;$mm++): $rr=$byMonth[$mm]??null; $ss=$rr['status']??'belum_dinilai'; ?>
   <tr>
    <td><strong><?=h($monthNames[$mm-1])?></strong></td>
    <td><?=$rr?h($rr['numerator']):'—'?></td>
    <td><?=$rr?h($rr['denominator']):'—'?></td>
    <td><strong><?=$rr&&$rr['capaian']!==null?h(round((float)$rr['capaian'],2).' '.$detail['satuan']):'—'?></strong></td>
    <td><?=$rr?h($rr['target_snapshot']):h($detail['target']??'—')?></td>
    <td><span class="status-pill <?=($ss==='tercapai'?'status-tercapai':($ss==='tidak_tercapai'?'status-tidak':($ss==='perlu_perhatian'?'status-perhatian':'status-belum')))?>"><?=h(strtoupper(str_replace('_',' ',$ss)))?></span></td>
     <td><?= $rr ? h(trim(($rr['analisis']??'').' '.($rr['tindak_lanjut']??''))) : '—' ?></td>
   </tr>
   <?php endfor; ?>
   </tbody>
  </table>
 </div>
</div></div>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Periode</th><th>N</th><th>D</th><th>Capaian</th><th>Target</th><th>Status</th><th>Analisis / Tindak lanjut</th><th>Aksi</th><th>Bukti</th></tr></thead><tbody>
<?php foreach($rows as $r):$s=$r['status'];?><tr><td><?=h($r['periode_label'])?></td><td><?=h($r['numerator'])?></td><td><?=h($r['denominator'])?></td><td><strong><?= $r['capaian']!==null?h(round((float)$r['capaian'],2).' '.$detail['satuan']):'-'?></strong></td><td><?=h($r['target_snapshot'])?></td><td><span class="status-pill <?=($s==='tercapai'?'status-tercapai':($s==='tidak_tercapai'?'status-tidak':'status-belum'))?>"><?=h(strtoupper(str_replace('_',' ',$s)))?></span></td><td><div><?=h($r['analisis']??'-')?></div><small class="text-muted"><?=h($r['tindak_lanjut']??'')?></small></td>
<td class="text-nowrap">
 <a class="btn btn-sm btn-outline-primary mb-1" href="#formCapaian12">Edit di tabel 12 bulan</a>
 <form method="post" class="d-inline" onsubmit="return confirm('Hapus capaian periode <?=h($r['periode_label'])?> beserta bukti yang terhubung?');">
  <input type="hidden" name="action" value="delete_capaian"><input type="hidden" name="capaian_id" value="<?=$r['id']?>">
  <button class="btn btn-sm btn-outline-danger mb-1">Hapus</button>
 </form>
</td>
<td style="min-width:260px">
 <?php foreach(($buktiByCapaian[(int)$r['id']]??[]) as $b): ?>
   <div class="mb-1"><a href="download.php?id=<?=$b['id']?>" target="_blank"><?=h($b['original_name'])?></a> <small class="text-muted">(<?=round($b['size_bytes']/1024,1)?> KB)</small></div>
 <?php endforeach; ?>
 <form method="post" enctype="multipart/form-data" class="mt-2">
  <input type="hidden" name="action" value="upload_bukti"><input type="hidden" name="capaian_id" value="<?=$r['id']?>">
  <input type="file" name="bukti_file" class="form-control form-control-sm mb-1" required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.zip">
  <input type="text" name="catatan_bukti" class="form-control form-control-sm mb-1" placeholder="Catatan bukti (opsional)">
  <button class="btn btn-sm btn-outline-success">📎 Upload bukti</button>
 </form>
</td></tr><?php endforeach;?>
</tbody></table></div>
</div></div>

<?php } ?>

<script>
(function(){
 const canvas=document.getElementById('trendChart');
 if(!canvas) return;
 const ctx=canvas.getContext('2d');
 const labels=<?=json_encode($monthNames,JSON_UNESCAPED_UNICODE)?>;
 const data=<?=json_encode(array_map(function($m)use($byMonth){$r=$byMonth[$m]??null;return $r&&$r['capaian']!==null?(float)$r['capaian']:null;},range(1,12)))?>;
 const target=<?=json_encode($detail['target']!==null?(float)$detail['target']:null)?>;
 const dpr=window.devicePixelRatio||1;
 function draw(){
   const w=canvas.clientWidth||900,h=canvas.clientHeight||310;
   canvas.width=w*dpr;canvas.height=h*dpr;ctx.setTransform(dpr,0,0,dpr,0,0);ctx.clearRect(0,0,w,h);
   const pad={l:48,r:18,t:22,b:42},pw=w-pad.l-pad.r,ph=h-pad.t-pad.b,vals=data.filter(v=>v!==null);
   if(!vals.length){ctx.font='14px Arial';ctx.fillStyle='#6c757d';ctx.fillText('Belum ada capaian untuk tahun ini.',pad.l,pad.t+30);return;}
   let min=Math.min(...vals,target??Infinity),max=Math.max(...vals,target??-Infinity);if(!Number.isFinite(min))min=0;if(!Number.isFinite(max))max=100;
   const rg=Math.max(max-min,1);min-=rg*.12;max+=rg*.12;const x=i=>pad.l+(pw*i/11),y=v=>pad.t+(max-v)*ph/(max-min);
   ctx.strokeStyle='#e9ecef';ctx.lineWidth=1;
   for(let g=0;g<=4;g++){const yy=pad.t+ph*g/4;ctx.beginPath();ctx.moveTo(pad.l,yy);ctx.lineTo(w-pad.r,yy);ctx.stroke();ctx.fillStyle='#6c757d';ctx.font='11px Arial';ctx.fillText((max-(max-min)*g/4).toFixed(1),5,yy+4);}
   if(target!==null){ctx.strokeStyle='#dc3545';ctx.setLineDash([6,5]);ctx.beginPath();ctx.moveTo(pad.l,y(target));ctx.lineTo(w-pad.r,y(target));ctx.stroke();ctx.setLineDash([]);}
   ctx.strokeStyle='#198754';ctx.lineWidth=3;ctx.beginPath();let started=false;
   data.forEach((v,i)=>{if(v===null){started=false;return;}const xx=x(i),yy=y(v);if(!started){ctx.moveTo(xx,yy);started=true;}else ctx.lineTo(xx,yy);});ctx.stroke();
   data.forEach((v,i)=>{if(v===null)return;const xx=x(i),yy=y(v);ctx.fillStyle='#198754';ctx.beginPath();ctx.arc(xx,yy,4,0,Math.PI*2);ctx.fill();ctx.fillStyle='#212529';ctx.font='11px Arial';ctx.textAlign='center';ctx.fillText(v.toFixed(2)+'%',xx,yy-9);});
   ctx.fillStyle='#495057';ctx.font='11px Arial';labels.forEach((lab,i)=>{ctx.textAlign='center';ctx.fillText(lab.slice(0,3),x(i),h-15);});
 }
 draw();window.addEventListener('resize',draw);
})();
document.querySelectorAll('#formCapaian12 tr').forEach(function(row){
 const n=row.querySelector('.month-num'),d=row.querySelector('.month-den'),cap=row.querySelector('.month-cap');if(!n||!d||!cap)return;
 function calc(){if(n.value!==''&&d.value!==''&&Number(d.value)!==0)cap.value=(Number(n.value)/Number(d.value)*100).toFixed(4);}
 n.addEventListener('input',calc);d.addEventListener('input',calc);
});
</script>

<?php require __DIR__.'/../partials/footer.php';