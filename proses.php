<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/CartTree.php';
require_login();

$totalData = (int)$pdo->query('SELECT COUNT(*) FROM masyarakat')->fetchColumn();
$totalBerlabel = (int)$pdo->query('SELECT COUNT(*) FROM masyarakat WHERE keterangan IS NOT NULL')->fetchColumn();
$totalTraining = (int)$pdo->query("SELECT COUNT(*) FROM masyarakat WHERE dataset_split = 'training'")->fetchColumn();
$totalTesting  = (int)$pdo->query("SELECT COUNT(*) FROM masyarakat WHERE dataset_split = 'testing'")->fetchColumn();

$settingsRows = $pdo->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$defaultMaxDepth = (int)($settingsRows['cart_max_depth'] ?? CART_MAX_DEPTH_DEFAULT);
$defaultMinSplit = (int)($settingsRows['cart_min_samples_split'] ?? CART_MIN_SAMPLES_SPLIT_DEFAULT);
$adaRunSebelumnya = (int)$pdo->query('SELECT COUNT(*) FROM cart_runs')->fetchColumn() > 0;

$runResult = null;
$errorMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'jalankan') {
    verify_csrf();

    $maxDepth = max(2, min(8, (int)($_POST['max_depth'] ?? $defaultMaxDepth)));
    $minSplit = max(2, min(20, (int)($_POST['min_samples_split'] ?? $defaultMinSplit)));

    $startTime = microtime(true);

    $allRows = $pdo->query('SELECT * FROM masyarakat ORDER BY id ASC')->fetchAll();

    $trainingRows = array_values(array_filter($allRows, fn($r) => $r['dataset_split'] === 'training'));
    $testingRows  = array_values(array_filter($allRows, fn($r) => $r['dataset_split'] === 'testing'));

    if (count($allRows) < 4) {
        $errorMsg = 'Minimal dibutuhkan 4 data masyarakat untuk menjalankan proses klasifikasi.';
    } elseif (count($trainingRows) < 2 || count($testingRows) < 1) {
        $errorMsg = 'Data belum dibagi menjadi Data Training dan Data Testing. Buka menu "Data Training" dan klik "Bagi Ulang Data (Acak)" terlebih dahulu.';
    } else {
        {
            // Alur: latih pohon pakai label survei asli, bukan status terkini
            $trainData = array_map(function ($r) {
                $r['keterangan'] = $r['keterangan_survei'];
                return $r;
            }, $trainingRows);

            $features = cart_feature_config();

            $cart = new CartTree($features, 'keterangan', $maxDepth, $minSplit, CART_MIN_SAMPLES_LEAF_DEFAULT);
            $tree = $cart->fit($trainData);

            $pdo->beginTransaction();
            try {
                // Hanya boleh ada 1 run aktif -- hapus run lama sebelum buat baru
                $pdo->exec('DELETE FROM cart_runs');

                $stmt = $pdo->prepare('INSERT INTO cart_runs
                    (total_data, total_training, total_testing, max_depth, min_samples_split)
                    VALUES (:total, :train, :test, :depth, :minsplit)');
                $stmt->execute([
                    'total' => count($allRows), 'train' => count($trainingRows), 'test' => count($testingRows),
                    'depth' => $maxDepth, 'minsplit' => $minSplit,
                ]);
                $runId = (int)$pdo->lastInsertId();

                $nodeStmt = $pdo->prepare('INSERT INTO cart_nodes
                    (run_id, parent_id, node_side, depth, is_leaf, feature, feature_label, split_type, split_value,
                     gini, gini_split, samples, layak_count, tidak_layak_count, predicted_class, confidence)
                    VALUES (:run_id, :parent_id, :side, :depth, :is_leaf, :feature, :feature_label, :split_type,
                     :split_value, :gini, :gini_split, :samples, :layak, :tidak, :pred, :conf)');

                $saveNode = function ($node, $parentId, $side) use (&$saveNode, $nodeStmt, $runId, $pdo) {
                    $layak = $node['counts']['Layak'] ?? 0;
                    $tidak = $node['counts']['Tidak Layak'] ?? 0;

                    $splitValue = null;
                    if (!$node['is_leaf']) {
                        $splitValue = $node['split_type'] === 'numeric'
                            ? (string)$node['split_value']
                            : implode('||', $node['split_value']);
                    }

                    $nodeStmt->execute([
                        'run_id' => $runId,
                        'parent_id' => $parentId,
                        'side' => $side,
                        'depth' => $node['depth'],
                        'is_leaf' => $node['is_leaf'] ? 1 : 0,
                        'feature' => $node['feature'],
                        'feature_label' => $node['feature_label'],
                        'split_type' => $node['split_type'],
                        'split_value' => $splitValue,
                        'gini' => $node['gini'],
                        'gini_split' => $node['gini_split'],
                        'samples' => $node['samples'],
                        'layak' => $layak,
                        'tidak' => $tidak,
                        'pred' => $node['is_leaf'] ? $node['predicted_class'] : null,
                        'conf' => $node['is_leaf'] ? $node['confidence'] : null,
                    ]);
                    $newId = (int)$pdo->lastInsertId();

                    if (!$node['is_leaf']) {
                        $saveNode($node['left'], $newId, 'left');
                        $saveNode($node['right'], $newId, 'right');
                    }
                    return $newId;
                };

                $saveNode($tree, null, 'root');

                $hasilStmt = $pdo->prepare('INSERT INTO hasil_klasifikasi
                    (run_id, masyarakat_id, hasil_prediksi, confidence, keterangan_aktual, is_correct)
                    VALUES (:run_id, :masyarakat_id, :hasil, :confidence, :aktual, :correct)');

                // Alur: perbarui status kelayakan semua warga tiap proses
                $updateStatusStmt = $pdo->prepare("UPDATE masyarakat SET keterangan = :keterangan, keterangan_source = :sumber WHERE id = :id");

                $layakCount = 0; $tidakCount = 0; $correctCount = 0; $correctTesting = 0;

                foreach ($allRows as $row) {
                    $pred = $cart->predict($row);
                    $hasil = $pred['predicted_class'];
                    $conf = $pred['confidence'];
                    // Ground truth pakai label survei, bukan status yang mau ditimpa
                    $aktual = $row['keterangan_survei'];
                    $isCorrect = $aktual !== null ? ($aktual === $hasil ? 1 : 0) : null;

                    if ($hasil === 'Layak') $layakCount++; else $tidakCount++;
                    if ($isCorrect === 1) {
                        $correctCount++;
                        if ($row['dataset_split'] === 'testing') $correctTesting++;
                    }

                    $hasilStmt->execute([
                        'run_id' => $runId,
                        'masyarakat_id' => $row['id'],
                        'hasil' => $hasil,
                        'confidence' => $conf,
                        'aktual' => $aktual,
                        'correct' => $isCorrect,
                    ]);

                    $updateStatusStmt->execute([
                        'keterangan' => $hasil,
                        'sumber' => $aktual !== null ? 'survei' : 'sistem',
                        'id' => $row['id'],
                    ]);
                }

                $accuracy = count($testingRows) > 0 ? round(($correctTesting / count($testingRows)) * 100, 2) : 0;
                $durationMs = (int)round((microtime(true) - $startTime) * 1000);

                $updStmt = $pdo->prepare('UPDATE cart_runs SET accuracy=:acc, layak_count=:layak,
                    tidak_layak_count=:tidak, correct_count=:correct, correct_testing=:correct_test, duration_ms=:dur WHERE id=:id');
                $updStmt->execute([
                    'acc' => $accuracy, 'layak' => $layakCount, 'tidak' => $tidakCount,
                    'correct' => $correctCount, 'correct_test' => $correctTesting, 'dur' => $durationMs, 'id' => $runId,
                ]);

                $pdo->commit();

                set_flash('success', "Proses klasifikasi CART berhasil dijalankan. Akurasi model (dari data testing): {$accuracy}%. Status kelayakan SELURUH warga (termasuk data training & testing) sudah otomatis diperbarui mengikuti hasil klasifikasi terbaru.");
                header('Location: hasil.php?run=' . $runId);
                exit;
            } catch (Throwable $ex) {
                $pdo->rollBack();
                $errorMsg = 'Gagal memproses klasifikasi: ' . $ex->getMessage();
            }
        }
    }
}

$lastRun = $pdo->query('SELECT * FROM cart_runs ORDER BY id DESC LIMIT 1')->fetch();

$pageTitle   = 'Proses Klasifikasi';
$activeMenu  = 'proses';
$pageIcon    = 'bi bi-table';
$pageEyebrow = 'Management';
$pageHeading = 'Proses Klasifikasi';
$pageDesc    = 'Jalankan proses klasifikasi untuk menentukan kelayakan penerima Kartu Indonesia Sehat berdasarkan data masyarakat menggunakan metode CART.';

require_once __DIR__ . '/includes/layout_top.php';
?>

<?php if ($errorMsg): ?>
<div class="alert alert-danger"><?= e($errorMsg) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-lg-4">
    <div class="panel">
      <h5 class="mb-3">Ringkasan Data</h5>
      <hr>
      <p><strong>Total Data Masyarakat :</strong><br><?= $totalData ?> Data</p>
      <p><strong>Data Training :</strong><br><?= $totalTraining ?> Data <span class="text-muted small">(melatih pohon CART)</span></p>
      <p><strong>Data Testing :</strong><br><?= $totalTesting ?> Data <span class="text-muted small">(mengevaluasi akurasi)</span></p>
      <p><strong>Metode :</strong><br>CART (Classification and Regression Tree)</p>
      <p class="mb-0">
        <strong>Status :</strong><br>
        <?php if ($totalTraining >= 2 && $totalTesting >= 1): ?>
          <span class="badge bg-success">Siap Diproses</span>
        <?php else: ?>
          <span class="badge bg-secondary">Belum Dibagi Training/Testing</span><br>
          <a href="data_training.php" class="small">Bagi data sekarang &rarr;</a>
        <?php endif; ?>
      </p>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="panel text-center">
      <i class="bi bi-cpu display-1 text-primary"></i>
      <h3 class="mt-3">Proses Klasifikasi CART</h3>

      <form method="post" class="text-center mb-3" <?= $adaRunSebelumnya ? 'onsubmit="return confirm(\'Hasil proses sebelumnya akan dihapus permanen dan diganti hasil yang baru. Lanjutkan?\');"' : '' ?>>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="jalankan">
        <input type="hidden" name="max_depth" value="<?= $defaultMaxDepth ?>">
        <input type="hidden" name="min_samples_split" value="<?= $defaultMinSplit ?>">
        <p class="small text-muted mb-3">Kedalaman Maks. Pohon: <strong><?= $defaultMaxDepth ?></strong> &middot; Min. Sampel Split: <strong><?= $defaultMinSplit ?></strong></p>
        <?php if ($adaRunSebelumnya): ?>
        <p class="small text-warning"><i class="bi bi-exclamation-triangle"></i> Sudah ada 1 hasil proses tersimpan. Menjalankan lagi akan menggantinya (hasil lama terhapus).</p>
        <?php endif; ?>
        <button class="btn btn-success btn-lg px-5" type="submit" <?= ($totalTraining < 2 || $totalTesting < 1) ? 'disabled' : '' ?>>
          <i class="bi bi-play-fill"></i> <?= $adaRunSebelumnya ? 'Proses Ulang Klasifikasi' : 'Proses Klasifikasi' ?>
        </button>
      </form>
      <?php if ($totalTraining < 2 || $totalTesting < 1): ?>
        <p class="text-muted small mb-0">Data belum dibagi menjadi Training/Testing. Buka menu <a href="data_training.php">Data Training</a> terlebih dahulu.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="panel mt-4">
  <h5 class="mb-3">Hasil Proses Terakhir</h5>
  <table class="table table-bordered align-middle mb-0">
    <thead>
      <tr>
        <th>Waktu Proses</th>
        <th>Training / Testing</th>
        <th>Layak</th>
        <th>Tidak Layak</th>
        <th>Akurasi (dari Data Testing)</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($lastRun): ?>
      <tr>
        <td><?= date('d-m-Y H:i', strtotime($lastRun['run_at'])) ?></td>
        <td><?= (int)$lastRun['total_training'] ?> / <?= (int)$lastRun['total_testing'] ?></td>
        <td><?= (int)$lastRun['layak_count'] ?></td>
        <td><?= (int)$lastRun['tidak_layak_count'] ?></td>
        <td><?= number_format((float)$lastRun['accuracy'], 1) ?>% <span class="text-muted small">(<?= (int)$lastRun['correct_testing'] ?>/<?= (int)$lastRun['total_testing'] ?> benar)</span></td>
        <td><span class="badge bg-success">Selesai</span> <a href="hasil.php?run=<?= (int)$lastRun['id'] ?>" class="ms-1">Lihat Hasil</a> &middot; <a href="data_testing.php" class="ms-1">Lihat Testing</a></td>
      </tr>
      <?php else: ?>
      <tr>
        <td>-</td><td>-</td><td>-</td><td>-</td><td>-</td>
        <td><span class="badge bg-secondary">Belum Diproses</span></td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
