<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$jenis = $_GET['jenis'] ?? 'rekapitulasi';
if (!in_array($jenis, ['rekapitulasi', 'masyarakat', 'training_testing', 'hasil'], true)) {
    $jenis = 'rekapitulasi';
}

$statusKeterangan = trim($_GET['status'] ?? 'Semua');
$statusPekerjaan = trim($_GET['status_pekerjaan'] ?? 'Semua');
$kondisiTinggal = trim($_GET['kondisi'] ?? 'Semua');

$rows = [];
$trainingRows = [];
$testingRows = [];
$summary = [];
$rekap = [];
$reportTitle = '';
$reportSubtitle = 'Desa Cicadas, Kampung Pabuaran &mdash; Metode CART';

$statusPekerjaanOpts = ['Tetap', 'Tidak Tetap', 'Tidak Bekerja'];
$kondisiTinggalOpts = ['Milik Sendiri', 'Sewa', 'Menumpang'];
$kepemilikanAsetOpts = ['Memiliki Aset', 'Tidak Memiliki Aset'];

// Alur: hanya 1 run aktif -- laporan hasil selalu merujuk run ini
$run = $pdo->query('SELECT * FROM cart_runs ORDER BY id DESC LIMIT 1')->fetch();
$trainingDetails = [];
$testingDetails = [];
$tp = $tn = $fp = $fn = 0;

if ($jenis === 'rekapitulasi') {
    $all = $pdo->query('SELECT keterangan, status_pekerjaan, kondisi_tempat_tinggal, kepemilikan_aset FROM masyarakat')->fetchAll();

    $totalMasyarakat = count($all);
    $totalLayak = count(array_filter($all, fn($r) => $r['keterangan'] === 'Layak'));
    $totalTidakLayak = count(array_filter($all, fn($r) => $r['keterangan'] === 'Tidak Layak'));
    $totalBelum = $totalMasyarakat - $totalLayak - $totalTidakLayak;
    $pctLayak = $totalMasyarakat > 0 ? round($totalLayak / $totalMasyarakat * 100, 1) : 0;
    $pctTidakLayak = $totalMasyarakat > 0 ? round($totalTidakLayak / $totalMasyarakat * 100, 1) : 0;

    $rekapPekerjaan = [];
    foreach ($statusPekerjaanOpts as $opt) {
        $rekapPekerjaan[$opt] = count(array_filter($all, fn($r) => $r['status_pekerjaan'] === $opt));
    }
    $rekapKondisi = [];
    foreach ($kondisiTinggalOpts as $opt) {
        $rekapKondisi[$opt] = count(array_filter($all, fn($r) => $r['kondisi_tempat_tinggal'] === $opt));
    }
    $rekapAset = [];
    foreach ($kepemilikanAsetOpts as $opt) {
        $rekapAset[$opt] = count(array_filter($all, fn($r) => $r['kepemilikan_aset'] === $opt));
    }

    $rekap = [
        'total_masyarakat' => $totalMasyarakat,
        'total_layak' => $totalLayak,
        'total_tidak_layak' => $totalTidakLayak,
        'total_belum' => $totalBelum,
        'pct_layak' => $pctLayak,
        'pct_tidak_layak' => $pctTidakLayak,
        'pekerjaan' => $rekapPekerjaan,
        'kondisi' => $rekapKondisi,
        'aset' => $rekapAset,
    ];

    $reportTitle = 'LAPORAN REKAPITULASI KELAYAKAN PENERIMA KIS';
}

