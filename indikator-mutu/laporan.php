<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function fmt($v,$dec=2){return number_format((float)$v,$dec,',','.');}

$id=(int)($_GET['id']??0);
$year=(int)($_GET['tahun']??date('Y'));
if($year<2020||$year>2100)$year=(int)date('Y');

$st=$pdo->prepare("SELECT i.*,p.nama pic_nama FROM mutu_indikator i LEFT JOIN pic p ON p.id=i.pic_id WHERE i.id=? AND i.aktif=1");
$st->execute([$id]);$i=$st->fetch();
if(!$i){http_response_code(404);exit('Indikator tidak ditemukan.');}

$monthNames=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

/* Data capaian tersimpan. */
$st=$pdo->prepare("SELECT c.* FROM mutu_capaian c WHERE c.indikator_id=? AND YEAR(c.periode)=? ORDER BY c.periode");
$st->execute([$id,$year]);$rows=$st->fetchAll();
$byMonth=[];foreach($rows as $r)$byMonth[(int)date('n',strtotime($r['periode']))]=$r;

/* Untuk IM-IT-01 dan IM-IT-02, sumber utama laporan adalah tabel downtime yang sama. */
$downtimeByMonth=[];
$downtimeRows=[];
if(in_array($i['kode'],['IM-IT-01','IM-IT-02'],true)){
    $st=$pdo->prepare("SELECT d.*,p.nama pic_nama FROM downtime d LEFT JOIN pic p ON p.id=d.pic_id WHERE d.selesai IS NOT NULL AND d.selesai>d.mulai AND d.mulai < ? AND d.selesai > ? ORDER BY d.mulai");
    $st->execute([sprintf('%04d-12-31 23:59:59',$year),sprintf('%04d-01-01 00:00:00',$year)]);
    $downtimeRows=$st->fetchAll();

    for($m=1;$m<=12;$m++){
        $start=new DateTime(sprintf('%04d-%02d-01 00:00:00',$year,$m));
        $end=(clone $start)->modify('+1 month');
        $intervals=[];$eventDetails=[];
        foreach($downtimeRows as $ev){
            $ds=new DateTime($ev['mulai']);$de=new DateTime($ev['selesai']);
            $cs=$ds>$start?$ds:$start;$ce=$de<$end?$de:$end;
            if($ce>$cs){
                $sec=$ce->getTimestamp()-$cs->getTimestamp();
                $intervals[]=[$cs->getTimestamp(),$ce->getTimestamp()];
                $eventDetails[]=['event'=>$ev,'durasi_menit'=>$sec/60];
            }
        }
        usort($intervals,fn($a,$b)=>$a[0]<=>$b[0]);
        $merged=[];
        foreach($intervals as $iv){
            if(!$merged||$iv[0]>$merged[count($merged)-1][1])$merged[]=$iv;
            else $merged[count($merged)-1][1]=max($merged[count($merged)-1][1],$iv[1]);
        }
        $downSeconds=0;foreach($merged as $iv)$downSeconds+=$iv[1]-$iv[0];
        $startToday=new DateTime('today');
        $completed=($end<=$startToday);
        $totalSeconds=$end->getTimestamp()-$start->getTimestamp();
        $totalHours=$totalSeconds/3600;
        $downMinutes=round($downSeconds/60,2);
        if($i['kode']==='IM-IT-02'){
            $value=($downSeconds>0||$completed)?$downMinutes:null;
            $status=$value===null?'belum_dinilai':($i['target']===null?'belum_dinilai':($value<=(float)$i['target']?'tercapai':'tidak_tercapai'));
            $downtimeByMonth[$m]=['capaian'=>$value,'status'=>$status,'events'=>$eventDetails,'downtime_minutes'=>$downMinutes,'numerator'=>$value,'denominator'=>count($eventDetails)];
        }else{
            $value=($downSeconds>0||$completed)?round(max(0,($totalSeconds-$downSeconds)/$totalSeconds)*100,4):null;
            $status=$value===null?'belum_dinilai':(($i['target']===null||(float)$value>=(float)$i['target'])?'tercapai':'tidak_tercapai');
            $downtimeByMonth[$m]=['capaian'=>$value,'status'=>$status,'events'=>$eventDetails,'downtime_minutes'=>$downMinutes,'numerator'=>$value===null?null:round(max(0,$totalHours-($downSeconds/3600)),4),'denominator'=>$value===null?null:round($totalHours,4)];
        }
    }
}

