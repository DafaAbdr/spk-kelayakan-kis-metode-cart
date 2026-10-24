<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: kriteria.php');
    exit;
}
verify_csrf();

$action = $_POST['action'] ?? '';

$lastRun = $pdo->query('SELECT * FROM cart_runs ORDER BY id DESC LIMIT 1')->fetch();
if (!$lastRun) {
    set_flash('danger', 'Belum ada model CART yang selesai dilatih. Jalankan menu "Proses Klasifikasi" minimal satu kali terlebih dahulu.');
    header('Location: kriteria.php');
    exit;
}

if ($action === 'predict_one') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM masyarakat WHERE id = :id AND keterangan IS NULL');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        set_flash('warning', 'Data tidak ditemukan atau sudah memiliki keterangan (sudah pernah diprediksi/disurvei).');
    } else {
        $pred = predict_one_masyarakat($pdo, $row);
        if ($pred) {
            set_flash('success', 'Data ' . e($row['nama']) . ' berhasil diprediksi: ' . e($pred['hasil']) . ' (confidence ' . number_format($pred['confidence'], 1) . '%).');
        } else {
            set_flash('danger', 'Gagal memprediksi data.');
        }
    }
} elseif ($action === 'predict_all') {
    $rows = $pdo->query('SELECT * FROM masyarakat WHERE keterangan IS NULL')->fetchAll();
    $count = 0;
    foreach ($rows as $row) {
        if (predict_one_masyarakat($pdo, $row)) $count++;
    }
    if ($count > 0) {
        set_flash('success', "$count data warga baru berhasil diprediksi memakai model dari Proses #{$lastRun['id']}.");
    } else {
        set_flash('info', 'Tidak ada data warga baru (belum berlabel) yang perlu diprediksi saat ini.');
    }
}

header('Location: kriteria.php');
exit;
