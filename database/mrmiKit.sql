CREATE DATABASE IF NOT EXISTS mrmiKit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mrmiKit;

CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL, nama VARCHAR(150) NOT NULL, role VARCHAR(30) NOT NULL DEFAULT 'admin', aktif TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
INSERT IGNORE INTO users(username,password_hash,nama,role) VALUES ('admin', '$2y$12$Ha.uANREfvtDU40P.dbyteFioAFpu9HS/zS2SH693O3uOW.AMfDRS', 'Administrator IT', 'admin');

CREATE TABLE IF NOT EXISTS elemen_penilaian (id INT AUTO_INCREMENT PRIMARY KEY, kode VARCHAR(30) UNIQUE NOT NULL, kategori VARCHAR(100), judul VARCHAR(255) NOT NULL, deskripsi TEXT, urutan INT NOT NULL DEFAULT 0);
INSERT IGNORE INTO elemen_penilaian(kode,kategori,judul,deskripsi,urutan) VALUES
('MRMIK 13.a','MRMIK 13','Regulasi penyelenggaraan TI kesehatan','Regulasi internal penyelenggaraan teknologi informasi kesehatan.',1),
('MRMIK 13.b','MRMIK 13','Penerapan SIMRS','Penerapan sistem informasi manajemen rumah sakit.',2),
('MRMIK 13.c','MRMIK 13','Unit penyelenggara SIMRS dan staf kompeten','Unit, penanggung jawab, SDM, kompetensi dan uraian tugas.',3),
('MRMIK 13.d','MRMIK 13','Integrasi data klinis dan nonklinis','Peta dan bukti integrasi sistem informasi.',4),
('MRMIK 13.e','MRMIK 13','Evaluasi efektivitas RME dan perbaikan','Evaluasi efektivitas RME, temuan dan tindak lanjut.',5),
('MRMIK 13.1.a','MRMIK 13.1','Prosedur downtime','Prosedur saat sistem tidak tersedia.',6),
('MRMIK 13.1.b','MRMIK 13.1','Pelatihan staf downtime','Pelatihan dan pemahaman staf terkait downtime.',7),
('MRMIK 13.1.c','MRMIK 13.1','Evaluasi setelah downtime','Evaluasi kejadian downtime dan perbaikan.',8);

