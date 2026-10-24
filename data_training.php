<?php

/**
 * Alur: tampil data training & tombol bagi ulang acak
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bagi_ulang') {
    verify_csrf();

    $ratio = max(0.5, min(0.9, (float)($_POST['train_ratio'] ?? 0.8)));

    $rows = $pdo->query("SELECT id, keterangan_survei AS keterangan FROM masyarakat WHERE keterangan_survei IS NOT NULL")->fetchAll();

    if (count($rows) < 4) {
        set_flash('danger', 'Minimal dibutuhkan 4 data hasil survei (bukan rekomendasi sistem) untuk membagi Data Training/Testing.');
    } else {
        $split = stratified_split($rows, $ratio);

        $pdo->beginTransaction();
        try {

            $pdo->exec("UPDATE masyarakat SET dataset_split = NULL WHERE keterangan_survei IS NOT NULL");

            $updStmt = $pdo->prepare("UPDATE masyarakat SET dataset_split = :split WHERE id = :id");
            foreach ($split['training'] as $id) {
                $updStmt->execute(['split' => 'training', 'id' => $id]);
            }
            foreach ($split['testing'] as $id) {
                $updStmt->execute(['split' => 'testing', 'id' => $id]);
            }
            $pdo->commit();

            $nTrain = count($split['training']);
            $nTest = count($split['testing']);
            set_flash('success', "Data berhasil dibagi ulang secara acak: {$nTrain} data training, {$nTest} data testing. Silakan jalankan Proses Klasifikasi kembali agar model dilatih memakai pembagian terbaru.");
        } catch (Throwable $ex) {
            $pdo->rollBack();
            set_flash('danger', 'Gagal membagi data: ' . $ex->getMessage());
        }
    }
    header('Location: data_training.php');
    exit;
}

$totalBerlabel = (int)$pdo->query("SELECT COUNT(*) FROM masyarakat WHERE keterangan IS NOT NULL")->fetchColumn();
$totalSurvei = (int)$pdo->query("SELECT COUNT(*) FROM masyarakat WHERE keterangan_survei IS NOT NULL")->fetchColumn();
$totalSistem = (int)$pdo->query("SELECT COUNT(*) FROM masyarakat WHERE keterangan IS NOT NULL AND keterangan_survei IS NULL")->fetchColumn();
$totalTraining = (int)$pdo->query("SELECT COUNT(*) FROM masyarakat WHERE dataset_split = 'training'")->fetchColumn();
$totalTesting  = (int)$pdo->query("SELECT COUNT(*) FROM masyarakat WHERE dataset_split = 'testing'")->fetchColumn();

// Layak/Tidak Layak dihitung dari label survei asli (ground truth)
$countsStmt = $pdo->query("SELECT keterangan_survei AS keterangan, COUNT(*) c FROM masyarakat WHERE dataset_split = 'training' GROUP BY keterangan_survei");
$layakTrain = 0; $tidakTrain = 0;
foreach ($countsStmt as $r) {
    if ($r['keterangan'] === 'Layak') $layakTrain = (int)$r['c'];
    else $tidakTrain = (int)$r['c'];
}

$dataTraining = $pdo->query("SELECT * FROM masyarakat WHERE dataset_split = 'training' ORDER BY nama ASC")->fetchAll();

$pageTitle   = 'Data Training';
$activeMenu  = 'training';
$pageIcon    = 'bi bi-diagram-3';
$pageEyebrow = 'Machine Learning';
$pageHeading = 'Data Training';
$pageDesc    = 'Subset data masyarakat berlabel yang digunakan untuk membangun (melatih) pohon keputusan CART.';

require_once __DIR__ . '/includes/layout_top.php';
?>

<h5 class="mb-4"><b>DATA TRAINING</b></h5>

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="panel text-center py-3">
      <p class="text-muted small mb-1">Total Data Training</p>
      <h3 class="mb-0"><?= $totalTraining ?></h3>
    </div>
  </div>
  <div class="col-md-3">
    <div class="panel text-center py-3">
      <p class="text-muted small mb-1">Proporsi Terhadap Data Survei</p>
      <h3 class="mb-0"><?= $totalSurvei > 0 ? round(($totalTraining / $totalSurvei) * 100) : 0 ?>%</h3>
    </div>
  </div>
  <div class="col-md-3">
    <div class="panel text-center py-3">
      <p class="text-muted small mb-1">Layak / Tidak Layak</p>
      <h3 class="mb-0"><?= $layakTrain ?> / <?= $tidakTrain ?></h3>
    </div>
  </div>
  <div class="col-md-3 d-flex">
    <div class="panel py-3 w-100 d-flex flex-column justify-content-center align-items-center text-center">
      <form method="post" class="w-100">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="bagi_ulang">
        <input type="hidden" name="train_ratio" value="0.8">
        <p class="small text-muted mb-2">Rasio default: <strong>80% Training : 20% Testing</strong></p>
        <button type="submit" class="btn btn-sm btn-primary w-100"
          onclick="return confirm('Membagi ulang akan mengacak ulang SELURUH data survei (lama dan baru dilebur jadi satu) dengan rasio 80:20. Lanjutkan?');">
          <i class="bi bi-shuffle"></i> Bagi Ulang Data (Acak)
        </button>
      </form>
    </div>
  </div>
</div>

<?php if ($totalBerlabel === 0): ?>
<div class="alert alert-secondary">Belum ada data masyarakat berlabel. Tambahkan data pada menu <a href="kriteria.php">Data Masyarakat</a> terlebih dahulu.</div>
<?php elseif ($totalTraining === 0): ?>
<div class="alert alert-warning">Data berlabel belum dibagi menjadi Training/Testing. Klik <strong>"Bagi Ulang Data (Acak)"</strong> di atas untuk memulai.</div>
<?php endif; ?>

<?php if ($totalSistem > 0): ?>
<div class="alert alert-secondary py-2 small">
  <i class="bi bi-info-circle"></i>
  Ada <strong><?= $totalSistem ?></strong> data berlabel yang berasal dari rekomendasi sistem (bukan survei asli) -- <strong>tidak diikutsertakan</strong> dalam pembagian Training/Testing, hanya <strong><?= $totalSurvei ?></strong> data hasil survei asli yang dipakai. Lihat menu <a href="kriteria.php">Data Masyarakat</a> (badge kuning "Sistem") untuk memverifikasi data tersebut lewat survei lapangan.
</div>
<?php endif; ?>

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
        <?php if (!$dataTraining): ?>
        <tr><td colspan="8" class="text-center text-muted">Belum ada data training. Klik "Bagi Ulang Data (Acak)" di atas.</td></tr>
        <?php endif; ?>
        <?php foreach ($dataTraining as $i => $row): ?>
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
  <p class="text-muted small mb-0">Kolom Keterangan menampilkan label ASLI hasil survei (ground truth) yang dipakai melatih pohon -- bukan status resmi terkini, yang bisa berubah tiap Proses Klasifikasi dijalankan (lihat menu <a href="kriteria.php">Data Masyarakat</a>). Data ini digunakan sebagai bahan membangun pohon keputusan CART pada menu <a href="proses.php">Proses Klasifikasi</a>. Data testing tidak ditampilkan di sini &mdash; lihat menu <a href="data_testing.php">Data Testing</a>.</p>
</div>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
