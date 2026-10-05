USE mrmiKit;

CREATE TABLE IF NOT EXISTS helpdesk_insiden (
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
 sumber VARCHAR(50) NOT NULL DEFAULT 'whatsapp',
 catatan TEXT NULL,
 created_by INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_helpdesk_tanggal(tanggal_lapor),
 INDEX idx_helpdesk_status_sla(status_sla)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS helpdesk_bukti (
 id INT AUTO_INCREMENT PRIMARY KEY,
 insiden_id INT NOT NULL,
 nama_file VARCHAR(255) NOT NULL,
 original_name VARCHAR(255) NOT NULL,
 mime_type VARCHAR(150) NULL,
 size_bytes BIGINT NOT NULL DEFAULT 0,
 catatan TEXT NULL,
 uploaded_by INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(insiden_id) REFERENCES helpdesk_insiden(id) ON DELETE CASCADE,
 INDEX idx_helpdesk_bukti(insiden_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- IM-IT-05 memakai data helpdesk_insiden; capaian dihitung otomatis per bulan:
-- jumlah insiden selesai sesuai SLA / seluruh insiden selesai x 100.
-- Bulan tanpa insiden tidak dibuat 100% agar tidak mengarang data.
