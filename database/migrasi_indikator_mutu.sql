USE mrmiKit;

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
    FOREIGN KEY(indikator_id) REFERENCES mutu_indikator(id) ON DELETE CASCADE,
    INDEX idx_mutu_periode (periode)
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
SELECT i.id,e.id FROM elemen_penilaian e JOIN mutu_indikator i ON i.kode='IM-IT-07' WHERE e.kode='MRMIK 13.e';
INSERT IGNORE INTO mutu_indikator_ep(indikator_id,ep_id)
SELECT i.id,e.id FROM mutu_indikator i JOIN elemen_penilaian e ON e.kode='MRMIK 13.c' WHERE i.kode='IM-IT-08';
