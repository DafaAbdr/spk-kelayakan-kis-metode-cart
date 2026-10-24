<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/TreeRenderer.php';
require_login();

$runId = (int)($_GET['run'] ?? 0);

if ($runId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM cart_runs WHERE id = :id');
    $stmt->execute(['id' => $runId]);
    $run = $stmt->fetch();
} else {
    $run = $pdo->query('SELECT * FROM cart_runs ORDER BY id DESC LIMIT 1')->fetch();
}

$allRuns = $pdo->query('SELECT id, run_at FROM cart_runs ORDER BY id DESC')->fetchAll();

$treeHtml = '<p class="text-muted mb-0">Belum ada proses klasifikasi yang dijalankan.</p>';

// Hasil pada data training (data yang membangun pohon)
$details = [];
$layakCount = 0;
$tidakCount = 0;

// Evaluasi pada data testing (dihitung setelah model dilatih)
$dataTesting = [];
$tp = $tn = $fp = $fn = 0;

if ($run) {
    $nodeStmt = $pdo->prepare('SELECT * FROM cart_nodes WHERE run_id = :run');
    $nodeStmt->execute(['run' => $run['id']]);
    $nodes = $nodeStmt->fetchAll();
    $treeHtml = TreeRenderer::render($nodes);

    $detailStmt = $pdo->prepare("SELECT hk.*, m.no_kk, m.nama FROM hasil_klasifikasi hk
                                  JOIN masyarakat m ON m.id = hk.masyarakat_id
                                  WHERE hk.run_id = :run AND m.dataset_split = 'training'
                                  ORDER BY hk.id ASC");
    $detailStmt->execute(['run' => $run['id']]);
    $details = $detailStmt->fetchAll();

    foreach ($details as $d) {
        if ($d['hasil_prediksi'] === 'Layak') $layakCount++; else $tidakCount++;
    }

    $testStmt = $pdo->prepare("SELECT hk.*, m.no_kk, m.nama FROM hasil_klasifikasi hk
                                JOIN masyarakat m ON m.id = hk.masyarakat_id
                                WHERE hk.run_id = :run AND m.dataset_split = 'testing'
                                ORDER BY hk.id ASC");
    $testStmt->execute(['run' => $run['id']]);
    $dataTesting = $testStmt->fetchAll();

    foreach ($dataTesting as $row) {
        $aktual = $row['keterangan_aktual'];
        $pred = $row['hasil_prediksi'];
        if ($aktual === 'Layak' && $pred === 'Layak') $tp++;
        elseif ($aktual === 'Tidak Layak' && $pred === 'Tidak Layak') $tn++;
        elseif ($aktual === 'Tidak Layak' && $pred === 'Layak') $fp++;
        elseif ($aktual === 'Layak' && $pred === 'Tidak Layak') $fn++;
    }
}

$totalHasil = $layakCount + $tidakCount;
$layakPct = $totalHasil > 0 ? round(($layakCount / $totalHasil) * 100, 1) : 0;
$tidakPct = $totalHasil > 0 ? round(100 - $layakPct, 1) : 0;
$pieDeg = $totalHasil > 0 ? ($layakCount / $totalHasil) * 360 : 0;

$totalTestingHasil = count($dataTesting);
$layakTestCount = $tp + $fp;
$tidakTestCount = $tn + $fn;
$benarTesting = $tp + $tn;
$akurasiTesting = $totalTestingHasil > 0 ? round(($benarTesting / $totalTestingHasil) * 100, 2) : 0;

$pageTitle   = 'Hasil Klasifikasi';
$activeMenu  = 'hasil';
$pageIcon    = 'bi bi-bar-chart';
$pageEyebrow = 'Management';
$pageHeading = 'Hasil Klasifikasi';
$pageDesc    = 'Menampilkan pohon keputusan CART, hasil klasifikasi pada data training, serta evaluasi akurasi model pada data testing.';

require_once __DIR__ . '/includes/layout_top.php';
?>

<?php if (count($allRuns) > 1): ?>
<div class="d-flex justify-content-end mb-3">
  <form method="get" class="d-flex align-items-center gap-2">
    <label class="form-label mb-0 small">Pilih Riwayat Proses:</label>
    <select class="form-select form-select-sm" name="run" style="width:auto" onchange="this.form.submit()">
      <?php foreach ($allRuns as $r): ?>
      <option value="<?= (int)$r['id'] ?>" <?= $run && $run['id'] == $r['id'] ? 'selected' : '' ?>>
        Run #<?= (int)$r['id'] ?> - <?= date('d-m-Y H:i', strtotime($r['run_at'])) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </form>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="panel text-center">
      <h6>Total Data Training</h6>
      <h2><?= $totalHasil ?></h2>
    </div>
  </div>
  <div class="col-md-4">
    <div class="panel text-center">
      <h6>Layak</h6>
      <h2 class="text-success"><?= $layakCount ?></h2>
    </div>
  </div>
  <div class="col-md-4">
    <div class="panel text-center">
      <h6>Tidak Layak</h6>
      <h2 class="text-danger"><?= $tidakCount ?></h2>
    </div>
  </div>
</div>

<div class="panel mb-4">
  <h5 class="mb-3">Visualisasi Pohon Keputusan CART</h5>
  <?= $treeHtml ?>
</div>

<div class="panel mb-4">
  <h5 class="mb-4">Persentase Hasil Klasifikasi (Data Training)</h5>
  <div class="row align-items-center">
    <div class="col-md-6 text-center">
      <div class="css-pie" style="--p:<?= $pieDeg ?>deg;">
        <div class="css-pie-label">
          <strong><?= $layakPct ?>%</strong>
          <small>Layak</small>
        </div>
      </div>
      <div class="d-flex justify-content-center gap-3 mt-3 small">
        <span><span class="legend-swatch" style="background:#34c38f;display:inline-block;width:12px;height:12px;border-radius:3px;"></span> Layak</span>
        <span><span class="legend-swatch" style="background:#e35d5d;display:inline-block;width:12px;height:12px;border-radius:3px;"></span> Tidak Layak</span>
      </div>
    </div>
    <div class="col-md-6">
      <table class="table table-bordered mb-0">
        <thead><tr><th>Kategori</th><th>Jumlah</th><th>Persentase</th></tr></thead>
        <tbody>
          <tr><td>Layak</td><td><?= $layakCount ?></td><td><?= $layakPct ?>%</td></tr>
          <tr><td>Tidak Layak</td><td><?= $tidakCount ?></td><td><?= $tidakPct ?>%</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="panel mb-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0">Detail Hasil Klasifikasi Data Training</h5>
    <div class="input-group input-group-sm" style="max-width: 280px;">
      <span class="input-group-text"><i class="bi bi-search"></i></span>
      <input type="text" class="form-control" id="searchTraining" placeholder="Cari No. KK / nama..." oninput="filterTableRows(this, 'tableTraining')">
    </div>
  </div>
  <div class="table-responsive table-scroll">
    <table class="table table-bordered table-hover align-middle mb-0" id="tableTraining">
      <thead>
        <tr>
          <th>No</th>
          <th>No. KK</th>
          <th>Nama</th>
          <th>Keterangan Aktual</th>
          <th>Hasil Prediksi CART</th>
          <th>Confidence</th>
          <th>Kecocokan</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$details): ?>
        <tr><td colspan="7" class="text-center text-muted">Belum ada data hasil.</td></tr>
        <?php endif; ?>
        <?php foreach ($details as $i => $d): ?>
        <tr data-row>
          <td><?= $i + 1 ?></td>
          <td><?= e($d['no_kk']) ?></td>
          <td><?= e($d['nama']) ?></td>
          <td>
            <?php if ($d['keterangan_aktual']): ?>
              <?= badge_hasil($d['keterangan_aktual']) ?>
            <?php else: ?>
              <span class="badge bg-secondary">Data Baru</span>
            <?php endif; ?>
          </td>
          <td><?= badge_hasil($d['hasil_prediksi']) ?></td>
          <td><?= number_format((float)$d['confidence'], 1) ?>%</td>
          <td>
            <?php if ($d['is_correct'] === null): ?>
              <span class="text-muted">-</span>
            <?php elseif ((int)$d['is_correct'] === 1): ?>
              <i class="bi bi-check-circle-fill text-success"></i> Sesuai
            <?php else: ?>
              <i class="bi bi-x-circle-fill text-danger"></i> Berbeda
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if ($details): ?>
        <tr data-empty-row style="display:none;"><td colspan="7" class="text-center text-muted">Tidak ada data yang cocok dengan pencarian.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <p class="text-muted small mb-0 mt-3">Data training dipakai membangun pohon, sehingga kecocokan di atas bukan ukuran akurasi objektif. Akurasi model yang sebenarnya diukur dari data testing di bawah ini.</p>
</div>

<hr class="my-4">

<h5 class="mb-3"><i class="bi bi-clipboard-check"></i> Evaluasi Pada Data Testing</h5>

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="panel text-center py-3">
      <p class="text-muted small mb-1">Total Data Testing</p>
      <h3 class="mb-0"><?= $totalTestingHasil ?></h3>
    </div>
  </div>
  <div class="col-md-3">
    <div class="panel text-center py-3">
      <p class="text-muted small mb-1">Layak / Tidak Layak</p>
      <h3 class="mb-0"><?= $layakTestCount ?> / <?= $tidakTestCount ?></h3>
    </div>
  </div>
  <div class="col-md-3">
    <div class="panel text-center py-3">
      <p class="text-muted small mb-1">Prediksi Benar</p>
      <h3 class="mb-0"><?= $benarTesting ?></h3>
    </div>
  </div>
  <div class="col-md-3">
    <div class="panel text-center py-3">
      <p class="text-muted small mb-1">Akurasi Model</p>
      <h3 class="mb-0 text-success"><?= $akurasiTesting ?>%</h3>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="panel">
      <h6 class="mb-3">Confusion Matrix</h6>
      <table class="table table-bordered text-center mb-0">
        <thead>
          <tr class="table-light">
            <th></th>
            <th>Prediksi: Layak</th>
            <th>Prediksi: Tidak Layak</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <th class="table-light">Aktual: Layak</th>
            <td class="fw-bold bg-success bg-opacity-10"><?= $tp ?> <span class="text-muted small">(TP)</span></td>
            <td class="bg-danger bg-opacity-10"><?= $fn ?> <span class="text-muted small">(FN)</span></td>
          </tr>
          <tr>
            <th class="table-light">Aktual: Tidak Layak</th>
            <td class="bg-danger bg-opacity-10"><?= $fp ?> <span class="text-muted small">(FP)</span></td>
            <td class="fw-bold bg-success bg-opacity-10"><?= $tn ?> <span class="text-muted small">(TN)</span></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="panel">
      <h6 class="mb-3">Perhitungan Akurasi</h6>
      <p class="mb-1 font-monospace">Accuracy = (TP + TN) / (TP + TN + FP + FN) &times; 100%</p>
      <p class="mb-0 font-monospace">Accuracy = (<?= $tp ?> + <?= $tn ?>) / (<?= $tp ?> + <?= $tn ?> + <?= $fp ?> + <?= $fn ?>) &times; 100% = <strong><?= $akurasiTesting ?>%</strong></p>
    </div>
  </div>
</div>

<div class="panel">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0">Tabel Hasil Data Testing</h5>
    <div class="input-group input-group-sm" style="max-width: 280px;">
      <span class="input-group-text"><i class="bi bi-search"></i></span>
      <input type="text" class="form-control" id="searchTesting" placeholder="Cari No. KK / nama..." oninput="filterTableRows(this, 'tableTesting')">
    </div>
  </div>
  <div class="table-responsive table-scroll">
    <table class="table table-bordered table-hover align-middle mb-0" id="tableTesting">
      <thead>
        <tr class="table-light">
          <th>No</th>
          <th>No. KK</th>
          <th>Nama</th>
          <th>Keterangan Aktual</th>
          <th>Hasil Prediksi CART</th>
          <th>Confidence</th>
          <th>Kecocokan</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$dataTesting): ?>
        <tr><td colspan="7" class="text-center text-muted">Belum ada data testing untuk run ini.</td></tr>
        <?php endif; ?>
        <?php foreach ($dataTesting as $i => $row): ?>
        <tr data-row>
          <td><?= $i + 1 ?></td>
          <td><?= e($row['no_kk']) ?></td>
          <td><?= e($row['nama']) ?></td>
          <td><?= $row['keterangan_aktual'] ? badge_hasil($row['keterangan_aktual']) : '<span class="text-muted">-</span>' ?></td>
          <td><?= badge_hasil($row['hasil_prediksi']) ?></td>
          <td><?= number_format((float)$row['confidence'], 1) ?>%</td>
          <td>
            <?php if ($row['is_correct'] === null): ?>
              <span class="text-muted">-</span>
            <?php elseif ((int)$row['is_correct'] === 1): ?>
              <span class="badge bg-success"><i class="bi bi-check-lg"></i> Benar</span>
            <?php else: ?>
              <span class="badge bg-danger"><i class="bi bi-x-lg"></i> Salah</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if ($dataTesting): ?>
        <tr data-empty-row style="display:none;"><td colspan="7" class="text-center text-muted">Tidak ada data yang cocok dengan pencarian.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <p class="text-muted small mb-0 mt-3">Data testing TIDAK ikut membangun pohon CART, sehingga akurasi di atas mencerminkan kemampuan generalisasi model pada data baru. Lihat isi data testing di menu <a href="data_testing.php">Data Testing</a>.</p>