if ($jenis === 'masyarakat') {
    $where = [];
    $params = [];

    if ($statusKeterangan === 'Belum Ada') {
        $where[] = 'keterangan IS NULL';
    } elseif ($statusKeterangan !== '' && $statusKeterangan !== 'Semua') {
        $where[] = 'keterangan = :status'; $params['status'] = $statusKeterangan;
    }
    if ($statusPekerjaan !== '' && $statusPekerjaan !== 'Semua') {
        $where[] = 'status_pekerjaan = :status_pekerjaan'; $params['status_pekerjaan'] = $statusPekerjaan;
    }
    if ($kondisiTinggal !== '' && $kondisiTinggal !== 'Semua') {
        $where[] = 'kondisi_tempat_tinggal = :kondisi'; $params['kondisi'] = $kondisiTinggal;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $sql = "SELECT * FROM masyarakat $whereSql ORDER BY created_at DESC, id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $totalData = count($rows);
    $layak = count(array_filter($rows, fn($r) => $r['keterangan'] === 'Layak'));
    $tidakLayak = count(array_filter($rows, fn($r) => $r['keterangan'] === 'Tidak Layak'));
    $belum = $totalData - $layak - $tidakLayak;

    $summary = [
        ['label' => 'Total Data', 'value' => $totalData, 'icon' => 'bi-people', 'class' => 'metric-primary'],
        ['label' => 'Layak', 'value' => $layak, 'icon' => 'bi-check-circle', 'class' => 'metric-success text-success'],
        ['label' => 'Tidak Layak', 'value' => $tidakLayak, 'icon' => 'bi-x-circle', 'class' => 'metric-danger text-danger'],
        ['label' => 'Belum Ada Keterangan', 'value' => $belum, 'icon' => 'bi-question-circle', 'class' => 'metric-warning'],
    ];
    $reportTitle = 'LAPORAN DATA MASYARAKAT / KRITERIA';
}

if ($jenis === 'training_testing') {
    // Alur: data training & testing, 1 laporan tapi tabelnya dipisah
    $trainStmt = $pdo->query("SELECT * FROM masyarakat WHERE dataset_split = 'training' ORDER BY nama ASC");
    $trainingRows = $trainStmt->fetchAll();
    $testStmt = $pdo->query("SELECT * FROM masyarakat WHERE dataset_split = 'testing' ORDER BY nama ASC");
    $testingRows = $testStmt->fetchAll();

    $allTT = array_merge($trainingRows, $testingRows);
    $layak = count(array_filter($allTT, fn($r) => $r['keterangan_survei'] === 'Layak'));
    $tidakLayak = count(array_filter($allTT, fn($r) => $r['keterangan_survei'] === 'Tidak Layak'));

    $summary = [
        ['label' => 'Total Data Training', 'value' => count($trainingRows), 'icon' => 'bi-diagram-3', 'class' => 'metric-primary'],
        ['label' => 'Total Data Testing', 'value' => count($testingRows), 'icon' => 'bi-clipboard-check', 'class' => 'metric-primary'],
        ['label' => 'Layak', 'value' => $layak, 'icon' => 'bi-check-circle', 'class' => 'metric-success text-success'],
        ['label' => 'Tidak Layak', 'value' => $tidakLayak, 'icon' => 'bi-x-circle', 'class' => 'metric-danger text-danger'],
    ];
    $reportTitle = 'LAPORAN DATA TRAINING & TESTING';
}

if ($jenis === 'hasil') {
    if ($run) {
        $trainStmt = $pdo->prepare("SELECT hk.*, m.no_kk, m.nama FROM hasil_klasifikasi hk
                                     JOIN masyarakat m ON m.id = hk.masyarakat_id
                                     WHERE hk.run_id = :run AND m.dataset_split = 'training'
                                     ORDER BY hk.id ASC");
        $trainStmt->execute(['run' => $run['id']]);
        $trainingDetails = $trainStmt->fetchAll();

        $testStmt = $pdo->prepare("SELECT hk.*, m.no_kk, m.nama FROM hasil_klasifikasi hk
                                    JOIN masyarakat m ON m.id = hk.masyarakat_id
                                    WHERE hk.run_id = :run AND m.dataset_split = 'testing'
                                    ORDER BY hk.id ASC");
        $testStmt->execute(['run' => $run['id']]);
        $testingDetails = $testStmt->fetchAll();

        foreach ($testingDetails as $r) {
            $aktual = $r['keterangan_aktual']; $pred = $r['hasil_prediksi'];
            if ($aktual === 'Layak' && $pred === 'Layak') $tp++;
            elseif ($aktual === 'Tidak Layak' && $pred === 'Tidak Layak') $tn++;
            elseif ($aktual === 'Tidak Layak' && $pred === 'Layak') $fp++;
            elseif ($aktual === 'Layak' && $pred === 'Tidak Layak') $fn++;
        }
    }

    $akurasiTesting = ($tp + $tn + $fp + $fn) > 0 ? round((($tp + $tn) / ($tp + $tn + $fp + $fn)) * 100, 2) : 0;

    $summary = [
        ['label' => 'Total Data Training', 'value' => count($trainingDetails), 'icon' => 'bi-diagram-3', 'class' => 'metric-primary'],
        ['label' => 'Total Data Testing', 'value' => count($testingDetails), 'icon' => 'bi-clipboard-check', 'class' => 'metric-primary'],
        ['label' => 'Prediksi Benar (Testing)', 'value' => $tp + $tn, 'icon' => 'bi-check-circle', 'class' => 'metric-success text-success'],
        ['label' => 'Akurasi Model', 'value' => $akurasiTesting . '%', 'icon' => 'bi-graph-up', 'class' => 'metric-warning'],
    ];
    $reportTitle = 'LAPORAN HASIL PROSES KLASIFIKASI CART';
}

// Susun data export sekali, dipakai bareng CSV & preview
$exportHeader = [];
$exportRows = [];

if ($jenis === 'rekapitulasi') {
    $exportHeader = ['Kategori', 'Jumlah'];
    $exportRows[] = ['Total Masyarakat', $rekap['total_masyarakat']];
    $exportRows[] = ['Total Layak KIS', $rekap['total_layak']];
    $exportRows[] = ['Total Tidak Layak KIS', $rekap['total_tidak_layak']];
    $exportRows[] = ['Persentase Layak', $rekap['pct_layak'] . '%'];
    $exportRows[] = ['Persentase Tidak Layak', $rekap['pct_tidak_layak'] . '%'];
    foreach ($rekap['pekerjaan'] as $k => $v) $exportRows[] = ["Status Pekerjaan: $k", $v];
    foreach ($rekap['kondisi'] as $k => $v) $exportRows[] = ["Kondisi Tempat Tinggal: $k", $v];
    foreach ($rekap['aset'] as $k => $v) $exportRows[] = ["Kepemilikan Aset: $k", $v];
} elseif ($jenis === 'masyarakat') {
    $exportHeader = ['No', 'No. KK', 'Nama', 'Alamat', 'Penghasilan', 'Status Pekerjaan', 'Jumlah Tanggungan', 'Kondisi Tempat Tinggal', 'Kepemilikan Aset', 'Keterangan', 'Tanggal Input'];
    foreach ($rows as $i => $r) {
        $exportRows[] = [$i + 1, $r['no_kk'], $r['nama'], $r['alamat'], $r['penghasilan'], $r['status_pekerjaan'],
            $r['jumlah_tanggungan'], $r['kondisi_tempat_tinggal'], $r['kepemilikan_aset'],
            $r['keterangan'] ?? 'Belum Ada', date('d-m-Y H:i', strtotime($r['created_at']))];
    }
} elseif ($jenis === 'training_testing') {
    $exportHeader = ['No', 'No. KK', 'Nama', 'Penghasilan', 'Status Pekerjaan', 'Jumlah Tanggungan', 'Kondisi Tempat Tinggal', 'Kepemilikan Aset', 'Keterangan (Survei)'];
    $ncol = count($exportHeader);
    $exportRows[] = array_pad(['=== DATA TRAINING ==='], $ncol, '');
    foreach ($trainingRows as $i => $r) {
        $exportRows[] = [$i + 1, $r['no_kk'], $r['nama'], $r['penghasilan'], $r['status_pekerjaan'],
            $r['jumlah_tanggungan'], $r['kondisi_tempat_tinggal'], $r['kepemilikan_aset'], $r['keterangan_survei'] ?? '-'];
    }
    $exportRows[] = array_fill(0, $ncol, '');
    $exportRows[] = array_pad(['=== DATA TESTING ==='], $ncol, '');
    foreach ($testingRows as $i => $r) {
        $exportRows[] = [$i + 1, $r['no_kk'], $r['nama'], $r['penghasilan'], $r['status_pekerjaan'],
            $r['jumlah_tanggungan'], $r['kondisi_tempat_tinggal'], $r['kepemilikan_aset'], $r['keterangan_survei'] ?? '-'];
    }
} elseif ($jenis === 'hasil') {
    $exportHeader = ['No', 'No. KK', 'Nama', 'Kelompok', 'Keterangan Aktual', 'Hasil Prediksi', 'Confidence (%)', 'Kecocokan'];
    $i = 1;
    foreach ($trainingDetails as $r) {
        $cocok = $r['is_correct'] === null ? '-' : ((int)$r['is_correct'] === 1 ? 'Sesuai' : 'Berbeda');
        $exportRows[] = [$i++, $r['no_kk'], $r['nama'], 'Training', $r['keterangan_aktual'] ?? '-', $r['hasil_prediksi'],
            number_format((float)$r['confidence'], 1), $cocok];
    }
    foreach ($testingDetails as $r) {
        $cocok = $r['is_correct'] === null ? '-' : ((int)$r['is_correct'] === 1 ? 'Sesuai' : 'Berbeda');
        $exportRows[] = [$i++, $r['no_kk'], $r['nama'], 'Testing', $r['keterangan_aktual'] ?? '-', $r['hasil_prediksi'],
            number_format((float)$r['confidence'], 1), $cocok];
    }
}

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=laporan_' . $jenis . '_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, $exportHeader);
    foreach ($exportRows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}

$tanggalCetak = format_tanggal_indonesia_ttd();
ob_start();
?>
<div class="kop-surat">
  <img src="assets/images/logo-desa.png" alt="Logo Desa Cicadas">
  <div class="kop-text">
    <h4>PEMERINTAH DESA CICADAS</h4>
    <h6>KECAMATAN GUNUNGPUTRI KABUPATEN BOGOR</h6>
    <small>Kampung Pabuaran, Desa Cicadas, Kecamatan Gunungputri, Kabupaten Bogor</small>
  </div>
  <img src="assets/images/logo-desa.png" alt="" style="visibility:hidden;">
</div>
<hr class="kop-line-thin">

<div class="report-title-block">
  <h5><?= e($reportTitle) ?></h5>
  <p class="mb-0" style="font-size:12px;"><?= $reportSubtitle ?></p>
</div>

<?php if ($jenis === 'rekapitulasi'): ?>
  <table class="report-table-pretty report-recap-table mb-2">
    <tbody>
      <tr><td>Total Masyarakat</td><td><?= $rekap['total_masyarakat'] ?></td></tr>
      <tr><td>Total Layak KIS</td><td><?= $rekap['total_layak'] ?> (<?= $rekap['pct_layak'] ?>%)</td></tr>
      <tr><td>Total Tidak Layak KIS</td><td><?= $rekap['total_tidak_layak'] ?> (<?= $rekap['pct_tidak_layak'] ?>%)</td></tr>
      <?php if ($rekap['total_belum'] > 0): ?>
      <tr><td>Belum Diproses / Belum Ada Keterangan</td><td><?= $rekap['total_belum'] ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <br>

  <table class="report-table-pretty report-recap-table mb-2">
    <thead><tr><th colspan="2">Distribusi Status Pekerjaan</th></tr></thead>
    <tbody>
      <?php foreach ($rekap['pekerjaan'] as $k => $v): ?>
      <tr><td><?= e($k) ?></td><td><?= $v ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <br>

  <table class="report-table-pretty report-recap-table mb-2">
    <thead><tr><th colspan="2">Distribusi Kondisi Tempat Tinggal</th></tr></thead>
    <tbody>
      <?php foreach ($rekap['kondisi'] as $k => $v): ?>
      <tr><td><?= e($k) ?></td><td><?= $v ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <br>

  <table class="report-table-pretty report-recap-table mb-2">
    <thead><tr><th colspan="2">Distribusi Kepemilikan Aset</th></tr></thead>
    <tbody>
      <?php foreach ($rekap['aset'] as $k => $v): ?>
      <tr><td><?= e($k) ?></td><td><?= $v ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <br>

<?php elseif ($jenis === 'masyarakat'): ?>
  <table class="report-table-pretty mb-2">
    <thead><tr><th>No</th><th>No. KK</th><th>Nama</th><th>Penghasilan</th><th>Status Pekerjaan</th><th>Tanggungan</th><th>Kondisi Tinggal</th><th>Aset</th><th>Keterangan</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $i => $r): ?>
      <tr>
        <td><?= $i + 1 ?></td><td><?= e($r['no_kk']) ?></td><td><?= e($r['nama']) ?></td>
        <td><?= rupiah($r['penghasilan']) ?></td><td><?= e($r['status_pekerjaan']) ?></td>
        <td><?= (int)$r['jumlah_tanggungan'] ?></td><td><?= e($r['kondisi_tempat_tinggal']) ?></td>
        <td><?= e($r['kepemilikan_aset']) ?></td><td><?= e($r['keterangan'] ?? 'Belum Ada') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <br>

<?php elseif ($jenis === 'training_testing'): ?>
  <table class="report-table-pretty mb-2">
    <thead><tr><th colspan="8">Tabel Data Training</th></tr></thead>
  </table>
  <table class="report-table-pretty mb-2">
    <thead><tr><th>No</th><th>Nama</th><th>Penghasilan</th><th>Status Pekerjaan</th><th>Tanggungan</th><th>Kondisi Tinggal</th><th>Aset</th><th>Keterangan (Survei)</th></tr></thead>
    <tbody>
      <?php foreach ($trainingRows as $i => $r): ?>
      <tr>
        <td><?= $i + 1 ?></td><td><?= e($r['nama']) ?></td>
        <td><?= rupiah($r['penghasilan']) ?></td><td><?= e($r['status_pekerjaan']) ?></td>
        <td><?= (int)$r['jumlah_tanggungan'] ?></td><td><?= e($r['kondisi_tempat_tinggal']) ?></td>
        <td><?= e($r['kepemilikan_aset']) ?></td><td><?= e($r['keterangan_survei'] ?? '-') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <br>

  <table class="report-table-pretty mb-2">
    <thead><tr><th colspan="8">Tabel Data Testing</th></tr></thead>
  </table>
  <table class="report-table-pretty mb-2">
    <thead><tr><th>No</th><th>Nama</th><th>Penghasilan</th><th>Status Pekerjaan</th><th>Tanggungan</th><th>Kondisi Tinggal</th><th>Aset</th><th>Keterangan (Survei)</th></tr></thead>
    <tbody>
      <?php foreach ($testingRows as $i => $r): ?>
      <tr>
        <td><?= $i + 1 ?></td><td><?= e($r['nama']) ?></td>
        <td><?= rupiah($r['penghasilan']) ?></td><td><?= e($r['status_pekerjaan']) ?></td>
        <td><?= (int)$r['jumlah_tanggungan'] ?></td><td><?= e($r['kondisi_tempat_tinggal']) ?></td>
        <td><?= e($r['kepemilikan_aset']) ?></td><td><?= e($r['keterangan_survei'] ?? '-') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <br>

<?php elseif ($jenis === 'hasil'): ?>
  <?php if ($run): ?>
  <table class="report-table-pretty report-recap-table mb-2">
    <tbody>
      <tr><td>Waktu Proses</td><td><?= date('d-m-Y H:i', strtotime($run['run_at'])) ?></td></tr>
      <tr><td>Total Data Training / Testing</td><td><?= count($trainingDetails) ?> / <?= count($testingDetails) ?></td></tr>
      <tr><td>Kedalaman Pohon / Min. Sampel Split</td><td><?= (int)$run['max_depth'] ?> / <?= (int)$run['min_samples_split'] ?></td></tr>
      <tr><td>Akurasi Model (dari Data Testing)</td><td><?= $akurasiTesting ?>% (<?= $tp + $tn ?>/<?= $tp + $tn + $fp + $fn ?> benar)</td></tr>
    </tbody>
  </table>

  <br>

  <table class="report-table-pretty report-recap-table mb-2">
    <thead><tr><th colspan="3">Confusion Matrix (Data Testing)</th></tr></thead>
    <tbody>
      <tr><td></td><td>Prediksi: Layak</td><td>Prediksi: Tidak Layak</td></tr>
      <tr><td>Aktual: Layak</td><td>TP = <?= $tp ?></td><td>FN = <?= $fn ?></td></tr>
      <tr><td>Aktual: Tidak Layak</td><td>FP = <?= $fp ?></td><td>TN = <?= $tn ?></td></tr>
    </tbody>
  </table>

  <br>

  <table class="report-table-pretty mb-2">
    <thead><tr><th colspan="7">Detail Hasil Klasifikasi &mdash; Data Training</th></tr></thead>
  </table>
  <table class="report-table-pretty mb-2">
    <thead><tr><th>No</th><th>No. KK</th><th>Nama</th><th>Hasil</th><th>Confidence</th><th>Keterangan Aktual</th><th>Kecocokan</th></tr></thead>
    <tbody>
      <?php foreach ($trainingDetails as $i => $r): ?>
      <tr>
        <td><?= $i + 1 ?></td><td><?= e($r['no_kk']) ?></td><td><?= e($r['nama']) ?></td>
        <td><?= e($r['hasil_prediksi']) ?></td><td><?= number_format((float)$r['confidence'], 1) ?>%</td>
        <td><?= e($r['keterangan_aktual'] ?? '-') ?></td>
        <td><?= $r['is_correct'] === null ? '-' : ((int)$r['is_correct'] === 1 ? 'Sesuai' : 'Berbeda') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <br>

  <table class="report-table-pretty mb-2">
    <thead><tr><th colspan="7">Detail Hasil Klasifikasi &mdash; Data Testing</th></tr></thead>
  </table>
  <table class="report-table-pretty mb-2">
    <thead><tr><th>No</th><th>No. KK</th><th>Nama</th><th>Hasil</th><th>Confidence</th><th>Keterangan Aktual</th><th>Kecocokan</th></tr></thead>
    <tbody>
      <?php foreach ($testingDetails as $i => $r): ?>
      <tr>
        <td><?= $i + 1 ?></td><td><?= e($r['no_kk']) ?></td><td><?= e($r['nama']) ?></td>
        <td><?= e($r['hasil_prediksi']) ?></td><td><?= number_format((float)$r['confidence'], 1) ?>%</td>
        <td><?= e($r['keterangan_aktual'] ?? '-') ?></td>
        <td><?= $r['is_correct'] === null ? '-' : ((int)$r['is_correct'] === 1 ? 'Sesuai' : 'Berbeda') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <br>
  <?php else: ?>
  <p>Belum ada proses klasifikasi yang dijalankan.</p>
  <?php endif; ?>
<?php endif; ?>

<br>

<div class="ttd-block">
  <div class="ttd-inner">
    <div>Cicadas, <?= e($tanggalCetak) ?></div>
    <div>Kepala Desa Cicadas</div>
    <div class="ttd-space"></div>
    <div class="ttd-name">(........................................)</div>
  </div>
</div>
<?php
$printOnlyHtml = ob_get_clean();

$pageTitle   = 'Laporan';
$activeMenu  = 'laporan';
$pageIcon    = 'bi bi-file-earmark-text';
$pageEyebrow = 'Management';
$pageHeading = 'Laporan';
$pageDesc    = 'Kelola dan cetak laporan rekapitulasi, data masyarakat, data training/testing, dan hasil proses CART.';

require_once __DIR__ . '/includes/layout_top.php';

$baseParams = [
    'jenis' => $jenis, 'status' => $statusKeterangan, 'status_pekerjaan' => $statusPekerjaan, 'kondisi' => $kondisiTinggal,
];
$queryStringForExport = http_build_query(array_merge($baseParams, ['export' => 'csv']));

$jenisTabs = [
    'rekapitulasi'     => ['label' => 'Rekapitulasi',        'icon' => 'bi-clipboard-data'],
    'masyarakat'       => ['label' => 'Data Masyarakat',     'icon' => 'bi-people'],
    'training_testing' => ['label' => 'Data Training & Testing', 'icon' => 'bi-diagram-3'],
    'hasil'            => ['label' => 'Hasil Klasifikasi',   'icon' => 'bi-bar-chart'],
];
?>

<ul class="nav nav-pills mb-4">
  <?php foreach ($jenisTabs as $key => $tab): ?>
  <li class="nav-item">
    <a class="nav-link<?= $jenis === $key ? ' active' : '' ?>" href="laporan.php?jenis=<?= e($key) ?>">
      <i class="<?= e($tab['icon']) ?>"></i> <?= e($tab['label']) ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<?php if ($jenis === 'masyarakat'): ?>
<div class="panel mb-4">
  <h5 class="mb-4">Filter Laporan</h5>
  <form class="row g-3" method="get">
    <input type="hidden" name="jenis" value="masyarakat">
    <div class="col-md-4">
      <label class="form-label">Status Keterangan</label>
      <select class="form-select" name="status">
        <?php foreach (['Semua', 'Layak', 'Tidak Layak', 'Belum Ada'] as $opt): ?>
        <option value="<?= e($opt) ?>" <?= $statusKeterangan === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Status Pekerjaan</label>
      <select class="form-select" name="status_pekerjaan">
        <option value="Semua" <?= $statusPekerjaan === 'Semua' ? 'selected' : '' ?>>Semua</option>
        <?php foreach ($statusPekerjaanOpts as $opt): ?>
        <option value="<?= e($opt) ?>" <?= $statusPekerjaan === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Kondisi Tempat Tinggal</label>
      <select class="form-select" name="kondisi">
        <option value="Semua" <?= $kondisiTinggal === 'Semua' ? 'selected' : '' ?>>Semua</option>
        <?php foreach ($kondisiTinggalOpts as $opt): ?>
        <option value="<?= e($opt) ?>" <?= $kondisiTinggal === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 d-flex gap-2">
      <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Tampilkan</button>
      <a href="laporan.php?jenis=masyarakat" class="btn btn-outline-secondary">Reset</a>
    </div>
  </form>
</div>
<?php elseif ($jenis === 'training_testing'): ?>
<div class="panel mb-4">
  <p class="mb-0 text-muted"><i class="bi bi-info-circle"></i> Menampilkan pembagian data SEBELUM diproses (hasil "Bagi Ulang Data" di menu Data Training). Data Training dipakai membangun pohon CART, Data Testing khusus mengevaluasi akurasinya. Kolom Keterangan pakai label asli hasil survei (ground truth).</p>
</div>
<?php elseif ($jenis === 'hasil'): ?>
<div class="panel mb-4">
  <p class="mb-0 text-muted"><i class="bi bi-info-circle"></i> Hanya ada 1 hasil proses klasifikasi aktif -- menjalankan Proses Klasifikasi ulang akan menggantikan hasil ini.</p>
</div>
<?php else: ?>
<div class="panel mb-4">
  <p class="mb-0 text-muted"><i class="bi bi-info-circle"></i> Laporan rekapitulasi menampilkan ringkasan menyeluruh dari seluruh data masyarakat saat ini, tanpa filter.</p>
</div>
<?php endif; ?>

<?php if ($summary): ?>
<div class="row g-3 mb-4">
  <?php foreach ($summary as $s): ?>
  <div class="col-md-3">
    <div class="metric-card <?= e(explode(' ', $s['class'])[0]) ?>">
      <div class="metric-top"><span class="metric-label"><?= e($s['label']) ?></span><span class="metric-icon"><i class="bi <?= e($s['icon']) ?>"></i></span></div>
      <div class="metric-value <?= e(explode(' ', $s['class'])[1] ?? '') ?>"><?= e((string)$s['value']) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($jenis === 'rekapitulasi'): ?>
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="metric-card metric-primary">
      <div class="metric-top"><span class="metric-label">Total Masyarakat</span><span class="metric-icon"><i class="bi bi-people"></i></span></div>
      <div class="metric-value"><?= $rekap['total_masyarakat'] ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="metric-card metric-success">
      <div class="metric-top"><span class="metric-label">Layak KIS</span><span class="metric-icon"><i class="bi bi-check-circle"></i></span></div>
      <div class="metric-value text-success"><?= $rekap['total_layak'] ?> <small class="fs-6 text-muted">(<?= $rekap['pct_layak'] ?>%)</small></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="metric-card metric-danger">
      <div class="metric-top"><span class="metric-label">Tidak Layak KIS</span><span class="metric-icon"><i class="bi bi-x-circle"></i></span></div>
      <div class="metric-value text-danger"><?= $rekap['total_tidak_layak'] ?> <small class="fs-6 text-muted">(<?= $rekap['pct_tidak_layak'] ?>%)</small></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="metric-card metric-warning">
      <div class="metric-top"><span class="metric-label">Belum Diproses</span><span class="metric-icon"><i class="bi bi-question-circle"></i></span></div>
      <div class="metric-value"><?= $rekap['total_belum'] ?></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="panel h-100">
      <h6 class="mb-3">Distribusi Status Pekerjaan</h6>
      <table class="report-table-pretty report-recap-table mb-0">
        <?php foreach ($rekap['pekerjaan'] as $k => $v): ?>
        <tr><td><?= e($k) ?></td><td><?= $v ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
  <div class="col-md-4">
    <div class="panel h-100">
      <h6 class="mb-3">Distribusi Kondisi Tempat Tinggal</h6>
      <table class="report-table-pretty report-recap-table mb-0">
        <?php foreach ($rekap['kondisi'] as $k => $v): ?>
        <tr><td><?= e($k) ?></td><td><?= $v ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
  <div class="col-md-4">
    <div class="panel h-100">
      <h6 class="mb-3">Distribusi Kepemilikan Aset</h6>
      <table class="report-table-pretty report-recap-table mb-0">
        <?php foreach ($rekap['aset'] as $k => $v): ?>
        <tr><td><?= e($k) ?></td><td><?= $v ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($jenis === 'hasil' && $run): ?>
<div class="panel mb-4">
  <h5 class="mb-3">Confusion Matrix (Data Testing)</h5>
  <table class="report-table-pretty report-recap-table mb-0">
    <tr><td></td><td>Prediksi: Layak</td><td>Prediksi: Tidak Layak</td></tr>
    <tr><td>Aktual: Layak</td><td>TP = <?= $tp ?></td><td>FN = <?= $fn ?></td></tr>
    <tr><td>Aktual: Tidak Layak</td><td>FP = <?= $fp ?></td><td>TN = <?= $tn ?></td></tr>
  </table>
</div>
<?php endif; ?>

<div class="panel mb-4">
  <div class="d-flex flex-wrap gap-2 justify-content-end">
    <button type="button" class="btn btn-secondary" onclick="window.print()">
      <i class="bi bi-file-earmark-pdf"></i> PDF / Print
    </button>
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#excelPreviewModal">
      <i class="bi bi-file-earmark-excel"></i> Excel (CSV)
    </button>
  </div>
</div>

<!-- Preview export Excel: isinya sama persis dgn CSV yang diunduh -->
<div class="modal fade" id="excelPreviewModal" tabindex="-1" aria-labelledby="excelPreviewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="excelPreviewModalLabel"><i class="bi bi-file-earmark-excel text-success"></i> Preview Export Excel</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">Berikut isi file <strong>laporan_<?= e($jenis) ?>_<?= date('Ymd') ?>.csv</strong> yang akan diunduh (bisa langsung dibuka di Excel). Periksa dulu sebelum download.</p>
        <div class="table-responsive excel-preview-scroll">
          <table class="table table-bordered table-sm excel-preview-table mb-0">
            <thead>
              <tr>
                <?php foreach ($exportHeader as $h): ?>
                <th><?= e((string)$h) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php if (!$exportRows): ?>
              <tr><td colspan="<?= max(1, count($exportHeader)) ?>" class="text-center text-muted">Tidak ada data untuk diekspor.</td></tr>
              <?php endif; ?>
              <?php foreach ($exportRows as $r): ?>
              <tr>
                <?php foreach ($r as $cell): ?>
                <td><?= e((string)$cell) ?></td>
                <?php endforeach; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="text-muted small mb-0 mt-3"><?= count($exportRows) ?> baris data.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <a class="btn btn-success" href="laporan.php?<?= e($queryStringForExport) ?>">
          <i class="bi bi-download"></i> Unduh Excel (CSV) Sekarang
        </a>
      </div>
    </div>
  </div>
</div>

<style>
.excel-preview-scroll {
  max-height: 55vh;
  overflow: auto;
}
.excel-preview-table {
  font-size: 0.85rem;
}
.excel-preview-table thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  background: #34c38f;
  color: #fff;
  white-space: nowrap;
}
</style>