CREATE TABLE IF NOT EXISTS evidence_requirement (id INT AUTO_INCREMENT PRIMARY KEY, ep_id INT NOT NULL, kode VARCHAR(30) UNIQUE NOT NULL, nama VARCHAR(255) NOT NULL, jenis ENUM('R','D','O') NOT NULL, wajib TINYINT(1) DEFAULT 1, urutan INT DEFAULT 0, FOREIGN KEY(ep_id) REFERENCES elemen_penilaian(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS pic (id INT AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(150) NOT NULL, unit VARCHAR(150), jabatan VARCHAR(150), email VARCHAR(150), aktif TINYINT(1) DEFAULT 1);
CREATE TABLE IF NOT EXISTS dokumen (id INT AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(255) NOT NULL, kategori VARCHAR(100), nomor VARCHAR(100), versi VARCHAR(30) DEFAULT '1.0', tanggal_berlaku DATE NULL, tanggal_review DATE NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255), status ENUM('lengkap','perlu_review','belum_lengkap') DEFAULT 'lengkap', pic_id INT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY(pic_id) REFERENCES pic(id) ON DELETE SET NULL);
CREATE TABLE IF NOT EXISTS dokumen_versi (id INT AUTO_INCREMENT PRIMARY KEY, dokumen_id INT NOT NULL, versi VARCHAR(30) NOT NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255), catatan TEXT, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(dokumen_id) REFERENCES dokumen(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS dokumen_ep (dokumen_id INT NOT NULL, ep_id INT NOT NULL, PRIMARY KEY(dokumen_id,ep_id), FOREIGN KEY(dokumen_id) REFERENCES dokumen(id) ON DELETE CASCADE, FOREIGN KEY(ep_id) REFERENCES elemen_penilaian(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS bukti_implementasi (id INT AUTO_INCREMENT PRIMARY KEY, jenis ENUM('R','D','O') NOT NULL, judul VARCHAR(255) NOT NULL, deskripsi TEXT, tanggal_bukti DATE NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255), pic_id INT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(pic_id) REFERENCES pic(id) ON DELETE SET NULL);
CREATE TABLE IF NOT EXISTS bukti_ep (bukti_id INT NOT NULL, ep_id INT NOT NULL, requirement_id INT NULL, PRIMARY KEY(bukti_id,ep_id), FOREIGN KEY(bukti_id) REFERENCES bukti_implementasi(id) ON DELETE CASCADE, FOREIGN KEY(ep_id) REFERENCES elemen_penilaian(id) ON DELETE CASCADE, FOREIGN KEY(requirement_id) REFERENCES evidence_requirement(id) ON DELETE SET NULL);
CREATE TABLE IF NOT EXISTS downtime (id INT AUTO_INCREMENT PRIMARY KEY, mulai DATETIME NOT NULL, selesai DATETIME NULL, jenis VARCHAR(30) NOT NULL, penyebab TEXT, unit_terdampak TEXT, tindakan TEXT, evaluasi TEXT, tindak_lanjut TEXT, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS audit_log (id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, aksi VARCHAR(100), objek VARCHAR(100), objek_id INT NULL, detail TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);

INSERT IGNORE INTO pic(nama,unit,jabatan) VALUES ('Administrator IT','IT','Administrator');

INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'13a-R1','Regulasi/kebijakan penyelenggaraan TI kesehatan','R',1,1 FROM elemen_penilaian WHERE kode='MRMIK 13.a';
INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'13b-D1','Bukti penerapan SIMRS','D',1,1 FROM elemen_penilaian WHERE kode='MRMIK 13.b';
INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'13c-R1','SK/struktur unit penyelenggara SIMRS','R',1,1 FROM elemen_penilaian WHERE kode='MRMIK 13.c';
INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'13c-D1','Bukti kompetensi dan uraian tugas staf','D',1,2 FROM elemen_penilaian WHERE kode='MRMIK 13.c';
INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'13d-D1','Diagram/peta integrasi sistem','D',1,1 FROM elemen_penilaian WHERE kode='MRMIK 13.d';
INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'13d-O1','Bukti implementasi integrasi','O',1,2 FROM elemen_penilaian WHERE kode='MRMIK 13.d';
INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'13e-D1','Evaluasi efektivitas RME dan tindak lanjut','D',1,1 FROM elemen_penilaian WHERE kode='MRMIK 13.e';
INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'131a-R1','SOP/prosedur downtime','R',1,1 FROM elemen_penilaian WHERE kode='MRMIK 13.1.a';
INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'131b-D1','Bukti pelatihan staf downtime','D',1,1 FROM elemen_penilaian WHERE kode='MRMIK 13.1.b';
INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'131c-D1','Evaluasi kejadian downtime dan tindak lanjut','D',1,1 FROM elemen_penilaian WHERE kode='MRMIK 13.1.c';


CREATE TABLE IF NOT EXISTS import_batch (id INT AUTO_INCREMENT PRIMARY KEY, nama_batch VARCHAR(255) NOT NULL, tahun_sumber YEAR NOT NULL, filename_zip VARCHAR(255), total_file INT DEFAULT 0, status ENUM('diunggah','dipetakan','selesai') DEFAULT 'diunggah', created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS import_file (id INT AUTO_INCREMENT PRIMARY KEY, batch_id INT NOT NULL, relative_path VARCHAR(500) NOT NULL, original_name VARCHAR(255) NOT NULL, extension VARCHAR(20), size_bytes BIGINT DEFAULT 0, tahun_sumber YEAR NOT NULL, ep_id INT NULL, dokumen_id INT NULL, status ENUM('belum_dipetakan','dipetakan','diabaikan') DEFAULT 'belum_dipetakan', catatan TEXT, FOREIGN KEY(batch_id) REFERENCES import_batch(id) ON DELETE CASCADE, FOREIGN KEY(ep_id) REFERENCES elemen_penilaian(id) ON DELETE SET NULL, FOREIGN KEY(dokumen_id) REFERENCES dokumen(id) ON DELETE SET NULL);
CREATE TABLE IF NOT EXISTS dokumen_sumber (dokumen_id INT NOT NULL, tahun_sumber YEAR NOT NULL, batch_id INT NULL, sumber ENUM('akreditasi_lama','baru') DEFAULT 'akreditasi_lama', PRIMARY KEY(dokumen_id,tahun_sumber), FOREIGN KEY(dokumen_id) REFERENCES dokumen(id) ON DELETE CASCADE, FOREIGN KEY(batch_id) REFERENCES import_batch(id) ON DELETE SET NULL);

ALTER TABLE dokumen ADD COLUMN IF NOT EXISTS status_akreditasi ENUM('arsip','review','aktif_2026','tidak_berlaku') NOT NULL DEFAULT 'arsip';
ALTER TABLE dokumen ADD COLUMN IF NOT EXISTS tahun_aktif YEAR NULL;
ALTER TABLE dokumen ADD COLUMN IF NOT EXISTS reviewed_by INT NULL;
ALTER TABLE dokumen ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL;
ALTER TABLE dokumen ADD COLUMN IF NOT EXISTS review_catatan TEXT NULL;
ALTER TABLE import_file ADD COLUMN IF NOT EXISTS reviewed_by INT NULL;
ALTER TABLE import_file ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL;
ALTER TABLE import_file ADD COLUMN IF NOT EXISTS review_status ENUM('belum_review','disetujui','perlu_revisi','ditolak') NOT NULL DEFAULT 'belum_review';
\n
/* Modul Indikator Mutu IT */
CREATE TABLE IF NOT EXISTS mutu_indikator (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(50) NOT NULL UNIQUE,
    nama VARCHAR(255) NOT NULL,
    definisi_operasional TEXT,
    numerator_label VARCHAR(255),
    denominator_label VARCHAR(255),
    formula TEXT,
    target DECIMAL(12,4) NULL,
    satuan VARCHAR(50) DEFAULT '%',
    arah ENUM('naik','turun','sesuai_target') NOT NULL DEFAULT 'sesuai_target',
    frekuensi ENUM('bulanan','triwulan','semester','tahunan') NOT NULL DEFAULT 'bulanan',
    sumber_data VARCHAR(255),
    metode_pengumpulan TEXT,
    pic_id INT NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY(pic_id) REFERENCES pic(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS mutu_capaian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    indikator_id INT NOT NULL,
    periode DATE NOT NULL,
    numerator DECIMAL(14,4) NULL,
    denominator DECIMAL(14,4) NULL,
    capaian DECIMAL(12,4) NULL,
    target_snapshot DECIMAL(12,4) NULL,
    analisis TEXT,
    tindak_lanjut TEXT,
    status ENUM('tercapai','perlu_perhatian','tidak_tercapai','belum_dinilai') NOT NULL DEFAULT 'belum_dinilai',
    filename VARCHAR(255) NULL,
    original_name VARCHAR(255) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mutu_periode (indikator_id, periode),
    FOREIGN KEY(indikator_id) REFERENCES mutu_indikator(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS mutu_indikator_ep (
    indikator_id INT NOT NULL,
    ep_id INT NOT NULL,
    PRIMARY KEY(indikator_id, ep_id),
    FOREIGN KEY(indikator_id) REFERENCES mutu_indikator(id) ON DELETE CASCADE,
    FOREIGN KEY(ep_id) REFERENCES elemen_penilaian(id) ON DELETE CASCADE
);
INSERT IGNORE INTO mutu_indikator(kode,nama,satuan,frekuensi,arah) VALUES
('IM-IT-01','Ketersediaan SIMRS','%','bulanan','naik'),
('IM-IT-02','Downtime SIMRS','menit','bulanan','turun'),
('IM-IT-03','Keberhasilan backup data','%','bulanan','naik'),
('IM-IT-04','Keberhasilan uji restore backup','%','bulanan','naik'),
('IM-IT-05','Penyelesaian insiden IT sesuai SLA','%','bulanan','naik'),
('IM-IT-06','Keberhasilan integrasi data sistem','%','bulanan','naik'),
('IM-IT-07','Ketersediaan RME','%','bulanan','naik'),
('IM-IT-08','Kepatuhan review akses pengguna','%','bulanan','naik');
INSERT IGNORE INTO mutu_indikator_ep(indikator_id,ep_id)
SELECT i.id,e.id FROM mutu_indikator i JOIN elemen_penilaian e ON e.kode='MRMIK 13.b' WHERE i.kode='IM-IT-01';
INSERT IGNORE INTO mutu_indikator_ep(indikator_id,ep_id)
SELECT i.id,e.id FROM mutu_indikator i JOIN elemen_penilaian e ON e.kode='MRMIK 13.1.a' WHERE i.kode='IM-IT-02';
INSERT IGNORE INTO mutu_indikator_ep(indikator_id,ep_id)
SELECT i.id,e.id FROM mutu_indikator i JOIN elemen_penilaian e ON e.kode='MRMIK 13.b' WHERE i.kode='IM-IT-03';
INSERT IGNORE INTO mutu_indikator_ep(indikator_id,ep_id)
SELECT i.id,e.id FROM mutu_indikator i JOIN elemen_penilaian e ON e.kode='MRMIK 13.b' WHERE i.kode='IM-IT-04';
INSERT IGNORE INTO mutu_indikator_ep(indikator_id,ep_id)
SELECT i.id,e.id FROM mutu_indikator i JOIN elemen_penilaian e ON e.kode='MRMIK 13.c' WHERE i.kode='IM-IT-05';
INSERT IGNORE INTO mutu_indikator_ep(indikator_id,ep_id)
SELECT i.id,e.id FROM mutu_indikator i JOIN elemen_penilaian e ON e.kode='MRMIK 13.d' WHERE i.kode='IM-IT-06';
INSERT IGNORE INTO mutu_indikator_ep(indikator_id,ep_id)
SELECT i.id,e.id FROM mutu_indikator i JOIN elemen_penilaian e ON e.kode='MRMIK 13.e' WHERE i.kode='IM-IT-07';
INSERT IGNORE INTO mutu_indikator_ep(indikator_id,ep_id)
SELECT i.id,e.id FROM mutu_indikator i JOIN elemen_penilaian e ON e.kode='MRMIK 13.c' WHERE i.kode='IM-IT-08';


CREATE TABLE IF NOT EXISTS mutu_bukti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    capaian_id INT NOT NULL,
    nama_file VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(150) NULL,
    size_bytes BIGINT NOT NULL DEFAULT 0,
    catatan TEXT NULL,
    uploaded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (capaian_id) REFERENCES mutu_capaian(id) ON DELETE CASCADE
);


-- Upgrade Downtime: multi-kejadian, dampak, PIC, sumber data dan bukti
ALTER TABLE downtime ADD COLUMN IF NOT EXISTS dampak VARCHAR(30) NOT NULL DEFAULT 'total' AFTER unit_terdampak;
ALTER TABLE downtime ADD COLUMN IF NOT EXISTS pic_id INT NULL AFTER tindak_lanjut;
ALTER TABLE downtime ADD COLUMN IF NOT EXISTS sumber_data VARCHAR(30) NOT NULL DEFAULT 'monitoring' AFTER pic_id;
ALTER TABLE downtime ADD COLUMN IF NOT EXISTS bukti_filename VARCHAR(255) NULL AFTER sumber_data;
ALTER TABLE downtime ADD COLUMN IF NOT EXISTS bukti_original_name VARCHAR(255) NULL AFTER bukti_filename;
ALTER TABLE downtime ADD COLUMN IF NOT EXISTS bukti_mime VARCHAR(150) NULL AFTER bukti_original_name;
ALTER TABLE downtime ADD COLUMN IF NOT EXISTS bukti_size BIGINT NOT NULL DEFAULT 0 AFTER bukti_mime;
