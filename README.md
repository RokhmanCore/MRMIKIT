# MRMIKIT

Sistem Manajemen Dokumen & Bukti Akreditasi MRMIK IT berbasis LARSI untuk RSU Permata Medika Kebumen.

## Stack
- PHP 8+
- MariaDB/MySQL 5.7+/8+
- Bootstrap 5 (CDN)

## Instalasi
1. Salin repository ke web root LAMPP/XAMPP.
2. Buat database kosong bernama `mrmiKit`.
3. Import `database/mrmiKit.sql`.
4. Sesuaikan koneksi pada `config/config.php`.
5. Pastikan folder `uploads/` dapat ditulis oleh web server.
6. Buka `http://localhost/MRMIKIT/`.

Akun awal setelah import SQL:
- Username: admin
- Password: admin123

Segera ganti password setelah login.


## Indikator Mutu IT

Modul **Indikator Mutu IT** tersedia di `/indikator-mutu/` dan mencakup:
- register indikator dan target internal RS
- numerator/denominator dan perhitungan capaian
- input capaian per bulan
- analisis dan tindak lanjut
- PIC
- pemetaan indikator ke EP MRMIK
- status tercapai/tidak tercapai

### Instalasi database untuk instalasi yang sudah berjalan

Jalankan file:

`database/migrasi_indikator_mutu.sql`

melalui phpMyAdmin pada database `mrmiKit`.

Setelah itu buka:

`http://localhost/MRMIKIT/indikator-mutu/`

### Catatan akreditasi

Indikator yang disediakan sebagai awal adalah **template indikator internal IT**, bukan klaim bahwa target/angka tersebut merupakan indikator nasional. RS perlu menetapkan definisi operasional, target, metode pengukuran, PIC, periode, analisis, dan tindak lanjut sesuai kebijakan serta regulasi yang berlaku.