<?php if ($jenis === 'masyarakat'): ?>
<div class="panel">
  <div class="table-responsive">
    <table class="report-table-pretty">
      <thead>
        <tr><th>No</th><th>No. KK</th><th>Nama</th><th>Penghasilan</th><th>Status Pekerjaan</th><th>Tanggungan</th><th>Kondisi Tinggal</th><th>Aset</th><th>Keterangan</th></tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="9" class="text-center text-muted">Tidak ada data yang sesuai filter.</td></tr><?php endif; ?>
        <?php foreach ($rows as $i => $r): ?>
        <tr>
          <td><?= $i + 1 ?></td><td><?= e($r['no_kk']) ?></td><td><?= e($r['nama']) ?></td>
          <td><?= rupiah($r['penghasilan']) ?></td><td><?= e($r['status_pekerjaan']) ?></td>
          <td class="text-center"><?= (int)$r['jumlah_tanggungan'] ?></td><td><?= e($r['kondisi_tempat_tinggal']) ?></td>
          <td><?= e($r['kepemilikan_aset']) ?></td>
          <td><?= $r['keterangan'] ? badge_hasil($r['keterangan']) : '<span class="badge bg-secondary">Belum Ada</span>' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($jenis === 'training_testing'): ?>
<div class="panel mb-4">
  <h5 class="mb-3"><i class="bi bi-diagram-3"></i> Tabel Data Training</h5>
  <div class="table-responsive">
    <table class="report-table-pretty">
      <thead>
        <tr><th>No</th><th>Nama</th><th>Penghasilan</th><th>Status Pekerjaan</th><th>Tanggungan</th><th>Kondisi Tinggal</th><th>Aset</th><th>Keterangan (Survei)</th></tr>
      </thead>
      <tbody>
        <?php if (!$trainingRows): ?><tr><td colspan="8" class="text-center text-muted">Belum ada data. Bagi data di menu Data Training.</td></tr><?php endif; ?>
        <?php foreach ($trainingRows as $i => $r): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= e($r['nama']) ?></td>
          <td><?= rupiah($r['penghasilan']) ?></td><td><?= e($r['status_pekerjaan']) ?></td>
          <td class="text-center"><?= (int)$r['jumlah_tanggungan'] ?></td><td><?= e($r['kondisi_tempat_tinggal']) ?></td>
          <td><?= e($r['kepemilikan_aset']) ?></td>
          <td><?= $r['keterangan_survei'] ? badge_hasil($r['keterangan_survei']) : '<span class="badge bg-secondary">-</span>' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel">
  <h5 class="mb-3"><i class="bi bi-clipboard-check"></i> Tabel Data Testing</h5>
  <div class="table-responsive">
    <table class="report-table-pretty">
      <thead>
        <tr><th>No</th><th>Nama</th><th>Penghasilan</th><th>Status Pekerjaan</th><th>Tanggungan</th><th>Kondisi Tinggal</th><th>Aset</th><th>Keterangan (Survei)</th></tr>
      </thead>
      <tbody>
        <?php if (!$testingRows): ?><tr><td colspan="8" class="text-center text-muted">Belum ada data. Bagi data di menu Data Training.</td></tr><?php endif; ?>
        <?php foreach ($testingRows as $i => $r): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= e($r['nama']) ?></td>
          <td><?= rupiah($r['penghasilan']) ?></td><td><?= e($r['status_pekerjaan']) ?></td>
          <td class="text-center"><?= (int)$r['jumlah_tanggungan'] ?></td><td><?= e($r['kondisi_tempat_tinggal']) ?></td>
          <td><?= e($r['kepemilikan_aset']) ?></td>
          <td><?= $r['keterangan_survei'] ? badge_hasil($r['keterangan_survei']) : '<span class="badge bg-secondary">-</span>' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($jenis === 'hasil'): ?>
