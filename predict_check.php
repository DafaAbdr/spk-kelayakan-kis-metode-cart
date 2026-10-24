<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Sesi berakhir, silakan login ulang.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method tidak diizinkan.']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'message' => 'Sesi tidak valid, silakan muat ulang halaman.']);
    exit;
}

$statusOptions  = ['Tetap', 'Tidak Tetap', 'Tidak Bekerja'];
$kondisiOptions = ['Milik Sendiri', 'Sewa', 'Menumpang'];
$asetOptions    = ['Memiliki Aset', 'Tidak Memiliki Aset'];

$penghasilan = (float)str_replace(['.', ','], '', $_POST['penghasilan'] ?? '0');
$tanggungan  = (int)($_POST['jumlah_tanggungan'] ?? 0);
$status      = $_POST['status_pekerjaan'] ?? '';
$kondisi     = $_POST['kondisi_tempat_tinggal'] ?? '';
$aset        = $_POST['kepemilikan_aset'] ?? '';

if (!in_array($status, $statusOptions, true) || !in_array($kondisi, $kondisiOptions, true) || !in_array($aset, $asetOptions, true)) {
    echo json_encode(['ok' => false, 'message' => 'Lengkapi dahulu Data Kriteria (penghasilan, status pekerjaan, tanggungan, kondisi tempat tinggal, kepemilikan aset).']);
    exit;
}

$inputRow = [
    'penghasilan' => $penghasilan,
    'jumlah_tanggungan' => $tanggungan,
    'status_pekerjaan' => $status,
    'kondisi_tempat_tinggal' => $kondisi,
    'kepemilikan_aset' => $aset,
];

$pred = predict_raw($pdo, $inputRow);

if (!$pred) {
    echo json_encode(['ok' => false, 'message' => 'Belum ada model CART yang selesai dilatih. Jalankan menu "Proses Klasifikasi" terlebih dahulu.']);
    exit;
}

echo json_encode(['ok' => true, 'hasil' => $pred['hasil'], 'confidence' => round($pred['confidence'], 1)]);
