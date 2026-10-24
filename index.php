<?php

$pageTitle   = 'Dashboard';
$activeMenu  = 'dashboard';
$pageIcon    = 'bi bi-speedometer2';
$pageEyebrow = 'Overview';
$pageHeading = 'Dashboard';
$pageDesc    = 'Kelola data masyarakat, proses klasifikasi kelayakan penerima Kartu Indonesia Sehat (KIS), dan lihat hasil klasifikasi berbasis metode CART.';

require_once __DIR__ . '/includes/layout_top.php';

$totalTraining = (int)$pdo->query("SELECT COUNT(*) FROM masyarakat WHERE dataset_split = 'training'")->fetchColumn();
$totalBerlabel   = (int)$pdo->query("SELECT COUNT(*) FROM masyarakat WHERE keterangan IS NOT NULL")->fetchColumn();
$totalBelumProses = (int)$pdo->query("SELECT COUNT(*) FROM masyarakat WHERE keterangan IS NULL")->fetchColumn();
$totalTesting = (int)$pdo->query("SELECT COUNT(*) FROM masyarakat WHERE dataset_split = 'testing'")->fetchColumn();
$lastRun = $pdo->query('SELECT * FROM cart_runs ORDER BY id DESC LIMIT 1')->fetch();

// Total data, layak, tidak layak dibaca langsung dari tabel masyarakat
$totalMasyarakat = (int)$pdo->query('SELECT COUNT(*) FROM masyarakat')->fetchColumn();
$totalLayak = (int)$pdo->query("SELECT COUNT(*) FROM masyarakat WHERE keterangan = 'Layak'")->fetchColumn();
$totalTidakLayak = (int)$pdo->query("SELECT COUNT(*) FROM masyarakat WHERE keterangan = 'Tidak Layak'")->fetchColumn();

$latestResults = [];
if ($lastRun) {
    $stmt = $pdo->prepare('SELECT hk.*, m.no_kk, m.nama FROM hasil_klasifikasi hk
                            JOIN masyarakat m ON m.id = hk.masyarakat_id
                            WHERE hk.run_id = :run ORDER BY hk.id DESC LIMIT 5');
    $stmt->execute(['run' => $lastRun['id']]);
    $latestResults = $stmt->fetchAll();
}
?>

<div class="panel mt-4 text-center">
    <p class="mb-0">
        <b>SELAMAT DATANG DI PENENTUAN KELAYAKAN PENERIMAAN KARTU INDONESIA SEHAT
        PADA MASYARAKAT DESA CICADAS KAMPUNG PABUARAN</b>
    </p>
</div>
<br>
<div class="row g-3 text-center">
    <div class="col-md-4">
        <div class="panel position-relative" style="border-left:5px solid #5b7fdb;">
            <i class="bi bi-list-check fs-1 mb-2"></i>
            <h5><b>Data Training</b></h5>
            <p class="text-muted small mb-2"><?= $totalTraining ?> data training</p>
            <a href="data_training.php" class="btn btn-light btn-sm mt-2"><i class="bi bi-arrow-right"></i> More Info</a>
        </div>
    </div>

    <div class="col-md-4">
        <div class="panel position-relative" style="border-left:5px solid #34c38f;">
            <i class="bi bi-people fs-1 mb-2"></i>
            <h5><b>Data Testing</b></h5>
            <p class="text-muted small mb-2"><?= $totalTesting ?> data testing</p>
            <a href="data_testing.php" class="btn btn-light btn-sm mt-2"><i class="bi bi-arrow-right"></i> More Info</a>
        </div>
    </div>

    <div class="col-md-4">
        <div class="panel position-relative" style="border-left:5px solid #f1b44c;">
            <i class="bi bi-bar-chart-line fs-1 mb-2"></i>
            <h5><b>Hasil Klasifikasi</b></h5>
            <p class="text-muted small mb-2">
                <?= $lastRun ? 'Akurasi ' . number_format((float)$lastRun['accuracy'], 1) . '%' : 'Belum diproses' ?>
            </p>
            <a href="hasil.php" class="btn btn-light btn-sm mt-2"><i class="bi bi-arrow-right"></i> More Info</a>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-md-3">
        <div class="panel text-center">
            <h6>Total Data Masyarakat</h6>
            <h2><?= $totalMasyarakat ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel text-center">
            <h6>Layak</h6>
            <h2 class="text-success"><?= $totalLayak ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel text-center">
            <h6>Tidak Layak</h6>
            <h2 class="text-danger"><?= $totalTidakLayak ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel text-center">
            <h6>Akurasi Model</h6>
            <h2><?= $lastRun ? number_format((float)$lastRun['accuracy'], 1) : '0.0' ?>%</h2>
        </div>
    </div>
</div>

<div class="panel mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Hasil Klasifikasi Terbaru</h5>
        <a href="hasil.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead>
                <tr><th>No. KK</th><th>Nama</th><th>Hasil</th><th>Confidence</th></tr>
            </thead>
            <tbody>
                <?php foreach ($latestResults as $r): ?>
                <tr>
                    <td><?= e($r['no_kk']) ?></td>
                    <td><?= e($r['nama']) ?></td>
                    <td><?= badge_hasil($r['hasil_prediksi']) ?></td>
                    <td><?= number_format((float)$r['confidence'], 1) ?>%</td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$latestResults): ?>
                <tr><td colspan="4" class="text-center text-muted">Belum ada hasil.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>