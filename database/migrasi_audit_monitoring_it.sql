-- Jalankan sekali pada database mrmiKit
CREATE TABLE IF NOT EXISTS audit_monitoring_it (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tanggal_monitoring DATE NOT NULL,
 periode VARCHAR(20) NOT NULL DEFAULT 'Bulanan',
 petugas VARCHAR(150) NOT NULL,
 kepatuhan_status VARCHAR(30) NOT NULL DEFAULT 'Memenuhi',
 kepatuhan_catatan TEXT NULL,
 jaringan_lan VARCHAR(30) NOT NULL DEFAULT 'Stabil',
 jaringan_wifi VARCHAR(30) NOT NULL DEFAULT 'Stabil',
 jaringan_internet VARCHAR(30) NOT NULL DEFAULT 'Stabil',
 jaringan_server VARCHAR(30) NOT NULL DEFAULT 'Stabil',
 jaringan_catatan TEXT NULL,
 rme_login VARCHAR(30) NOT NULL DEFAULT 'Normal',
 rme_data_pasien VARCHAR(30) NOT NULL DEFAULT 'Normal',
 rme_soap VARCHAR(30) NOT NULL DEFAULT 'Normal',
 rme_resep VARCHAR(30) NOT NULL DEFAULT 'Normal',
 rme_pencarian VARCHAR(30) NOT NULL DEFAULT 'Normal',
 rme_catatan TEXT NULL,
 keluhan_status VARCHAR(30) NOT NULL DEFAULT 'Tidak ada',
 keluhan_detail TEXT NULL,
 kesimpulan TEXT NULL,
 tindak_lanjut TEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id), KEY idx_audit_tanggal (tanggal_monitoring)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_monitoring_it_evidence (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 audit_id BIGINT UNSIGNED NOT NULL,
 nama_file VARCHAR(255) NOT NULL,
 file_path VARCHAR(500) NOT NULL,
 file_mime VARCHAR(100) NULL,
 file_size BIGINT UNSIGNED NULL,
 uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id), KEY idx_evidence_audit (audit_id),
 CONSTRAINT fk_audit_evidence FOREIGN KEY (audit_id) REFERENCES audit_monitoring_it(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;