/* Bukti yang sudah diunggah pada capaian. */
$bukti=[];
$ids=array_map(fn($r)=>(int)$r['id'],$rows);
if($ids){
    $ph=implode(',',array_fill(0,count($ids),'?'));
    $st=$pdo->prepare("SELECT b.*,c.periode FROM mutu_bukti b JOIN mutu_capaian c ON c.id=b.capaian_id WHERE b.capaian_id IN ($ph) ORDER BY c.periode,b.created_at");
    $st->execute($ids);$bukti=$st->fetchAll();
}

$ep=[];
$st=$pdo->prepare("SELECT e.kode,e.judul FROM mutu_indikator_ep m JOIN elemen_penilaian e ON e.id=m.ep_id WHERE m.indikator_id=? ORDER BY e.urutan");
$st->execute([$id]);$ep=$st->fetchAll();

/* Bukti perwakilan IM-IT-03: satu offline dan satu online untuk seluruh tahun laporan. */
$backupEvidence=[];
if($i['kode']==='IM-IT-03'){
    $est=$pdo->prepare("SELECT * FROM mutu_backup_bukti WHERE indikator_id=? AND tahun=? ORDER BY FIELD(jenis,'offline','online')");
    $est->execute([$id,$year]);
    $backupEvidence=$est->fetchAll();
}

/* Bukti pendukung downtime diambil LANGSUNG dari tabel downtime.
 * File yang diunggah pada menu Downtime tersimpan pada:
 * uploads/downtime/ dan direferensikan oleh downtime.bukti_filename.
 * Tidak menggunakan mutu_bukti karena itu adalah bukti capaian indikator.
 */
$downtimeEvidence=[];
if(in_array($i['kode'],['IM-IT-01','IM-IT-02'],true)){
    foreach($downtimeRows as $ev){
        if(!empty($ev['bukti_filename'])){
            $downtimeEvidence[]=$ev;
        }
    }
}

/* Data uji restore IM-IT-04 dan bukti-buktinya. */
$restoreRows=[];
$restoreEvidence=[];
if($i['kode']==='IM-IT-04'){
    $st=$pdo->prepare("SELECT r.*,p.nama pic_nama FROM mutu_restore_uji r LEFT JOIN pic p ON p.id=r.pic_id WHERE r.indikator_id=? AND YEAR(r.tanggal_uji)=? ORDER BY r.tanggal_uji,r.id");
    $st->execute([$id,$year]); $restoreRows=$st->fetchAll();
    $rids=array_map(fn($r)=>(int)$r['id'],$restoreRows);
    if($rids){
        $ph=implode(',',array_fill(0,count($rids),'?'));
        $st=$pdo->prepare("SELECT b.*,r.tanggal_uji FROM mutu_restore_bukti b JOIN mutu_restore_uji r ON r.id=b.restore_id WHERE b.restore_id IN ($ph) ORDER BY r.tanggal_uji,b.created_at");
        $st->execute($rids); $restoreEvidence=$st->fetchAll();
    }
}

/* Susun 12 bulan untuk laporan. */
$reportMonths=[];$tercapai=0;$tidak=0;$cnt=0;$sum=0;
for($m=1;$m<=12;$m++){
    $r=$byMonth[$m]??null;
    if(in_array($i['kode'],['IM-IT-01','IM-IT-02'],true) && isset($downtimeByMonth[$m]) && $downtimeByMonth[$m]['capaian']!==null){
        $auto=$downtimeByMonth[$m];
        $r=$r?:[];
        $r['numerator']=$auto['numerator']??null;
        $r['denominator']=$auto['denominator']??null;
        $r['capaian']=$auto['capaian'];
        $r['target_snapshot']=$i['target'];
        $r['status']=$auto['status'];
    }
    $reportMonths[$m]=$r;
    if($r&&$r['capaian']!==null){$sum+=(float)$r['capaian'];$cnt++;}
    if(($r['status']??'')==='tercapai')$tercapai++;
    if(($r['status']??'')==='tidak_tercapai')$tidak++;
}
$avg=$cnt?$sum/$cnt:null;

