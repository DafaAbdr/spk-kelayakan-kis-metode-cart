-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 07, 2026 at 05:26 PM
-- Server version: 8.0.30
-- PHP Version: 8.3.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_kis_cart`
--

-- --------------------------------------------------------

--
-- Table structure for table `app_settings`
--

CREATE TABLE `app_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `app_settings`
--

INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES
('cart_max_depth', '4'),
('cart_min_samples_split', '4');

-- --------------------------------------------------------

--
-- Table structure for table `cart_nodes`
--

CREATE TABLE `cart_nodes` (
  `id` int UNSIGNED NOT NULL,
  `run_id` int UNSIGNED NOT NULL,
  `parent_id` int UNSIGNED DEFAULT NULL,
  `node_side` enum('root','left','right') NOT NULL DEFAULT 'root',
  `depth` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `is_leaf` tinyint(1) NOT NULL DEFAULT '0',
  `feature` varchar(50) DEFAULT NULL,
  `feature_label` varchar(100) DEFAULT NULL,
  `split_type` enum('numeric','categorical') DEFAULT NULL,
  `split_value` varchar(255) DEFAULT NULL COMMENT 'Angka ambang batas, atau daftar kategori dipisah koma (cabang kiri)',
  `gini` decimal(6,4) NOT NULL DEFAULT '0.0000' COMMENT 'Gini Impurity node t: Gini(t) = 1 - Sum(Pj^2)',
  `gini_split` decimal(8,6) DEFAULT NULL COMMENT 'Gini_split(s,t) = (nL/n).Gini(tL) + (nR/n).Gini(tR) dari split terpilih di node ini (NULL untuk daun)',
  `samples` int UNSIGNED NOT NULL DEFAULT '0',
  `layak_count` int UNSIGNED NOT NULL DEFAULT '0',
  `tidak_layak_count` int UNSIGNED NOT NULL DEFAULT '0',
  `predicted_class` varchar(20) DEFAULT NULL,
  `confidence` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart_runs`
--

CREATE TABLE `cart_runs` (
  `id` int UNSIGNED NOT NULL,
  `run_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total_data` int UNSIGNED NOT NULL DEFAULT '0',
  `total_training` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Jumlah data berlabel yang dipakai melatih pohon',
  `total_testing` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Jumlah data testing yang dipakai mengevaluasi akurasi',
  `correct_count` int UNSIGNED NOT NULL DEFAULT '0',
  `correct_testing` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Jumlah prediksi benar pada data testing',
  `accuracy` decimal(5,2) NOT NULL DEFAULT '0.00',
  `layak_count` int UNSIGNED NOT NULL DEFAULT '0',
  `tidak_layak_count` int UNSIGNED NOT NULL DEFAULT '0',
  `max_depth` tinyint UNSIGNED NOT NULL DEFAULT '4',
  `min_samples_split` tinyint UNSIGNED NOT NULL DEFAULT '4',
  `duration_ms` int UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hasil_klasifikasi`
--

CREATE TABLE `hasil_klasifikasi` (
  `id` int UNSIGNED NOT NULL,
  `run_id` int UNSIGNED NOT NULL,
  `masyarakat_id` int UNSIGNED NOT NULL,
  `hasil_prediksi` enum('Layak','Tidak Layak') NOT NULL,
  `confidence` decimal(5,2) NOT NULL DEFAULT '0.00',
  `keterangan_aktual` enum('Layak','Tidak Layak') DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL COMMENT 'NULL jika tidak ada label aktual (data baru)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `masyarakat`
--

CREATE TABLE `masyarakat` (
  `id` int UNSIGNED NOT NULL,
  `no_kk` varchar(16) NOT NULL COMMENT 'Nomor Kartu Keluarga (16 digit) -- identitas per keluarga/rumah tangga',
  `nama` varchar(100) NOT NULL,
  `alamat` varchar(255) DEFAULT NULL,
  `penghasilan` decimal(12,0) NOT NULL COMMENT 'Penghasilan per bulan (Rupiah)',
  `status_pekerjaan` enum('Tetap','Tidak Tetap','Tidak Bekerja') NOT NULL,
  `jumlah_tanggungan` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `kondisi_tempat_tinggal` enum('Milik Sendiri','Sewa','Menumpang') NOT NULL,
  `kepemilikan_aset` enum('Memiliki Aset','Tidak Memiliki Aset') NOT NULL,
  `keterangan` enum('Layak','Tidak Layak') DEFAULT NULL COMMENT 'Status kelayakan RESMI/TERKINI warga (Layak/Tidak Layak KIS). Diperbarui otomatis mengikuti hasil Proses Klasifikasi CART terakhir untuk SEMUA warga (training, testing, maupun data baru). NULL = belum pernah diproses.',
  `keterangan_survei` enum('Layak','Tidak Layak') DEFAULT NULL COMMENT 'Label ASLI hasil survei lapangan (ground truth). TIDAK PERNAH ditimpa otomatis oleh proses klasifikasi -- dipakai sebagai acuan melatih & mengevaluasi akurasi model CART. NULL = warga ini belum pernah disurvei langsung (murni hasil sistem).',
  `dataset_split` enum('training','testing') DEFAULT NULL COMMENT 'Hasil pembagian acak (stratified) untuk data berlabel. NULL = belum dibagi atau data belum berlabel.',
  `keterangan_source` enum('survei','sistem') DEFAULT NULL COMMENT 'survei = hasil pendataan lapangan asli, sistem = hasil prediksi/rekomendasi CART',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Administrator','Petugas') NOT NULL DEFAULT 'Administrator',
  `avatar` varchar(255) DEFAULT NULL COMMENT 'Path relatif ke file foto profil, contoh: uploads/avatars/xxx.jpg',
  `bio` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `avatar`, `bio`, `created_at`, `updated_at`) VALUES
(1, 'Admin Desa Cicadas', 'admin@example.com', '$2y$10$X15CHoQViYJlLQa1qB5LxevTFu98oCzwzVzjWqpioAgnu30GhXY0S', 'Administrator', NULL, 'Mengelola data masyarakat dan proses klasifikasi kelayakan penerima Kartu Indonesia Sehat.', '2026-08-07 17:22:48', '2026-08-07 17:22:48'),
(2, 'Dafa Abdurrohman', 'dafa@gmail.com', '$2y$10$.JOzdoRsogQHipeINqpXk.T4cNkgFyUUacSJrMUCkxLE4BEd5kpYm', 'Administrator', NULL, NULL, '2026-08-07 17:25:50', '2026-08-07 17:25:50');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `cart_nodes`
--
ALTER TABLE `cart_nodes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cart_nodes_run` (`run_id`),
  ADD KEY `idx_cart_nodes_parent` (`parent_id`);

--
-- Indexes for table `cart_runs`
--
ALTER TABLE `cart_runs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hasil_klasifikasi`
--
ALTER TABLE `hasil_klasifikasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hasil_run` (`run_id`),
  ADD KEY `idx_hasil_masyarakat` (`masyarakat_id`);

--
-- Indexes for table `masyarakat`
--
ALTER TABLE `masyarakat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_masyarakat_no_kk` (`no_kk`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart_nodes`
--
ALTER TABLE `cart_nodes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart_runs`
--
ALTER TABLE `cart_runs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hasil_klasifikasi`
--
ALTER TABLE `hasil_klasifikasi`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `masyarakat`
--
ALTER TABLE `masyarakat`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart_nodes`
--
ALTER TABLE `cart_nodes`
  ADD CONSTRAINT `fk_cart_nodes_run` FOREIGN KEY (`run_id`) REFERENCES `cart_runs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hasil_klasifikasi`
--
ALTER TABLE `hasil_klasifikasi`
  ADD CONSTRAINT `fk_hasil_masyarakat` FOREIGN KEY (`masyarakat_id`) REFERENCES `masyarakat` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hasil_run` FOREIGN KEY (`run_id`) REFERENCES `cart_runs` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