</div>

<style>
.css-pie {
  --p: 180deg;
  width: 180px; height: 180px; border-radius: 50%; margin: 0 auto;
  background: conic-gradient(#34c38f var(--p), #e35d5d 0);
  display: flex; align-items: center; justify-content: center;
  position: relative;
}
.css-pie::before {
  content: ''; position: absolute; width: 118px; height: 118px; border-radius: 50%;
  background: var(--panel-bg, #fff);
}
[data-theme="dark"] .css-pie::before { background: #1f2734; }
.css-pie-label { position: relative; z-index: 1; text-align: center; line-height: 1.2; }
.css-pie-label strong { display: block; font-size: 20px; }

/* Tabel panjang dibuat bisa discroll vertikal */
.table-scroll {
  max-height: 420px;
  overflow-y: auto;
}
.table-scroll thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  background: var(--panel-bg, #fff);
}
[data-theme="dark"] .table-scroll thead th { background: #1f2734; }
</style>

<script>
  // Search sederhana: filter baris tabel berdasarkan teks
  function filterTableRows(inputEl, tableId) {
    var query = inputEl.value.trim().toLowerCase();
    var table = document.getElementById(tableId);
    if (!table) return;
    var rows = table.querySelectorAll('tbody tr[data-row]');
    var visibleCount = 0;
    rows.forEach(function (row) {
      var match = row.textContent.toLowerCase().indexOf(query) !== -1;
      row.style.display = match ? '' : 'none';
      if (match) visibleCount++;
    });
    var emptyRow = table.querySelector('tbody tr[data-empty-row]');
    if (emptyRow) {
      emptyRow.style.display = (visibleCount === 0 && query !== '') ? '' : 'none';
    }
  }
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