<?php if (!$run): ?>
<div class="panel text-center text-muted">Belum ada proses klasifikasi yang dijalankan.</div>
<?php else: ?>
<div class="panel mb-4">
  <h5 class="mb-3">Detail Hasil Klasifikasi &mdash; Data Training</h5>
  <div class="table-responsive">
    <table class="report-table-pretty">
      <thead><tr><th>No</th><th>No. KK</th><th>Nama</th><th>Hasil</th><th>Confidence</th><th>Keterangan Aktual</th><th>Kecocokan</th></tr></thead>
      <tbody>
        <?php if (!$trainingDetails): ?><tr><td colspan="7" class="text-center text-muted">Tidak ada data.</td></tr><?php endif; ?>
        <?php foreach ($trainingDetails as $i => $r): ?>
        <tr>
          <td><?= $i + 1 ?></td><td><?= e($r['no_kk']) ?></td><td><?= e($r['nama']) ?></td>
          <td><?= badge_hasil($r['hasil_prediksi']) ?></td><td><?= number_format((float)$r['confidence'], 1) ?>%</td>
          <td><?= $r['keterangan_aktual'] ? badge_hasil($r['keterangan_aktual']) : '<span class="text-muted">-</span>' ?></td>
          <td>
            <?php if ($r['is_correct'] === null): ?><span class="text-muted">-</span>
            <?php elseif ((int)$r['is_correct'] === 1): ?><i class="bi bi-check-circle-fill text-success"></i> Sesuai
            <?php else: ?><i class="bi bi-x-circle-fill text-danger"></i> Berbeda
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel">
  <h5 class="mb-3">Detail Hasil Klasifikasi &mdash; Data Testing</h5>
  <div class="table-responsive">
    <table class="report-table-pretty">
      <thead><tr><th>No</th><th>No. KK</th><th>Nama</th><th>Hasil</th><th>Confidence</th><th>Keterangan Aktual</th><th>Kecocokan</th></tr></thead>
      <tbody>
        <?php if (!$testingDetails): ?><tr><td colspan="7" class="text-center text-muted">Tidak ada data.</td></tr><?php endif; ?>
        <?php foreach ($testingDetails as $i => $r): ?>
        <tr>
          <td><?= $i + 1 ?></td><td><?= e($r['no_kk']) ?></td><td><?= e($r['nama']) ?></td>
          <td><?= badge_hasil($r['hasil_prediksi']) ?></td><td><?= number_format((float)$r['confidence'], 1) ?>%</td>
          <td><?= $r['keterangan_aktual'] ? badge_hasil($r['keterangan_aktual']) : '<span class="text-muted">-</span>' ?></td>
          <td>
            <?php if ((int)$r['is_correct'] === 1): ?><span class="badge bg-success"><i class="bi bi-check-lg"></i> Benar</span>
            <?php else: ?><span class="badge bg-danger"><i class="bi bi-x-lg"></i> Salah</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
