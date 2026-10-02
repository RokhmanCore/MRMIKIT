<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();



/* Pastikan tabel IM-IT-03 tersedia sebelum SELECT halaman detail. */
$pdo->exec("CREATE TABLE IF NOT EXISTS mutu_backup_harian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    indikator_id INT NOT NULL,
    periode DATE NOT NULL,
    dijadwalkan INT NOT NULL DEFAULT 0,
    berhasil INT NOT NULL DEFAULT 0,
    gagal INT NOT NULL DEFAULT 0,
    catatan TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_backup_periode (indikator_id, periode),
    INDEX idx_backup_indikator_periode (indikator_id, periode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
/* Bukti perwakilan IM-IT-03: satu bukti offline dan satu bukti online per tahun. */
$pdo->exec("CREATE TABLE IF NOT EXISTS mutu_backup_bukti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    indikator_id INT NOT NULL,
    tahun YEAR NOT NULL,
    jenis ENUM('offline','online') NOT NULL,
    nama_file VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NULL,
    size_bytes BIGINT NOT NULL DEFAULT 0,
    catatan TEXT NULL,
    uploaded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_backup_bukti (indikator_id,tahun,jenis),
    INDEX idx_backup_bukti_indikator (indikator_id,tahun)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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

        if ($action==='sinkronkan_im_it_01') {
            $indikator_id=(int)($_POST['indikator_id']??0);
            $tahun=(int)($_POST['tahun']??date('Y'));
            if($indikator_id<1 || $tahun<2020 || $tahun>2100) throw new RuntimeException('Indikator atau tahun tidak valid.');

            $st=$pdo->prepare("SELECT * FROM mutu_indikator WHERE id=?");
            $st->execute([$indikator_id]); $ind=$st->fetch();
            if(!$ind) throw new RuntimeException('Indikator tidak ditemukan.');
            if($ind['kode']!=='IM-IT-01') throw new RuntimeException('Fitur ini khusus IM-IT-01 Ketersediaan SIMRS.');

            // Sumber data IM-IT-01 dan IM-IT-02 sama-sama tabel downtime.
            // Semua kejadian downtime digabung per bulan. Jika bulan sudah selesai
            // dan tidak ada downtime, ketersediaan otomatis 100%.
            $events=$pdo->query("SELECT mulai,selesai FROM downtime WHERE selesai IS NOT NULL AND selesai>mulai ORDER BY mulai")->fetchAll();

            $oldSt=$pdo->prepare("SELECT analisis,tindak_lanjut,target_snapshot FROM mutu_capaian WHERE indikator_id=? AND periode=?");
            $up=$pdo->prepare("INSERT INTO mutu_capaian
                (indikator_id,periode,numerator,denominator,capaian,target_snapshot,analisis,tindak_lanjut,status,created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE numerator=VALUES(numerator),denominator=VALUES(denominator),
                capaian=VALUES(capaian),target_snapshot=VALUES(target_snapshot),
                analisis=VALUES(analisis),tindak_lanjut=VALUES(tindak_lanjut),
                status=VALUES(status),updated_at=CURRENT_TIMESTAMP");

            $hasil=0;
            $nowYear=(int)date('Y');
            $nowMonth=(int)date('n');

            for($bulan=1;$bulan<=12;$bulan++){
                $start=new DateTime(sprintf('%04d-%02d-01 00:00:00',$tahun,$bulan));
                $end=(clone $start)->modify('+1 month');
                $periode=$start->format('Y-m-d');

                // Bulan yang sudah selesai boleh otomatis dinilai.
                // Bulan berjalan dan bulan masa depan tanpa data tetap kosong.
                $isPastMonth=($tahun<$nowYear) || ($tahun===$nowYear && $bulan<$nowMonth);

                $intervals=[];
                foreach($events as $ev){
                    $ds=new DateTime($ev['mulai']);
                    $de=new DateTime($ev['selesai']);
                    $cs=$ds>$start?$ds:$start;
                    $ce=$de<$end?$de:$end;
                    if($ce>$cs) $intervals[]=[$cs->getTimestamp(),$ce->getTimestamp()];
                }

                usort($intervals,fn($x,$y)=>$x[0]<=>$y[0]);
                $merged=[];
                foreach($intervals as $iv){
                    if(!$merged || $iv[0]>$merged[count($merged)-1][1]){
                        $merged[]=$iv;
                    }else{
                        $merged[count($merged)-1][1]=max($merged[count($merged)-1][1],$iv[1]);
                    }
                }

                // Simpan analisis/RTL yang sudah dibuat pengguna agar sinkronisasi
                // tidak menghapus catatan akreditasi.
                $oldSt->execute([$indikator_id,$periode]);
                $old=$oldSt->fetch();

                $target=$ind['target']!==null?(float)$ind['target']:($old['target_snapshot']??null);

                if(!$merged){
                    if(!$isPastMonth) continue;

                    // Tidak ada downtime pada bulan yang sudah selesai:
                    // N = D = total jam kalender, capaian = 100%.
                    $totalHours=($end->getTimestamp()-$start->getTimestamp())/3600;
                    $cap=100.0;
                    $status=$target===null?'belum_dinilai':($cap >= $target?'tercapai':'tidak_tercapai');

                    $up->execute([
                        $indikator_id,$periode,
                        round($totalHours,4),round($totalHours,4),
                        $cap,$target,
                        $old['analisis']??'Tidak ditemukan kejadian downtime pada bulan ini. Ketersediaan SIMRS 100%.',
                        $old['tindak_lanjut']??'Tidak ada tindak lanjut karena tidak terdapat downtime.',
                        $status,$_SESSION['user']['id']??null
                    ]);
                    $hasil++;
                    continue;
                }

                $downSeconds=0;
                foreach($merged as $iv) $downSeconds += $iv[1]-$iv[0];

                $totalSeconds=$end->getTimestamp()-$start->getTimestamp();
                $totalHours=$totalSeconds/3600;
                $downHours=$downSeconds/3600;
                $availableHours=max(0,$totalHours-$downHours);
                $cap=$totalHours>0?($availableHours/$totalHours)*100:0;
                $status=$target===null?'belum_dinilai':($cap >= $target?'tercapai':'tidak_tercapai');

                $up->execute([
                    $indikator_id,$periode,
                    round($availableHours,4),round($totalHours,4),
                    round($cap,4),$target,
                    $old['analisis']??'',
                    $old['tindak_lanjut']??'',
                    $status,$_SESSION['user']['id']??null
                ]);
                $hasil++;
            }

            $msg="IM-IT-01 otomatis disinkronkan dari tabel Downtime yang sama dengan IM-IT-02. Bulan selesai tanpa downtime menjadi 100%; bulan dengan downtime dihitung dari seluruh kejadian; bulan berjalan/masa depan tanpa data tetap Belum Ada Data.";
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

            // Sinkronisasi IM-IT-01 berdasarkan tabel downtime:
            // bulan selesai tanpa downtime = 100%, bulan dengan downtime dihitung otomatis,
            // sedangkan bulan berjalan/masa depan tanpa data tetap Belum Ada Data.
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
                $nowYear=(int)date('Y');
                $nowMonth=(int)date('n');
                $isPastMonth=($tahun<$nowYear) || ($tahun===$nowYear && $bulan<$nowMonth);

                // Bulan yang sudah selesai tanpa downtime = ketersediaan 100%.
                if(!$merged){
                    if($isPastMonth){
                        $totalHours=$totalSeconds/3600;
                        $cap=100.0;
                        $target=$ind['target']!==null?(float)$ind['target']:null;
                        $status=$target===null?'belum_dinilai':($cap >= $target?'tercapai':'tidak_tercapai');
                        $up=$pdo->prepare("INSERT INTO mutu_capaian
                            (indikator_id,periode,numerator,denominator,capaian,target_snapshot,analisis,tindak_lanjut,status,created_by)
                            VALUES (?,?,?,?,?,?,?,?,?,?)
                            ON DUPLICATE KEY UPDATE numerator=VALUES(numerator),denominator=VALUES(denominator),
                            capaian=VALUES(capaian),target_snapshot=VALUES(target_snapshot),
                            status=VALUES(status),updated_at=CURRENT_TIMESTAMP");
                        $up->execute([$indikator_id,$start->format('Y-m-d'),round($totalHours,4),round($totalHours,4),
                            $cap,$target,'Tidak ditemukan kejadian downtime pada bulan ini. Ketersediaan SIMRS 100%.',
                            'Tidak ada tindak lanjut karena tidak terdapat downtime.',$status,$_SESSION['user']['id']??null]);
                        $hasil++;
                    }
                    continue;
                }

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
            $msg="IM-IT-01 disinkronkan dari tabel Downtime. {$hasil} bulan diperbarui: bulan dengan downtime dihitung otomatis, sedangkan bulan yang sudah selesai tanpa downtime menjadi 100%. Bulan berjalan/masa depan tanpa data tetap kosong.";
        }

        if ($action==='hitung_downtime_simrs') {
            $indikator_id=(int)($_POST['indikator_id']??0);
            $tahun=(int)($_POST['tahun']??date('Y'));
            if($indikator_id<1 || $tahun<2020 || $tahun>2100) throw new RuntimeException('Indikator atau tahun tidak valid.');

            $st=$pdo->prepare("SELECT * FROM mutu_indikator WHERE id=?");
            $st->execute([$indikator_id]); $ind=$st->fetch();
            if(!$ind) throw new RuntimeException('Indikator tidak ditemukan.');
            if($ind['kode']!=='IM-IT-02') throw new RuntimeException('Fitur ini khusus IM-IT-02 Downtime SIMRS.');

            $events=$pdo->query("SELECT id,mulai,selesai FROM downtime WHERE selesai IS NOT NULL AND selesai>mulai ORDER BY mulai")->fetchAll();

            $oldSt=$pdo->prepare("SELECT analisis,tindak_lanjut,target_snapshot FROM mutu_capaian WHERE indikator_id=? AND periode=?");
            $up=$pdo->prepare("INSERT INTO mutu_capaian
                (indikator_id,periode,numerator,denominator,capaian,target_snapshot,analisis,tindak_lanjut,status,created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE numerator=VALUES(numerator),denominator=VALUES(denominator),
                capaian=VALUES(capaian),target_snapshot=VALUES(target_snapshot),
                status=VALUES(status),updated_at=CURRENT_TIMESTAMP");

            $hasil=0;
            for($bulan=1;$bulan<=12;$bulan++){
                $start=new DateTime(sprintf('%04d-%02d-01 00:00:00',$tahun,$bulan));
                $end=(clone $start)->modify('+1 month');

                $intervals=[];
                foreach($events as $ev){
                    $ds=new DateTime($ev['mulai']);
                    $de=new DateTime($ev['selesai']);
                    $cs=$ds>$start?$ds:$start;
                    $ce=$de<$end?$de:$end;
                    if($ce>$cs) $intervals[]=[$cs->getTimestamp(),$ce->getTimestamp()];
                }

                // Untuk IM-IT-02, bulan yang SUDAH LEWAT tetapi tidak memiliki
                // satu pun kejadian downtime dianggap "Tidak Ada Downtime" = 0 menit.
                // Bulan berjalan dan bulan yang akan datang tetap "Belum Ada Data".
                if(!$intervals){
                    $nowYear=(int)date('Y');
                    $nowMonth=(int)date('n');
                    $isPastMonth=($tahun<$nowYear) || ($tahun===$nowYear && $bulan<$nowMonth);
                    if($isPastMonth){
                        $periode=$start->format('Y-m-d');
                        $oldSt->execute([$indikator_id,$periode]);
                        $old=$oldSt->fetch();
                        $target=$ind['target']!==null?(float)$ind['target']:($old['target_snapshot']??null);

                        // Tidak ada downtime = 0 menit. Bila target belum diisi,
                        // 0 menit tetap ditandai tercapai karena arah IM-IT-02 adalah
                        // semakin rendah semakin baik.
                        $status=$target===null?'tercapai':(0 <= $target?'tercapai':'tidak_tercapai');

                        $up->execute([
                            $indikator_id,$periode,0,0,0,$target,
                            $old['analisis']??'Tidak ditemukan kejadian downtime pada bulan ini.',
                            $old['tindak_lanjut']??'',
                            $status,$_SESSION['user']['id']??null
                        ]);
                        $hasil++;
                    }
                    continue;
                }

                $eventCount=count($intervals);
                usort($intervals,fn($a,$b)=>$a[0]<=>$b[0]);
                $merged=[];
                foreach($intervals as $iv){
                    if(!$merged || $iv[0]>$merged[count($merged)-1][1]) $merged[]=$iv;
                    else $merged[count($merged)-1][1]=max($merged[count($merged)-1][1],$iv[1]);
                }

                $downSeconds=0;
                foreach($merged as $iv) $downSeconds += $iv[1]-$iv[0];
                $totalMinutes=round($downSeconds/60,2);
                $periode=$start->format('Y-m-d');

                $oldSt->execute([$indikator_id,$periode]);
                $old=$oldSt->fetch();
                $target=$ind['target']!==null?(float)$ind['target']:($old['target_snapshot']??null);
                $status=$target===null?'belum_dinilai':($totalMinutes <= (float)$target?'tercapai':'tidak_tercapai');

                $up->execute([
                    $indikator_id,$periode,$totalMinutes,$eventCount,$totalMinutes,$target,
                    $old['analisis']??'',''===($old['tindak_lanjut']??'')?'':$old['tindak_lanjut'],
                    $status,$_SESSION['user']['id']??null
                ]);
                $hasil++;
            }

            $msg="IM-IT-02 disinkronkan dari menu Downtime. {$hasil} bulan diperbarui. Bulan yang sudah lewat tanpa kejadian downtime otomatis menjadi 0 menit; bulan berjalan/masa depan tetap Belum Ada Data.";
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

        if ($action==='save_backup_bulanan') {
            $indikator_id=(int)($_POST['indikator_id']??0);
            $tahun=(int)($_POST['tahun']??date('Y'));
            if($indikator_id<1 || $tahun<2020 || $tahun>2100) throw new RuntimeException('Indikator atau tahun tidak valid.');
            $st=$pdo->prepare("SELECT * FROM mutu_indikator WHERE id=?");
            $st->execute([$indikator_id]); $ind=$st->fetch();
            if(!$ind || $ind['kode']!=='IM-IT-03') throw new RuntimeException('Fitur ini khusus IM-IT-03 Keberhasilan backup data.');
            $pdo->exec("CREATE TABLE IF NOT EXISTS mutu_backup_harian (
                id INT AUTO_INCREMENT PRIMARY KEY,
                indikator_id INT NOT NULL,
                periode DATE NOT NULL,
                dijadwalkan INT NOT NULL DEFAULT 0,
                berhasil INT NOT NULL DEFAULT 0,
                gagal INT NOT NULL DEFAULT 0,
                catatan TEXT NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_backup_periode (indikator_id,periode),
                FOREIGN KEY (indikator_id) REFERENCES mutu_indikator(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $items=$_POST['backup']??[]; $saved=0;
            $sql=$pdo->prepare("INSERT INTO mutu_backup_harian(indikator_id,periode,dijadwalkan,berhasil,gagal,catatan,created_by)
                VALUES(?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE dijadwalkan=VALUES(dijadwalkan),berhasil=VALUES(berhasil),gagal=VALUES(gagal),
                catatan=VALUES(catatan),updated_at=CURRENT_TIMESTAMP");
            $capSql=$pdo->prepare("INSERT INTO mutu_capaian
                (indikator_id,periode,numerator,denominator,capaian,target_snapshot,analisis,tindak_lanjut,status,created_by)
                VALUES(?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE numerator=VALUES(numerator),denominator=VALUES(denominator),capaian=VALUES(capaian),
                target_snapshot=VALUES(target_snapshot),status=VALUES(status),updated_at=CURRENT_TIMESTAMP");
            for($bulan=1;$bulan<=12;$bulan++){
                $r=is_array($items[$bulan]??null)?$items[$bulan]:[];
                $scheduled=max(0,(int)($r['dijadwalkan']??0));
                $success=max(0,(int)($r['berhasil']??0));
                $failed=max(0,(int)($r['gagal']??0));
                $note=trim((string)($r['catatan']??''));
                if($scheduled===0 && $success===0 && $failed===0 && $note==='') continue;
                if($success>$scheduled) throw new RuntimeException("Backup berhasil bulan {$bulan} tidak boleh melebihi jumlah jadwal.");
                if($failed>$scheduled) throw new RuntimeException("Backup gagal bulan {$bulan} tidak boleh melebihi jumlah jadwal.");
                if(($success+$failed)>$scheduled) throw new RuntimeException("Berhasil + gagal bulan {$bulan} melebihi jumlah backup yang dijadwalkan.");
                $periode=sprintf('%04d-%02d-01',$tahun,$bulan);
                $sql->execute([$indikator_id,$periode,$scheduled,$success,$failed,$note,$_SESSION['user']['id']??null]);
                $target=$ind['target']!==null?(float)$ind['target']:null;
                $cap=$scheduled>0?($success/$scheduled)*100:null;
                $status=$cap===null?'belum_dinilai':($target===null?'belum_dinilai':($cap>=$target?'tercapai':'tidak_tercapai'));
                $analisis=$failed>0 ? "Terdapat {$failed} backup gagal dari {$scheduled} jadwal.": "Backup berjalan sesuai catatan yang diinput.";
                $rtl=$failed>0 ? "Telusuri log Task Scheduler dan lakukan backup ulang/penanganan kegagalan.": "Tidak ada tindak lanjut khusus.";
                $capSql->execute([$indikator_id,$periode,$success,$scheduled,$cap,$target,$analisis,$rtl,$status,$_SESSION['user']['id']??null]);
                $saved++;
            }
            $msg="Data backup {$saved} bulan berhasil disimpan. Struktur sudah disiapkan untuk sumber otomatis dari Task Scheduler.";
        }

        if ($action==='upload_backup_evidence') {
            $indikator_id=(int)($_POST['indikator_id']??0);
            $tahun=(int)($_POST['tahun']??date('Y'));
            $jenis=$_POST['jenis']??'';
            if($indikator_id<1 || $tahun<2020 || $tahun>2100 || !in_array($jenis,['offline','online'],true)) throw new RuntimeException('Data bukti backup tidak valid.');
            $st=$pdo->prepare("SELECT id,kode FROM mutu_indikator WHERE id=?");
            $st->execute([$indikator_id]);$ind=$st->fetch();
            if(!$ind || $ind['kode']!=='IM-IT-03') throw new RuntimeException('Bukti ini khusus IM-IT-03.');
            if(empty($_FILES['backup_evidence']) || $_FILES['backup_evidence']['error']!==UPLOAD_ERR_OK) throw new RuntimeException('File bukti belum dipilih atau gagal diunggah.');
            $f=$_FILES['backup_evidence'];
            if($f['size']>20*1024*1024) throw new RuntimeException('Ukuran file maksimal 20 MB.');
            $allowed=['pdf','jpg','jpeg','png'];
            $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
            if(!in_array($ext,$allowed,true)) throw new RuntimeException('Bukti gunakan PDF, JPG, JPEG atau PNG.');
            $dir=__DIR__.'/../uploads/mutu-indikator/backup-evidence';
            if(!is_dir($dir) && !mkdir($dir,0775,true) && !is_dir($dir)) throw new RuntimeException('Folder bukti backup tidak dapat dibuat.');
            $oldSt=$pdo->prepare("SELECT nama_file FROM mutu_backup_bukti WHERE indikator_id=? AND tahun=? AND jenis=?");
            $oldSt->execute([$indikator_id,$tahun,$jenis]);$old=$oldSt->fetch();
            $safe=bin2hex(random_bytes(8)).'_'.preg_replace('/[^A-Za-z0-9._-]/','_',basename($f['name']));
            $dest=$dir.'/'.$safe;
            if(!move_uploaded_file($f['tmp_name'],$dest)) throw new RuntimeException('File bukti gagal disimpan.');
            $note=trim($_POST['catatan_bukti_backup']??'');
            $up=$pdo->prepare("INSERT INTO mutu_backup_bukti(indikator_id,tahun,jenis,nama_file,original_name,mime_type,size_bytes,catatan,uploaded_by) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE nama_file=VALUES(nama_file),original_name=VALUES(original_name),mime_type=VALUES(mime_type),size_bytes=VALUES(size_bytes),catatan=VALUES(catatan),uploaded_by=VALUES(uploaded_by),updated_at=CURRENT_TIMESTAMP");
            $up->execute([$indikator_id,$tahun,$jenis,$safe,$f['name'],$f['type']??'',(int)$f['size'],$note,$_SESSION['user']['id']??null]);
            if($old && !empty($old['nama_file'])) @unlink($dir.'/'.$old['nama_file']);
            $msg='Bukti backup '.ucfirst($jenis).' berhasil disimpan untuk laporan tahun '.$tahun.'.';
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

/* Sumber otomatis IM-IT-01 dan IM-IT-02 dari tabel downtime yang sama. */
$downtimeEvents=$pdo->query("SELECT mulai,selesai FROM downtime WHERE selesai IS NOT NULL AND selesai>mulai ORDER BY mulai")->fetchAll();
$downtimeAuto=[];
$today=new DateTime('today');

for($dm=1;$dm<=12;$dm++){
    $ds=new DateTime(sprintf('%04d-%02d-01 00:00:00',$year,$dm));
    $de=(clone $ds)->modify('+1 month');
    $intervals=[];
    foreach($downtimeEvents as $ev){
        $es=new DateTime($ev['mulai']);
        $ee=new DateTime($ev['selesai']);
        $cs=$es>$ds?$es:$ds;
        $ce=$ee<$de?$ee:$de;
        if($ce>$cs) $intervals[]=[$cs->getTimestamp(),$ce->getTimestamp()];
    }
    usort($intervals,fn($a,$b)=>$a[0]<=>$b[0]);
    $merged=[];
    foreach($intervals as $iv){
        if(!$merged || $iv[0]>$merged[count($merged)-1][1]) $merged[]=$iv;
        else $merged[count($merged)-1][1]=max($merged[count($merged)-1][1],$iv[1]);
    }
    $downSeconds=0;
    foreach($merged as $iv) $downSeconds += $iv[1]-$iv[0];

    $completed=($de <= $today);
    $hasEvent=($downSeconds>0);
    if(!$completed && !$hasEvent){
        $downtimeAuto[$dm]=null;
        continue;
    }

    $totalSeconds=$de->getTimestamp()-$ds->getTimestamp();
    $downtimeAuto[$dm]=[
        'downtime_minutes'=>round($downSeconds/60,2),
        'availability'=>$totalSeconds>0 ? round(max(0,($totalSeconds-$downSeconds)/$totalSeconds)*100,4) : 0,
        'has_event'=>$hasEvent,
        'completed'=>$completed
    ];
}

$heatmapData=[];
foreach($indikators as &$ii){
    $st=$pdo->prepare("SELECT MONTH(periode) bulan,capaian,status FROM mutu_capaian WHERE indikator_id=? AND YEAR(periode)=? ORDER BY periode");
    $st->execute([(int)$ii['id'],$year]);
    $m=[];
    foreach($st as $rr) $m[(int)$rr['bulan']]=['capaian'=>$rr['capaian'],'status'=>$rr['status']];

    if(in_array($ii['kode'],['IM-IT-01','IM-IT-02'],true)){
        $m=[];
        $latest=null;
        for($dm=1;$dm<=12;$dm++){
            $auto=$downtimeAuto[$dm]??null;
            if($auto===null) continue;

            $value=($ii['kode']==='IM-IT-01') ? $auto['availability'] : $auto['downtime_minutes'];
            $target=$ii['target']!==null?(float)$ii['target']:null;
            $arah=$ii['arah']??'sesuai_target';
            if($target===null){
                $status='belum_dinilai';
            }else{
                $ok=($arah==='turun') ? ($value<=$target) : (($arah==='naik') ? ($value>=$target) : ($value>=$target));
                $status=$ok?'tercapai':'tidak_tercapai';
            }
            $m[$dm]=['capaian'=>$value,'status'=>$status];
            if($auto['completed']) $latest=['capaian'=>$value,'status'=>$status,'bulan'=>$dm];
        }
        if($latest){
            $ii['capaian_terakhir']=$latest['capaian'];
            $ii['status_terakhir']=$latest['status'];
            $ii['jumlah_periode']=$latest['bulan'];
        }else{
            $ii['capaian_terakhir']=null;
            $ii['status_terakhir']='belum_dinilai';
        }
    }

    $heatmapData[(int)$ii['id']]=$m;
}
unset($ii);

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

   $backupByMonth=[];
   if($detail['kode']==='IM-IT-03'){
       $bst=$pdo->prepare("SELECT * FROM mutu_backup_harian WHERE indikator_id=? AND YEAR(periode)=? ORDER BY periode");
       $bst->execute([$detailId,$year]);
       foreach($bst as $br) $backupByMonth[(int)date('n',strtotime($br['periode']))]=$br;
   }

   $backupEvidence=[];
   if($detail['kode']==='IM-IT-03'){
       $est=$pdo->prepare("SELECT * FROM mutu_backup_bukti WHERE indikator_id=? AND tahun=? ORDER BY FIELD(jenis,'offline','online')");
       $est->execute([$detailId,$year]);
       $backupEvidence=$est->fetchAll();
   }

   if(in_array($detail['kode'],['IM-IT-01','IM-IT-02'],true)){
       for($dm=1;$dm<=12;$dm++){
           $auto=$downtimeAuto[$dm]??null;
           if($auto===null) continue;

           $target=$detail['target']!==null?(float)$detail['target']:null;
           $arah=$detail['arah']??'sesuai_target';
           $value=($detail['kode']==='IM-IT-01') ? $auto['availability'] : $auto['downtime_minutes'];
           $status='belum_dinilai';
           if($target!==null){
               $ok=($arah==='turun') ? ($value<=$target) : (($arah==='naik') ? ($value>=$target) : ($value>=$target));
               $status=$ok?'tercapai':'tidak_tercapai';
           }

           $period=sprintf('%04d-%02d-01',$year,$dm);
           $existing=$byMonth[$dm]??[];
           $ds=new DateTime($period);
           $monthEnd=(clone $ds)->modify('+1 month');
           $hours=($monthEnd->getTimestamp()-$ds->getTimestamp())/3600;
           $byMonth[$dm]=array_merge($existing,[
               'periode'=>$period,
               'periode_label'=>sprintf('%04d-%02d',$year,$dm),
               'numerator'=>($detail['kode']==='IM-IT-01') ? round(($value/100)*$hours,4) : $auto['downtime_minutes'],
               'denominator'=>($detail['kode']==='IM-IT-01') ? round($hours,4) : 0,
               'capaian'=>$value,
               'target_snapshot'=>$target,
               'status'=>$status,
               'analisis'=>$existing['analisis']??'',
               'tindak_lanjut'=>$existing['tindak_lanjut']??''
           ]);
       }
   }

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
.chart-wrap{position:relative;height:310px}.trend-chart-lg{height:430px}.trend-card{border-radius:18px;background:linear-gradient(135deg,#ffffff 0%,#f4fbf7 100%);box-shadow:0 8px 24px rgba(17,63,52,.08)}.trend-summary{display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:#eef8f2}.trend-dot{width:10px;height:10px;border-radius:50%;background:#198754;display:inline-block}.legend-chip{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:600}.legend-green{background:#e8f5ee;color:#198754}.legend-red{background:#fdebed;color:#dc3545}.legend-gray{background:#eef1f3;color:#6c757d}.legend-target{background:#fff3cd;color:#9a6b00}.monthly-input-table{min-width:1100px}.monthly-input-table th{white-space:nowrap}.monthly-input-table input{min-width:95px}.monthly-input-table td:nth-child(7),.monthly-input-table td:nth-child(8){min-width:180px}.profile-label{font-size:12px;color:#6c757d;text-transform:uppercase;font-weight:700}.profile-value{font-weight:600}
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
  <?php foreach($indikators as $ii):?><div class="hm-name"><?=h($ii['kode'])?><br><span class="small text-muted"><?=h($ii['nama'])?></span></div><?php for($mm=1;$mm<=12;$mm++):$hm=$heatmapData[(int)$ii['id']][$mm]??null;$hs=$hm['status']??'';$hc=$hs==='tercapai'?'hm-ok':($hs==='tidak_tercapai'?'hm-bad':($hs==='perlu_perhatian'?'hm-warn':'hm-empty'));?><div class="<?=$hc?>" title="<?php
      if(($ii['kode']??'')==='IM-IT-02' && $hm && $hm['capaian']!==null){
          echo h(round((float)$hm['capaian'],2).' menit downtime');
      } else {
          echo $hm&&$hm['capaian']!==null ? h(round((float)$hm['capaian'],2).' '.($ii['satuan']??'')) : 'Belum diverifikasi / belum ada data';
      }
    ?>"><?php
      if(($ii['kode']??'')==='IM-IT-02'){
          echo $hm&&$hm['capaian']!==null ? h(round((float)$hm['capaian'],1)) : '—';
      } else {
          echo $hm&&$hm['capaian']!==null ? h(round((float)$hm['capaian'],1)) : '—';
      }
    ?></div><?php endfor;endforeach;?>
 </div></div>
 <div class="small text-muted mt-2">🟢 tercapai / 0 menit untuk IM-IT-02 · 🟡 perlu perhatian · 🔴 tidak tercapai / ada downtime · ⚪ belum ada data.</div>
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
  <td class="text-nowrap">
  <a class="btn btn-sm btn-outline-primary mb-1" href="?detail=<?=$i['id']?>">Capaian</a>
  <a class="btn btn-sm btn-outline-success mb-1" href="?map=<?=$i['id']?>">EP</a>
  <a class="btn btn-sm btn-outline-secondary mb-1" href="?edit=<?=$i['id']?>">Edit</a>
  <a class="btn btn-sm btn-outline-danger mb-1" target="_blank" href="laporan.php?id=<?=$i['id']?>&tahun=<?=$year?>">📄 Download Laporan</a>
</td>
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
<div class="card shadow-sm mb-4 border-0 trend-card">
 <div class="card-body p-4">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
   <div>
    <div class="small text-uppercase fw-bold text-success">Analisis capaian</div>
    <h4 class="mb-1 fw-bold">Trend <?=h($detail['nama'])?> — <?=h($year)?></h4>
    <div class="text-muted small">Perkembangan capaian Januari–Desember. Garis putus-putus menunjukkan target indikator.</div>
   </div>
   <div class="trend-summary">
    <span class="trend-dot"></span>
    <span class="small fw-semibold"><?=h($detail['satuan'])?></span>
   </div>
  </div>
  <div class="trend-legend mt-3 d-flex flex-wrap gap-2">
    <span class="legend-chip legend-green">● Tercapai</span>
    <span class="legend-chip legend-red">● Tidak tercapai</span>
    <span class="legend-chip legend-gray">● Belum ada data</span>
    <span class="legend-chip legend-target">━━ Target</span>
  </div>
  <div class="chart-wrap trend-chart-lg mt-3"><canvas id="trendChart" aria-label="Grafik tren 12 bulan"></canvas></div>
 </div>
</div>

<?php if(($detail['kode']??'')==='IM-IT-02'): ?>
<div class="card border-success shadow-sm mb-4"><div class="card-body">
 <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div><h6 class="mb-1">⏱️ Ambil otomatis dari Downtime SIMRS</h6>
   <div class="small text-muted">IM-IT-02 membaca semua kejadian dari menu Downtime, menjumlahkan durasi yang masuk ke setiap bulan, dan mengisi total menit downtime secara otomatis.</div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
   <a class="btn btn-outline-success" href="../downtime/">📋 Lihat Data Downtime</a>
   <form method="post" class="m-0" onsubmit="return confirm('Ambil ulang IM-IT-02 dari seluruh data Downtime tahun <?=h($year)?>?');">
    <input type="hidden" name="action" value="hitung_downtime_simrs">
    <input type="hidden" name="indikator_id" value="<?=$detail['id']?>">
    <input type="hidden" name="tahun" value="<?=$year?>">
    <button class="btn btn-success">🔄 Ambil dari Downtime</button>
   </form>
  </div>
 </div>
 <div class="alert alert-warning mt-3 mb-0 small">
  <strong>Catatan:</strong> N = total menit downtime, D = jumlah kejadian, Capaian = total menit downtime.
  Bulan tanpa kejadian tidak otomatis dianggap 0; tetap <strong>Belum Ada Data</strong> sampai diverifikasi.
 </div>
</div></div>
<?php endif; ?>

<?php if(($detail['kode']??'')==='IM-IT-01'): ?>
<div class="card border-success shadow-sm mb-4"><div class="card-body">
 <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div><h6 class="mb-1">⚙️ Hitung otomatis dari Downtime SIMRS</h6>
   <div class="small text-muted">IM-IT-01 membaca <strong>tabel Downtime</strong> yang sama dengan IM-IT-02. Semua kejadian dalam bulan dijumlahkan otomatis; bulan selesai tanpa downtime menjadi <strong>100%</strong>.</div>
  </div>
<div class="d-flex gap-2 flex-wrap">
   <a class="btn btn-outline-success" href="../downtime/tambah.php">➕ Catat Downtime</a>
   <form method="post" class="m-0" onsubmit="return confirm('Ambil data IM-IT-01 langsung dari tabel Downtime untuk tahun <?=h($year)?>? Data capaian bulan selesai akan disesuaikan otomatis.');">
    <input type="hidden" name="action" value="sinkronkan_im_it_01">
    <input type="hidden" name="indikator_id" value="<?=$detail['id']?>">
    <input type="hidden" name="tahun" value="<?=$year?>">
    <button class="btn btn-success">🔄 Ambil dari Downtime</button>
   </form>
  </div>
 </div>
 <div class="alert alert-warning mt-3 mb-0 small"><strong>Sumber data:</strong> IM-IT-01 dan IM-IT-02 sama-sama mengambil kejadian dari tabel <strong>downtime</strong>. September, misalnya, jika tidak ada kejadian downtime dan bulannya sudah selesai, otomatis menjadi <strong>0 menit downtime</strong> pada IM-IT-02 dan <strong>100% ketersediaan</strong> pada IM-IT-01.</div>
</div></div>
<?php endif; ?>

<?php if(($detail['kode']??'')==='IM-IT-03'): ?>
<div class="card border-success shadow-sm mb-4"><div class="card-body">
 <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div><h5 class="mb-1">💾 Rekap Backup Harian SIMRS</h5>
   <div class="small text-muted">Rekap bulanan diinput di MRMIKIT. Bukti cukup perwakilan: satu untuk backup offline dan satu untuk backup online. Sumber bukti: Windows Task Scheduler 03.00, log task, dan file backup. Struktur tabel disiapkan agar tahap berikutnya dapat membaca data otomatis.</div>
  </div>
  <span class="badge bg-success">IM-IT-03</span>
 </div>
 <form method="post" id="formBackup12">
  <input type="hidden" name="action" value="save_backup_bulanan">
  <input type="hidden" name="indikator_id" value="<?=$detail['id']?>">
  <input type="hidden" name="tahun" value="<?=$year?>">
  <div class="table-responsive mt-3">
   <table class="table table-bordered table-sm align-middle monthly-input-table">
    <thead><tr><th>Bulan</th><th>Jadwal</th><th>Berhasil</th><th>Gagal</th><th>Capaian</th><th>Status</th><th>Catatan / bukti</th></tr></thead>
    <tbody>
    <?php for($bm=1;$bm<=12;$bm++):
      $br=$backupByMonth[$bm]??null;
      $days=(int)cal_days_in_month(CAL_GREGORIAN,$bm,$year);
      $bd=(int)($br['dijadwalkan']??$days);
      $bs=(int)($br['berhasil']??0); $bf=(int)($br['gagal']??0);
      $bc=$bd>0?round($bs/$bd*100,2):null;
      $bst=$bc===null?'belum_dinilai':($detail['target']===null?'belum_dinilai':($bc>=(float)$detail['target']?'tercapai':'tidak_tercapai'));
    ?>
    <tr>
      <td><strong><?=h($monthNames[$bm-1])?></strong></td>
      <td><input type="number" min="0" name="backup[<?=$bm?>][dijadwalkan]" value="<?=$bd?>" class="form-control form-control-sm backup-scheduled"></td>
      <td><input type="number" min="0" name="backup[<?=$bm?>][berhasil]" value="<?=$bs?>" class="form-control form-control-sm backup-success"></td>
      <td><input type="number" min="0" name="backup[<?=$bm?>][gagal]" value="<?=$bf?>" class="form-control form-control-sm backup-failed"></td>
      <td class="backup-cap text-center fw-bold"><?= $bc!==null?h($bc.' %'):'—' ?></td>
      <td class="backup-status text-center"><span class="status-pill <?=($bst==='tercapai'?'status-tercapai':($bst==='tidak_tercapai'?'status-tidak':'status-belum'))?>"><?=h(strtoupper(str_replace('_',' ',$bst)))?></span></td>
      <td><input type="text" name="backup[<?=$bm?>][catatan]" value="<?=h($br['catatan']??'')?>" class="form-control form-control-sm" placeholder="Contoh: Task Scheduler berhasil; file backup tersedia"></td>
    </tr>
    <?php endfor; ?>
    </tbody>
   </table>
  </div>
  <div class="alert alert-info small mb-3"><strong>Contoh:</strong> jika Januari dijadwalkan 31 kali, berhasil 31, gagal 0 → capaian 100%. Jangan mengisi berhasil 100% tanpa memeriksa bukti backup.</div>
  <button class="btn btn-success">💾 Simpan Rekap Backup</button>
 </form>

 <div class="mt-4 p-3 border rounded bg-light">
  <h6 class="mb-1">📎 Bukti Perwakilan Backup <?=h($year)?></h6>
  <div class="small text-muted mb-3">Cukup satu bukti perwakilan untuk backup <strong>offline</strong> dan satu untuk <strong>online</strong>. Bukti ini dipakai kembali pada laporan/cetak PDF seluruh tahun, sehingga tidak perlu upload SS setiap hari.</div>
  <div class="row g-3">
   <?php foreach(['offline'=>'Backup Offline','online'=>'Backup Online'] as $jenisB=>$labelB): $eb=null; foreach($backupEvidence as $x){if($x['jenis']===$jenisB){$eb=$x;break;}} ?>
   <div class="col-md-6">
    <div class="border rounded p-3 h-100 bg-white">
     <strong><?=h($labelB)?></strong>
     <?php if($eb): ?>
       <div class="small mt-2"><a href="download_backup_evidence.php?id=<?=$eb['id']?>" target="_blank">📄 <?=h($eb['original_name'])?></a></div>
       <div class="small text-muted"><?=h($eb['catatan']??'')?></div>
       <div class="small text-success mt-1">✓ Digunakan pada laporan tahun <?=h($year)?></div>
     <?php else: ?>
       <div class="small text-muted mt-2">Belum ada bukti.</div>
     <?php endif; ?>
     <form method="post" enctype="multipart/form-data" class="mt-2">
      <input type="hidden" name="action" value="upload_backup_evidence">
      <input type="hidden" name="indikator_id" value="<?=$detail['id']?>">
      <input type="hidden" name="tahun" value="<?=$year?>">
      <input type="hidden" name="jenis" value="<?=$jenisB?>">
      <input type="file" name="backup_evidence" class="form-control form-control-sm mb-2" required accept=".pdf,.jpg,.jpeg,.png">
      <input type="text" name="catatan_bukti_backup" value="<?=h($eb['catatan']??'')?>" class="form-control form-control-sm mb-2" placeholder="Contoh: SS Task Scheduler 03.00 dan hasil file backup tersedia">
      <button class="btn btn-sm btn-outline-success">📎 <?= $eb?'Ganti bukti':'Upload bukti' ?></button>
     </form>
    </div>
   </div>
   <?php endforeach; ?>
  </div>
 </div>
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
     <?php endfor; ?>
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
 const statuses=<?=json_encode(array_map(function($m)use($byMonth){$r=$byMonth[$m]??null;return $r['status']??null;},range(1,12)))?>;
 const dpr=window.devicePixelRatio||1;
 const palette=['#198754','#0d6efd','#fd7e14','#6f42c1','#d63384','#20c997','#0dcaf0','#ffc107','#dc3545','#6c757d','#6610f2','#198754'];

 function draw(){
   const w=Math.max(canvas.clientWidth||980,760), h=Math.max(canvas.clientHeight||430,430);
   canvas.width=w*dpr; canvas.height=h*dpr;
   ctx.setTransform(dpr,0,0,dpr,0,0);
   ctx.clearRect(0,0,w,h);

   const pad={l:62,r:26,t:34,b:58}, pw=w-pad.l-pad.r, ph=h-pad.t-pad.b;
   const vals=data.filter(v=>v!==null);
   if(!vals.length){
     ctx.fillStyle='#6c757d';ctx.font='15px Arial';ctx.fillText('Belum ada capaian untuk tahun ini.',pad.l,pad.t+30);return;
   }

   let min=Math.min(...vals,target??Infinity), max=Math.max(...vals,target??-Infinity);
   if(!Number.isFinite(min)) min=0;
   if(!Number.isFinite(max)) max=100;
   let range=Math.max(max-min,1);
   if((max-min)<2){const mid=(max+min)/2;min=mid-1.2;max=mid+1.2;}
   else {min-=range*.08;max+=range*.08;}

   const x=i=>pad.l+(pw*i/11);
   const y=v=>pad.t+(max-v)*ph/(max-min);

   // plot background
   const grad=ctx.createLinearGradient(0,pad.t,0,h-pad.b);
   grad.addColorStop(0,'#f8fffb');grad.addColorStop(1,'#ffffff');
   ctx.fillStyle=grad;ctx.fillRect(pad.l,pad.t,pw,ph);

   // grid + y labels
   ctx.font='12px Arial';
   for(let g=0;g<=5;g++){
     const yy=pad.t+ph*g/5;
     ctx.strokeStyle='#e5eee9';ctx.lineWidth=1;
     ctx.beginPath();ctx.moveTo(pad.l,yy);ctx.lineTo(w-pad.r,yy);ctx.stroke();
     ctx.fillStyle='#718096';ctx.textAlign='right';
     ctx.fillText((max-(max-min)*g/5).toFixed(1),pad.l-10,yy+4);
   }

   // target line
   if(target!==null){
     const ty=y(target);
     ctx.save();
     ctx.strokeStyle='#f59f00';ctx.lineWidth=2;ctx.setLineDash([8,6]);
     ctx.beginPath();ctx.moveTo(pad.l,ty);ctx.lineTo(w-pad.r,ty);ctx.stroke();
     ctx.restore();
   }

   // month labels
   ctx.fillStyle='#4a5568';ctx.font='12px Arial';ctx.textAlign='center';
   labels.forEach((lab,i)=>ctx.fillText(lab.slice(0,3),x(i),h-24));

   // colored gradient line by segment
   for(let i=0;i<11;i++){
     if(data[i]===null||data[i+1]===null) continue;
     const x1=x(i),y1=y(data[i]),x2=x(i+1),y2=y(data[i+1]);
     const st=statuses[i], et=statuses[i+1];
     const color = st==='tidak_tercapai'||et==='tidak_tercapai' ? '#dc3545' :
                   st==='perlu_perhatian'||et==='perlu_perhatian' ? '#f59f00' : '#198754';
     ctx.strokeStyle=color;ctx.lineWidth=4;ctx.lineCap='round';
     ctx.beginPath();ctx.moveTo(x1,y1);ctx.lineTo(x2,y2);ctx.stroke();
   }

   // area fill under line
   const valid=data.map((v,i)=>v!==null?i:null).filter(v=>v!==null);
   if(valid.length){
     const first=valid[0], last=valid[valid.length-1];
     const area=ctx.createLinearGradient(0,pad.t,0,h-pad.b);
     area.addColorStop(0,'rgba(25,135,84,.20)');area.addColorStop(1,'rgba(25,135,84,0.01)');
     ctx.fillStyle=area;ctx.beginPath();ctx.moveTo(x(first),y(data[first]));
     for(let i=first+1;i<=last;i++){if(data[i]!==null)ctx.lineTo(x(i),y(data[i]));}
     ctx.lineTo(x(last),pad.t+ph);ctx.lineTo(x(first),pad.t+ph);ctx.closePath();ctx.fill();
   }

   // points + labels + halo
   data.forEach((v,i)=>{
     if(v===null) return;
     const xx=x(i), yy=y(v);
     const st=statuses[i];
     const color=st==='tidak_tercapai'?'#dc3545':st==='perlu_perhatian'?'#f59f00':'#198754';
     ctx.beginPath();ctx.fillStyle='rgba(255,255,255,.95)';ctx.arc(xx,yy,8,0,Math.PI*2);ctx.fill();
     ctx.beginPath();ctx.fillStyle=color;ctx.arc(xx,yy,5,0,Math.PI*2);ctx.fill();
     ctx.fillStyle='#24323d';ctx.font='bold 12px Arial';ctx.textAlign='center';
     const suffix=<?=json_encode($detail['satuan'])?>;
     ctx.fillText(v.toFixed(2)+' '+suffix,xx,yy-14);
   });

   // title on canvas
   ctx.fillStyle='#123f34';ctx.font='bold 14px Arial';ctx.textAlign='left';
   ctx.fillText('Capaian bulanan',pad.l,18);
   if(target!==null){
     ctx.fillStyle='#9a6b00';ctx.font='12px Arial';ctx.textAlign='right';
     ctx.fillText('Target: '+target.toFixed(2)+' '+<?=json_encode($detail['satuan'])?>,w-pad.r,18);
   }
 }
 draw(); window.addEventListener('resize',draw);
})();;
document.querySelectorAll('#formBackup12 tr').forEach(function(row){
 const s=row.querySelector('.backup-scheduled'),ok=row.querySelector('.backup-success'),bad=row.querySelector('.backup-failed'),cap=row.querySelector('.backup-cap'),st=row.querySelector('.backup-status');
 if(!s||!ok||!bad||!cap||!st)return;
 function calc(){
   const d=Number(s.value||0),v=Number(ok.value||0),f=Number(bad.value||0);
   const c=d>0?(v/d*100):null;
   cap.textContent=c===null?'—':c.toFixed(2)+' %';
   const target=<?=json_encode($detail['target']!==null?(float)$detail['target']:null)?>;
   let status='BELUM DINILAI', cls='status-belum';
   if(c!==null && target!==null){status=c>=target?'TERCAPAI':'TIDAK TERCAPAI';cls=c>=target?'status-tercapai':'status-tidak';}
   st.innerHTML='<span class="status-pill '+cls+'">'+status+'</span>';
 }
 [s,ok,bad].forEach(x=>x.addEventListener('input',calc)); calc();
});
document.querySelectorAll('#formCapaian12 tr').forEach(function(row){
 const n=row.querySelector('.month-num'),d=row.querySelector('.month-den'),cap=row.querySelector('.month-cap');if(!n||!d||!cap)return;
 function calc(){if(n.value!==''&&d.value!==''&&Number(d.value)!==0)cap.value=(Number(n.value)/Number(d.value)*100).toFixed(4);}
 n.addEventListener('input',calc);d.addEventListener('input',calc);
});
</script>

<?php require __DIR__.'/../partials/footer.php';