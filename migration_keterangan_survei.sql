-- =====================================================================
-- MIGRASI: Pisahkan "status kelayakan resmi/terkini" (keterangan) dari
-- "label asli hasil survei / ground truth" (keterangan_survei).
--
-- LATAR BELAKANG:
-- Sebelumnya, kolom `keterangan` dipakai untuk DUA hal sekaligus: (1)
-- label asli hasil survei yang dipakai melatih & menguji model CART,
-- dan (2) status kelayakan yang tampil ke admin. Setelah revisi ini,
-- Proses Klasifikasi akan memperbarui `keterangan` untuk SEMUA warga
-- (training, testing, dan data baru) supaya selalu mencerminkan hasil
-- klasifikasi CART terbaru -- sesuai kebutuhan sistem penentuan
-- penerimaan KIS. Supaya proses re-training di masa depan tidak
-- "belajar dari tebakannya sendiri" (feedback loop), label asli hasil
-- survei disimpan terpisah & permanen di `keterangan_survei`.
--
-- CARA PAKAI: jalankan file ini SEKALI saja di database yang sudah ada
-- (phpMyAdmin -> tab SQL -> tempel isi file ini -> Go), atau:
--   mysql -u root db_kis_cart < migration_keterangan_survei.sql
-- Jika kamu baru mau install dari nol, cukup import database.sql yang
-- sudah memuat kolom ini -- tidak perlu jalankan file migrasi ini.
-- =====================================================================

ALTER TABLE `masyarakat`
  ADD COLUMN `keterangan_survei` enum('Layak','Tidak Layak') DEFAULT NULL
  COMMENT 'Label ASLI hasil survei lapangan (ground truth). TIDAK PERNAH ditimpa otomatis oleh proses klasifikasi -- dipakai sebagai acuan melatih & mengevaluasi akurasi model CART. NULL = warga ini belum pernah disurvei langsung (murni hasil sistem).'
  AFTER `keterangan`;

-- Backfill: salin nilai `keterangan` yang sekarang ke `keterangan_survei`
-- untuk semua warga yang labelnya berasal dari survei asli (bukan hasil
-- prediksi sistem), supaya ground truth yang sudah ada tidak hilang.
UPDATE `masyarakat`
SET `keterangan_survei` = `keterangan`
WHERE `keterangan` IS NOT NULL
  AND (`keterangan_source` = 'survei' OR `keterangan_source` IS NULL);

-- Perbarui komentar kolom `keterangan` supaya mencerminkan makna barunya.
ALTER TABLE `masyarakat`
  MODIFY `keterangan` enum('Layak','Tidak Layak') DEFAULT NULL
  COMMENT 'Status kelayakan RESMI/TERKINI warga (Layak/Tidak Layak KIS). Diperbarui otomatis mengikuti hasil Proses Klasifikasi CART terakhir untuk SEMUA warga (training, testing, maupun data baru). NULL = belum pernah diproses.';
