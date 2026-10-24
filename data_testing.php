<?php

/**
 * Alur: tampil data testing (tidak ikut melatih pohon)
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$totalTraining = (int)$pdo->query("SELECT COUNT(*) FROM masyarakat WHERE dataset_split = 'training'")->fetchColumn();
$totalTesting  = (int)$pdo->query("SELECT COUNT(*) FROM masyarakat WHERE dataset_split = 'testing'")->fetchColumn();

$countsStmt = $pdo->query("SELECT keterangan_survei AS keterangan, COUNT(*) c FROM masyarakat WHERE dataset_split = 'testing' GROUP BY keterangan_survei");
$layakTest = 0; $tidakTest = 0;
foreach ($countsStmt as $r) {
    if ($r['keterangan'] === 'Layak') $layakTest = (int)$r['c'];
    else $tidakTest = (int)$r['c'];
}

$dataTesting = $pdo->query("SELECT * FROM masyarakat WHERE dataset_split = 'testing' ORDER BY nama ASC")->fetchAll();

$pageTitle   = 'Data Testing';
$activeMenu  = 'testing';
$pageIcon    = 'bi bi-clipboard-check';
$pageEyebrow = 'Machine Learning';
$pageHeading = 'Data Testing';
$pageDesc    = 'Subset data masyarakat berlabel yang TIDAK dipakai melatih model -- khusus dipakai untuk mengevaluasi akurasi CART secara objektif.';

require_once __DIR__ . '/includes/layout_top.php';
?>

<h5 class="mb-4"><b>DATA TESTING</b></h5>

<?php if ($totalTesting === 0): ?>
<div class="alert alert-warning">Data belum dibagi menjadi Training/Testing. Buka menu <a href="data_training.php">Data Training</a> dan klik "Bagi Ulang Data (Acak)".</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="panel text-center py-3">
      <p class="text-muted small mb-1">Total Data Testing</p>
      <h3 class="mb-0"><?= $totalTesting ?></h3>
    </div>
  </div>
  <div class="col-md-3">
    <div class="panel text-center py-3">
      <p class="text-muted small mb-1">Proporsi Terhadap Data Training</p>
      <h3 class="mb-0"><?= $totalTraining > 0 ? round(($totalTesting / $totalTraining) * 100) : 0 ?>%</h3>
    </div>
  </div>
  <div class="col-md-3">
    <div class="panel text-center py-3">
      <p class="text-muted small mb-1">Layak / Tidak Layak</p>
      <h3 class="mb-0"><?= $layakTest ?> / <?= $tidakTest ?></h3>
    </div>
  </div>
</div>

<div class="panel">
  <div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
      <thead>
        <tr class="table-light">
          <th>No</th>
          <th>Nama</th>
          <th>Penghasilan</th>
          <th>Status Pekerjaan</th>
          <th>Tanggungan</th>
          <th>Kondisi Tempat Tinggal</th>
          <th>Kepemilikan Aset</th>
          <th>Keterangan (Survei)</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$dataTesting): ?>
        <tr><td colspan="8" class="text-center text-muted">Belum ada data testing. Bagi data pada menu "Data Training" terlebih dahulu.</td></tr>
        <?php endif; ?>
        <?php foreach ($dataTesting as $i => $row): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= e($row['nama']) ?></td>
          <td><?= rupiah($row['penghasilan']) ?></td>
          <td><?= e($row['status_pekerjaan']) ?></td>
          <td class="text-center"><?= (int)$row['jumlah_tanggungan'] ?></td>
          <td><?= e($row['kondisi_tempat_tinggal']) ?></td>
          <td><?= e($row['kepemilikan_aset']) ?></td>
          <td><?= badge_hasil($row['keterangan_survei']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="text-muted small mb-0">Kolom Keterangan menampilkan label ASLI hasil survei (ground truth) yang dipakai mengevaluasi akurasi model. Data ini TIDAK ikut membangun pohon CART. Lihat hasil evaluasinya (akurasi, confusion matrix, prediksi) pada menu <a href="hasil.php">Hasil Klasifikasi</a>.</p>
</div>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
