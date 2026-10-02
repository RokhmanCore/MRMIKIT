<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$id=(int)($_GET['id']??0);
$year=(int)($_GET['tahun']??date('Y'));
if($year<2020||$year>2100)$year=(int)date('Y');

$st=$pdo->prepare("SELECT i.*,p.nama pic_nama FROM mutu_indikator i LEFT JOIN pic p ON p.id=i.pic_id WHERE i.id=? AND i.aktif=1");
$st->execute([$id]);$i=$st->fetch();
if(!$i){http_response_code(404);exit('Indikator tidak ditemukan.');}

$st=$pdo->prepare("SELECT c.*,DATE_FORMAT(c.periode,'%Y-%m') periode_label FROM mutu_capaian c WHERE c.indikator_id=? AND YEAR(c.periode)=? ORDER BY c.periode");
$st->execute([$id,$year]);$rows=$st->fetchAll();

$ep=[];
$st=$pdo->prepare("SELECT e.kode,e.judul FROM mutu_indikator_ep m JOIN elemen_penilaian e ON e.id=m.ep_id WHERE m.indikator_id=? ORDER BY e.urutan");
$st->execute([$id]);$ep=$st->fetchAll();

$bukti=[];
if($rows){
 $ids=array_map(fn($r)=>(int)$r['id'],$rows);$ph=implode(',',array_fill(0,count($ids),'?'));
 $st=$pdo->prepare("SELECT b.*,c.periode FROM mutu_bukti b JOIN mutu_capaian c ON c.id=b.capaian_id WHERE b.capaian_id IN ($ph) ORDER BY c.periode,b.created_at");
 $st->execute($ids);$bukti=$st->fetchAll();
}
$monthNames=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$byMonth=[];foreach($rows as $r)$byMonth[(int)date('n',strtotime($r['periode']))]=$r;
$tercapai=$tidak=0;$sum=0;$cnt=0;
foreach($rows as $r){if($r['status']==='tercapai')$tercapai++;if($r['status']==='tidak_tercapai')$tidak++;if($r['capaian']!==null){$sum+=(float)$r['capaian'];$cnt++;}}
$avg=$cnt?$sum/$cnt:null;
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Laporan <?=h($i['kode'])?> - MRMIKIT</title>
<style>
@page{size:A4;margin:14mm}*{box-sizing:border-box}body{font-family:Arial,Helvetica,sans-serif;color:#17202a;font-size:11px;line-height:1.45}h1{font-size:20px;margin:0}h2{font-size:15px;margin:20px 0 8px;border-bottom:2px solid #198754;padding-bottom:5px}.head{border-bottom:3px solid #198754;padding-bottom:12px;margin-bottom:15px}.sub{color:#64748b}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:12px 0}.box{border:1px solid #d7dee5;border-radius:7px;padding:9px}.label{font-size:9px;text-transform:uppercase;color:#64748b}.value{font-size:15px;font-weight:bold;margin-top:2px}table{width:100%;border-collapse:collapse;margin-top:8px}th,td{border:1px solid #d7dee5;padding:6px;vertical-align:top}th{background:#eef5f1;text-align:left}.ok{background:#dff3e8;color:#137a49;font-weight:bold}.bad{background:#fde2e2;color:#b42318;font-weight:bold}.empty{background:#f1f3f5;color:#6b7280}.small{font-size:9px;color:#64748b}.btnbar{margin-bottom:14px}.btn{background:#198754;color:white;border:0;border-radius:5px;padding:8px 12px;cursor:pointer}.evidence{margin:3px 0}.page-break{page-break-before:always}.footer{margin-top:20px;border-top:1px solid #ddd;padding-top:8px;font-size:9px;color:#64748b}@media print{.btnbar{display:none}}
</style></head><body>
<div class="btnbar"><button class="btn" onclick="window.print()">🖨 Cetak / Simpan sebagai PDF</button> <button class="btn" onclick="window.close()">Tutup</button></div>
<div class="head"><div class="sub">MRMIKIT · MUTU TEKNOLOGI INFORMASI</div><h1>Laporan Indikator Mutu IT</h1><div><strong><?=h($i['kode'])?> — <?=h($i['nama'])?></strong> · Tahun <?=h($year)?></div></div>
<div class="grid">
<div class="box"><div class="label">Target</div><div class="value"><?=h($i['target']??'-')?> <?=h($i['satuan'])?></div></div>
<div class="box"><div class="label">Rata-rata capaian</div><div class="value"><?=$avg!==null?h(number_format($avg,2,',','.').' '.$i['satuan']):'-'?></div></div>
<div class="box"><div class="label">Bulan tercapai</div><div class="value"><?=$tercapai?></div></div>
<div class="box"><div class="label">Bulan tidak tercapai</div><div class="value"><?=$tidak?></div></div>
</div>
<h2>1. Profil Indikator</h2>
<table><tr><th width="22%">Kode</th><td><?=h($i['kode'])?></td><th width="18%">PIC</th><td><?=h($i['pic_nama']??'-')?></td></tr>
<tr><th>Nama indikator</th><td colspan="3"><?=h($i['nama'])?></td></tr>
<tr><th>Definisi operasional</th><td colspan="3"><?=nl2br(h($i['definisi_operasional']??'-'))?></td></tr>
<tr><th>Numerator</th><td><?=h($i['numerator_label']??'-')?></td><th>Denominator</th><td><?=h($i['denominator_label']??'-')?></td></tr>
<tr><th>Formula</th><td><?=h($i['formula']??'N / D × 100')?></td><th>Frekuensi</th><td><?=h($i['frekuensi'])?></td></tr>
<tr><th>Sumber data</th><td><?=h($i['sumber_data']??'-')?></td><th>Metode</th><td><?=h($i['metode_pengumpulan']??'-')?></td></tr></table>
<h2>2. Pemetaan EP MRMIK</h2>
<table><tr><th>EP</th><th>Fokus</th></tr><?php if($ep):foreach($ep as $e):?><tr><td><strong><?=h($e['kode'])?></strong></td><td><?=h($e['judul'])?></td></tr><?php endforeach;else:?><tr><td colspan="2">Belum dipetakan.</td></tr><?php endif;?></table>
<h2>3. Capaian Bulanan Tahun <?=h($year)?></h2>
<table><thead><tr><th>Bulan</th><th>Numerator</th><th>Denominator</th><th>Capaian</th><th>Target</th><th>Status</th><th>Analisis / Tindak lanjut</th></tr></thead><tbody>
<?php for($m=1;$m<=12;$m++):$r=$byMonth[$m]??null;$s=$r['status']??'belum_dinilai';?><tr><td><strong><?=h($monthNames[$m-1])?></strong></td><td><?=$r?h($r['numerator']):'—'?></td><td><?=$r?h($r['denominator']):'—'?></td><td><?=$r&&$r['capaian']!==null?h(number_format((float)$r['capaian'],2,',','.').' '.$i['satuan']):'—'?></td><td><?=$r?h($r['target_snapshot']):h($i['target']??'—')?></td><td class="<?=$s==='tercapai'?'ok':($s==='tidak_tercapai'?'bad':'empty')?>"><?=h(strtoupper(str_replace('_',' ',$s)))?></td><td><?=$r?h(trim(($r['analisis']??'')." ".($r['tindak_lanjut']??'')):'—')?></td></tr><?php endfor;?></tbody></table>
<div class="page-break"></div><h2>4. Bukti Pendukung</h2>
<table><thead><tr><th>Bulan</th><th>Nama file</th><th>Catatan</th></tr></thead><tbody><?php if($bukti):foreach($bukti as $b):?><tr><td><?=h($monthNames[(int)date('n',strtotime($b['periode']))-1])?></td><td><?=h($b['original_name'])?></td><td><?=h($b['catatan']??'-')?></td></tr><?php endforeach;else:?><tr><td colspan="3">Belum ada bukti yang diunggah.</td></tr><?php endif;?></tbody></table>
<h2>5. Ringkasan</h2><p>Laporan ini mengambil data langsung dari modul Indikator Mutu IT MRMIKIT untuk indikator <strong><?=h($i['kode'])?></strong> pada tahun <?=h($year)?>. Target dan interpretasi capaian mengikuti konfigurasi indikator yang ditetapkan oleh rumah sakit.</p>
<div class="footer">Dicetak dari MRMIKIT · <?=date('d-m-Y H:i')?> · Dokumen laporan indikator mutu IT</div>
</body></html>