/* Grafik SVG: aman untuk dicetak ke PDF dari browser. */
$values=[];$labels=[];$statuses=[];$target=$i['target']!==null?(float)$i['target']:null;
for($m=1;$m<=12;$m++){ $labels[]=$monthNames[$m-1]; $values[]=$reportMonths[$m]['capaian']??null; $statuses[]=$reportMonths[$m]['status']??'belum_dinilai'; }
$valid=array_values(array_filter($values,fn($v)=>$v!==null));
$min=$valid?min($valid):0;$max=$valid?max($valid):100;
if($target!==null){$min=min($min,$target);$max=max($max,$target);}
$range=max(1,$max-$min);$min-=$range*.08;$max+=$range*.08;
$gx=70;$gy=35;$gw=850;$gh=300;
$pts=[];
for($m=0;$m<12;$m++){if($values[$m]===null){$pts[]=null;continue;}$x=$gx+($gw*$m/11);$y=$gy+$gh-($values[$m]-$min)*$gh/($max-$min);$pts[]=[round($x,1),round($y,1)];}
$lineParts=[];$areaParts=[];
foreach($pts as $idx=>$p){if($p){$lineParts[]=$p;}}
$poly='';$started=false;
foreach($pts as $p){if($p){$poly.=($started?' L ':'M ').$p[0].' '.$p[1];$started=true;}}
$first=null;$last=null;foreach($pts as $p){if($p){if($first===null)$first=$p;$last=$p;}}
$area=$poly&&$first&&$last?$poly.' L '.$last[0].' '.($gy+$gh).' L '.$first[0].' '.($gy+$gh).' Z':'';
$colors=['tercapai'=>'#198754','tidak_tercapai'=>'#dc3545','perlu_perhatian'=>'#f59f00','belum_dinilai'=>'#94a3b8'];
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>Laporan <?=h($i['kode'])?> - MRMIKIT</title>
<style>
@page{size:A4;margin:12mm}*{box-sizing:border-box}body{font-family:Arial,Helvetica,sans-serif;color:#17202a;font-size:10.5px;line-height:1.4;background:#fff}h1{font-size:20px;margin:0}h2{font-size:14px;margin:18px 0 7px;border-bottom:2px solid #198754;padding-bottom:5px}.head{border-bottom:3px solid #198754;padding-bottom:10px;margin-bottom:12px}.sub{color:#64748b}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:7px;margin:10px 0}.box{border:1px solid #d7dee5;border-radius:6px;padding:8px}.label{font-size:8px;text-transform:uppercase;color:#64748b}.value{font-size:14px;font-weight:bold;margin-top:2px}table{width:100%;border-collapse:collapse;margin-top:6px}th,td{border:1px solid #d7dee5;padding:5px;vertical-align:top}th{background:#eef5f1;text-align:left}.ok{background:#dff3e8;color:#137a49;font-weight:bold}.bad{background:#fde2e2;color:#b42318;font-weight:bold}.empty{background:#f1f3f5;color:#6b7280}.warn{background:#fff3cd;color:#8a6500;font-weight:bold}.small{font-size:8.5px;color:#64748b}.btnbar{margin-bottom:12px}.btn{background:#198754;color:white;border:0;border-radius:5px;padding:7px 11px;cursor:pointer}.chartbox{border:1px solid #d7dee5;border-radius:8px;padding:8px;background:#fbfdfc}.chartbox svg{width:100%;height:auto;display:block}.page-break{page-break-before:always}.footer{margin-top:16px;border-top:1px solid #ddd;padding-top:6px;font-size:8.5px;color:#64748b}.avoid-break{break-inside:avoid;page-break-inside:avoid}@media print{.btnbar{display:none}.chartbox{break-inside:avoid;page-break-inside:avoid}}
</style></head><body>
<div class="btnbar"><button class="btn" onclick="window.print()">🖨 Cetak / Simpan sebagai PDF</button> <button class="btn" onclick="window.close()">Tutup</button></div>
<div class="head"><div class="sub">MRMIKIT · MUTU TEKNOLOGI INFORMASI</div><h1>Laporan Indikator Mutu IT</h1><div><strong><?=h($i['kode'])?> — <?=h($i['nama'])?></strong> · Tahun <?=h($year)?></div></div>

<div class="grid">
<div class="box"><div class="label">Target</div><div class="value"><?=h($i['target']??'-')?> <?=h($i['satuan'])?></div></div>
<div class="box"><div class="label">Rata-rata capaian</div><div class="value"><?=$avg!==null?h(fmt($avg,2).' '.$i['satuan']):'-'?></div></div>
<div class="box"><div class="label">Bulan tercapai</div><div class="value"><?=$tercapai?></div></div>
<div class="box"><div class="label">Bulan tidak tercapai</div><div class="value"><?=$tidak?></div></div>
</div>

<h2>1. Profil Indikator</h2>
<table><tr><th width="20%">Kode</th><td><?=h($i['kode'])?></td><th width="15%">PIC</th><td><?=h($i['pic_nama']??'-')?></td></tr>
<tr><th>Nama indikator</th><td colspan="3"><?=h($i['nama'])?></td></tr>
<tr><th>Definisi operasional</th><td colspan="3"><?=nl2br(h($i['definisi_operasional']??'-'))?></td></tr>
<tr><th>Numerator</th><td><?=h($i['numerator_label']??'-')?></td><th>Denominator</th><td><?=h($i['denominator_label']??'-')?></td></tr>
<tr><th>Formula</th><td><?=h($i['formula']??'N / D × 100')?></td><th>Frekuensi</th><td><?=h($i['frekuensi'])?></td></tr>
<tr><th>Sumber data</th><td><?=h($i['sumber_data']??'-')?></td><th>Metode</th><td><?=h($i['metode_pengumpulan']??'-')?></td></tr></table>

<h2>2. Pemetaan EP MRMIK</h2>
<table><tr><th>EP</th><th>Fokus</th></tr><?php if($ep):foreach($ep as $e):?><tr><td><strong><?=h($e['kode'])?></strong></td><td><?=h($e['judul'])?></td></tr><?php endforeach;else:?><tr><td colspan="2">Belum dipetakan.</td></tr><?php endif;?></table>

<h2>3. Grafik Tren <?=h($year)?></h2>
<div class="chartbox">
<svg viewBox="0 0 980 390" role="img" aria-label="Grafik tren capaian 12 bulan">
<defs><linearGradient id="area" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#198754" stop-opacity=".22"/><stop offset="1" stop-color="#198754" stop-opacity=".02"/></linearGradient></defs>
<?php for($g=0;$g<=5;$g++):$yy=$gy+$gh*$g/5;$yv=$max-($max-$min)*$g/5;?><line x1="<?=$gx?>" y1="<?=round($yy,1)?>" x2="<?=($gx+$gw)?>" y2="<?=round($yy,1)?>" stroke="#e5eee9"/><text x="<?=$gx-9?>" y="<?=round($yy+4,1)?>" text-anchor="end" font-size="11" fill="#64748b"><?=h(fmt($yv,1))?></text><?php endfor;?>
<?php if($target!==null):$ty=$gy+$gh-($target-$min)*$gh/($max-$min);?><line x1="<?=$gx?>" y1="<?=round($ty,1)?>" x2="<?=($gx+$gw)?>" y2="<?=round($ty,1)?>" stroke="#f59f00" stroke-width="2" stroke-dasharray="8 6"/><text x="<?=$gx+$gw?>" y="<?=round($ty-7,1)?>" text-anchor="end" font-size="11" fill="#9a6b00">Target <?=h(fmt($target,2))?> <?=h($i['satuan'])?></text><?php endif;?>
<?php if($area):?><path d="<?=h($area)?>" fill="url(#area)" stroke="none"/><?php endif;?>
<?php foreach($pts as $idx=>$p): if($p):?><circle cx="<?=$p[0]?>" cy="<?=$p[1]?>" r="7" fill="#fff"/><circle cx="<?=$p[0]?>" cy="<?=$p[1]?>" r="4.5" fill="<?=$colors[$statuses[$idx]]??'#198754'?>"/><text x="<?=$p[0]?>" y="<?=$p[1]-12?>" text-anchor="middle" font-size="10" font-weight="bold" fill="#24323d"><?=h(fmt($values[$idx],2))?></text><?php endif;endforeach;?>
<?php foreach($pts as $idx=>$p): if($p):?><text x="<?=$p[0]?>" y="<?=($gy+$gh+27)?>" text-anchor="middle" font-size="11" fill="#475569"><?=h(substr($monthNames[$idx],0,3))?></text><?php endif;endforeach;?>
<?php if($poly):?><path d="<?=h($poly)?>" fill="none" stroke="#198754" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><?php endif;?>
<text x="<?=$gx?>" y="20" font-size="13" font-weight="bold" fill="#123f34">Capaian bulanan (<?=h($i['satuan'])?>)</text>
</svg></div>

<h2>4. Tabel Capaian 12 Bulan</h2>
<table><thead><tr><th>Bulan</th><th>N</th><th>D</th><th>Capaian</th><th>Target</th><th>Status</th><th>Analisis / RTL</th></tr></thead><tbody>
<?php for($m=1;$m<=12;$m++): $r=$reportMonths[$m]??null; $s=$r['status']??'belum_dinilai'; ?><tr><td><strong><?=h($monthNames[$m-1])?></strong></td><td><?= $r ? h($r['numerator']??'—') : '—' ?></td><td><?= $r ? h($r['denominator']??'—') : '—' ?></td><td><?= ($r && $r['capaian']!==null) ? h(fmt($r['capaian'],2).' '.$i['satuan']) : '—' ?></td><td><?=h($r['target_snapshot']??$i['target']??'—')?></td><td class="<?=$s==='tercapai'?'ok':($s==='tidak_tercapai'?'bad':($s==='perlu_perhatian'?'warn':'empty'))?>"><?=h(strtoupper(str_replace('_',' ',$s)))?></td><td><?= $r ? h(trim(($r['analisis']??'').' | '.($r['tindak_lanjut']??''))) : '—' ?></td></tr><?php endfor;?></tbody></table>

<?php if(in_array($i['kode'],['IM-IT-01','IM-IT-02'],true)): ?>
<h2>5. Rincian Sumber Data Downtime SIMRS</h2>
<div class="small">Sumber: tabel <strong>downtime</strong> MRMIKIT. Durasi kejadian yang melintasi batas bulan dipotong sesuai bulan laporan. Kejadian yang beririsan waktunya dihitung tanpa menggandakan durasi.</div>
<table><thead><tr><th>Mulai</th><th>Selesai</th><th>Durasi</th><th>Jenis</th><th>Dampak</th><th>Penyebab</th><th>PIC</th><th>Sumber</th><th>Tindakan</th></tr></thead><tbody>
<?php if($downtimeRows):foreach($downtimeRows as $d):$sec=max(0,(new DateTime($d['selesai']))->getTimestamp()-(new DateTime($d['mulai']))->getTimestamp());?><tr><td><?=h($d['mulai'])?></td><td><?=h($d['selesai'])?></td><td><?=h(fmt($sec/60,2))?> menit</td><td><?=h($d['jenis']??'-')?></td><td><?=h($d['dampak']??$d['unit_terdampak']??'-')?></td><td><?=h($d['penyebab']??'-')?></td><td><?=h($d['pic_nama']??'-')?></td><td><?=h($d['sumber_data']??'-')?></td><td><?=h($d['tindakan']??'-')?></td></tr><?php endforeach;else:?><tr><td colspan="9">Tidak ada kejadian downtime pada tahun <?=h($year)?>.</td></tr><?php endif;?></tbody></table>
<?php endif; ?>

<h2><?=in_array($i['kode'],['IM-IT-01','IM-IT-02'],true)?'6':'5'?>. Bukti Pendukung</h2>
<table><thead><tr><th>Bulan</th><th>Nama file</th><th>Catatan</th></tr></thead><tbody><?php if($bukti):foreach($bukti as $b):?><tr><td><?=h($monthNames[(int)date('n',strtotime($b['periode']))-1])?></td><td><?=h($b['original_name'])?></td><td><?=h($b['catatan']??'-')?></td></tr><?php endforeach;else:?><tr><td colspan="3">Belum ada bukti yang diunggah pada capaian.</td></tr><?php endif;?></tbody></table>

<?php if(in_array($i['kode'],['IM-IT-01','IM-IT-02'],true)): ?>
<h2>7. Bukti Pendukung Downtime</h2>
<div class="small">Bukti diambil langsung dari <strong>menu Downtime</strong> (tabel <strong>downtime</strong>). Tidak perlu upload ulang pada menu laporan.</div>
<table><thead><tr><th>Tanggal</th><th>Nama file</th><th>Sumber data</th><th>Penyebab</th><th>Catatan</th></tr></thead><tbody>
<?php if($downtimeEvidence): foreach($downtimeEvidence as $db): ?>
<tr>
<td><?=h(date('d-m-Y',strtotime($db['mulai'])))?></td>
<td><a href="../uploads/downtime/<?=rawurlencode(basename($db['bukti_filename']))?>" target="_blank"><?=h($db['bukti_original_name']??basename($db['bukti_filename']))?></a></td>
<td><?=h($db['sumber_data']??'-')?></td>
<td><?=h($db['penyebab']??'-')?></td>
<td><?=h($db['evaluasi']??$db['tindakan']??'-')?></td>
</tr>
<?php endforeach; else: ?><tr><td colspan="5">Belum ada bukti downtime yang diunggah pada menu Downtime untuk tahun <?=h($year)?>.</td></tr><?php endif; ?>
</tbody></table>
<?php foreach($downtimeEvidence as $db):
    $ext=strtolower(pathinfo($db['bukti_filename'],PATHINFO_EXTENSION));
    $isImage=in_array($ext,['jpg','jpeg','png','webp'],true);
    $fileUrl='../uploads/downtime/'.rawurlencode(basename($db['bukti_filename']));
?>
<div class="avoid-break" style="margin-top:10px">
<strong><?=h(date('d-m-Y H:i',strtotime($db['mulai'])))?> — <?=h($db['bukti_original_name']??basename($db['bukti_filename']))?></strong>
<?php if($isImage): ?>
<div style="margin-top:6px"><img src="<?=h($fileUrl)?>" alt="<?=h($db['bukti_original_name']??'Bukti downtime')?>" style="max-width:100%;max-height:260mm;border:1px solid #d7dee5;border-radius:5px"></div>
<?php else: ?>
<div class="small" style="margin-top:5px">File bukti: <a href="<?=h($fileUrl)?>"><?=h($db['bukti_original_name']??basename($db['bukti_filename']))?></a></div>
<?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php if($i['kode']==='IM-IT-04'): ?>
<h2>6. Rincian Uji Restore Backup</h2>
<div class="small">Capaian IM-IT-04 dihitung dari jumlah uji restore yang berhasil dibandingkan seluruh uji restore yang dicatat pada bulan tersebut.</div>
<table><thead><tr><th>Tanggal</th><th>Backup</th><th>Sumber</th><th>Target restore</th><th>Mulai</th><th>Selesai</th><th>Durasi</th><th>Hasil</th><th>PIC</th></tr></thead><tbody>
<?php if($restoreRows): foreach($restoreRows as $rt): $dur=$rt['durasi_detik']!==null?fmt($rt['durasi_detik']/60,1).' menit':'-'; ?>
<tr><td><?=h($rt['tanggal_uji'])?></td><td><?=h(strtoupper($rt['jenis_backup']))?></td><td><?=h($rt['sumber_backup']??'-')?></td><td><?=h($rt['target_restore']??'-')?></td><td><?=h($rt['mulai']??'-')?></td><td><?=h($rt['selesai']??'-')?></td><td><?=h($dur)?></td><td class="<?=$rt['hasil']==='berhasil'?'ok':'bad'?>"><?=h(strtoupper($rt['hasil']))?></td><td><?=h($rt['pic_nama']??'-')?></td></tr>
<tr><td colspan="9"><strong>Verifikasi:</strong> <?=nl2br(h($rt['verifikasi']??'-'))?><?php if(!empty($rt['analisis'])):?><br><strong>Analisis:</strong> <?=nl2br(h($rt['analisis']))?><?php endif;?><?php if(!empty($rt['tindak_lanjut'])):?><br><strong>RTL:</strong> <?=nl2br(h($rt['tindak_lanjut']))?><?php endif;?></td></tr>
<?php endforeach; else: ?><tr><td colspan="9">Belum ada uji restore pada tahun <?=h($year)?>.</td></tr><?php endif; ?></tbody></table>

<h2>7. Bukti Uji Restore</h2>
<table><thead><tr><th>Tanggal uji</th><th>File bukti</th><th>Catatan</th></tr></thead><tbody>
<?php if($restoreEvidence): foreach($restoreEvidence as $rb): ?><tr><td><?=h($rb['tanggal_uji'])?></td><td><?=h($rb['original_name'])?></td><td><?=h($rb['catatan']??'-')?></td></tr><?php endforeach; else: ?><tr><td colspan="3">Belum ada bukti uji restore.</td></tr><?php endif; ?></tbody></table>
<?php foreach($restoreEvidence as $rb): $isImage=in_array(strtolower(pathinfo($rb['original_name'],PATHINFO_EXTENSION)),['jpg','jpeg','png'],true); ?>
<div class="avoid-break" style="margin-top:10px"><strong><?=h($rb['tanggal_uji'])?> — <?=h($rb['original_name'])?></strong>
<?php if($isImage): ?><div style="margin-top:6px"><img src="download_restore_evidence.php?id=<?=$rb['id']?>" alt="<?=h($rb['original_name'])?>" style="max-width:100%;max-height:260mm;border:1px solid #d7dee5;border-radius:5px"></div><?php else: ?><div class="small" style="margin-top:5px">File tersimpan sebagai bukti: <?=h($rb['original_name'])?></div><?php endif; ?>
<?php if(!empty($rb['catatan'])):?><div class="small" style="margin-top:4px"><?=nl2br(h($rb['catatan']))?></div><?php endif; ?></div>
<?php endforeach; ?>
<?php endif; ?>

<?php if($i['kode']==='IM-IT-03'): ?>
<h2>6. Bukti Perwakilan Backup</h2>
<div class="small">Bukti ini mewakili mekanisme backup offline dan online untuk tahun <?=h($year)?>. Tidak diperlukan screenshot setiap hari. Rekap harian/bulanan tetap menjadi sumber angka capaian.</div>
<table><thead><tr><th>Jenis</th><th>File bukti</th><th>Catatan</th></tr></thead><tbody>
<?php if($backupEvidence): foreach($backupEvidence as $eb): ?>
<tr><td><strong><?=h(strtoupper($eb['jenis']))?></strong></td><td><?=h($eb['original_name'])?></td><td><?=h($eb['catatan']??'-')?></td></tr>
<?php endforeach; else: ?><tr><td colspan="3">Belum ada bukti perwakilan offline/online.</td></tr><?php endif; ?></tbody></table>
<?php foreach($backupEvidence as $eb): $isImage=in_array(strtolower(pathinfo($eb['original_name'],PATHINFO_EXTENSION)),['jpg','jpeg','png'],true); ?>
<div class="avoid-break" style="margin-top:10px"><strong><?=h(ucfirst($eb['jenis']))?> — <?=h($eb['original_name'])?></strong>
<?php if($isImage): ?><div style="margin-top:6px"><img src="download_backup_evidence.php?id=<?=$eb['id']?>" alt="<?=h($eb['original_name'])?>" style="max-width:100%;max-height:260mm;border:1px solid #d7dee5;border-radius:5px"></div><?php else: ?><div class="small" style="margin-top:5px">File PDF tersimpan sebagai bukti: <?=h($eb['original_name'])?></div><?php endif; ?>
<?php if(!empty($eb['catatan'])):?><div class="small" style="margin-top:4px"><?=nl2br(h($eb['catatan']))?></div><?php endif; ?></div>
<?php endforeach; ?>
<?php endif; ?>

<h2><?=in_array($i['kode'],['IM-IT-01','IM-IT-02'],true)||in_array($i['kode'],['IM-IT-03','IM-IT-04'],true)?'7':'6'?>. Ringkasan</h2>
<p>Laporan ini mengambil data langsung dari MRMIKIT untuk indikator <strong><?=h($i['kode'])?></strong> tahun <?=h($year)?>. Untuk IM-IT-01 dan IM-IT-02, perhitungan capaian dan rincian kejadian menggunakan tabel <strong>downtime</strong> yang sama. Untuk IM-IT-04, capaian dan bukti uji restore ditelusuri dari catatan uji restore yang tersimpan di MRMIKIT.</p>
<div class="footer">Dicetak dari MRMIKIT · <?=date('d-m-Y H:i')?> · Laporan Indikator Mutu IT</div>
</body></html>
