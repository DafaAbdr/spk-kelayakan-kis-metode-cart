<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$statusOptions   = ['Tetap', 'Tidak Tetap', 'Tidak Bekerja'];
$kondisiOptions  = ['Milik Sendiri', 'Sewa', 'Menumpang'];
$asetOptions     = ['Memiliki Aset', 'Tidak Memiliki Aset'];
$keteranganOptions = ['Layak', 'Tidak Layak'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $no_kk = trim($_POST['no_kk'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $penghasilan = (float)str_replace(['.', ','], '', $_POST['penghasilan'] ?? '0');
        $status = $_POST['status_pekerjaan'] ?? 'Tidak Bekerja';
        $tanggungan = (int)($_POST['jumlah_tanggungan'] ?? 0);
        $kondisi = $_POST['kondisi_tempat_tinggal'] ?? 'Sewa';
        $aset = $_POST['kepemilikan_aset'] ?? 'Tidak Ada';
        $keterangan = trim($_POST['keterangan'] ?? '');
        $keterangan = in_array($keterangan, $keteranganOptions, true) ? $keterangan : null;

        $sourceRaw = trim($_POST['keterangan_source'] ?? '');
        $keteranganSource = $keterangan === null ? null : ($sourceRaw === 'sistem' ? 'sistem' : 'survei');
        $keteranganSurvei = $keteranganSource === 'survei' ? $keterangan : null;

        if ($no_kk === '' || $nama === '' || !preg_match('/^\d{16}$/', $no_kk)) {
            set_flash('danger', 'No. KK harus 16 digit angka dan Nama wajib diisi.');
        } elseif (!in_array($status, $statusOptions, true) || !in_array($kondisi, $kondisiOptions, true) || !in_array($aset, $asetOptions, true)) {
            set_flash('danger', 'Data kriteria tidak valid.');
        } else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE masyarakat SET no_kk=:no_kk, nama=:nama, alamat=:alamat, penghasilan=:penghasilan,
                        status_pekerjaan=:status, jumlah_tanggungan=:tanggungan, kondisi_tempat_tinggal=:kondisi,
                        kepemilikan_aset=:aset, keterangan=:keterangan, keterangan_source=:sumber, keterangan_survei=:ket_survei WHERE id=:id');
                    $stmt->execute([
                        'no_kk' => $no_kk, 'nama' => $nama, 'alamat' => $alamat, 'penghasilan' => $penghasilan,
                        'status' => $status, 'tanggungan' => $tanggungan, 'kondisi' => $kondisi,
                        'aset' => $aset, 'keterangan' => $keterangan, 'sumber' => $keteranganSource,
                        'ket_survei' => $keteranganSurvei, 'id' => $id,
                    ]);
                    set_flash('success', 'Data ' . e($nama) . ' berhasil diperbarui.');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO masyarakat
                        (no_kk, nama, alamat, penghasilan, status_pekerjaan, jumlah_tanggungan, kondisi_tempat_tinggal, kepemilikan_aset, keterangan, keterangan_source, keterangan_survei)
                        VALUES (:no_kk, :nama, :alamat, :penghasilan, :status, :tanggungan, :kondisi, :aset, :keterangan, :sumber, :ket_survei)');
                    $stmt->execute([
                        'no_kk' => $no_kk, 'nama' => $nama, 'alamat' => $alamat, 'penghasilan' => $penghasilan,
                        'status' => $status, 'tanggungan' => $tanggungan, 'kondisi' => $kondisi,
                        'aset' => $aset, 'keterangan' => $keterangan, 'sumber' => $keteranganSource,
                        'ket_survei' => $keteranganSurvei,
                    ]);

                    if ($keterangan === null) {
                        set_flash('success', 'Data ' . e($nama) . ' berhasil ditambahkan sebagai data baru (belum ada keterangan). Klik tombol Prediksi di tabel untuk melihat rekomendasi sistem.');
                    } elseif ($keteranganSource === 'sistem') {
                        set_flash('success', 'Data ' . e($nama) . ' berhasil ditambahkan dengan keterangan mengikuti rekomendasi sistem (' . e($keterangan) . '). Ini belum dianggap data survei sampai diverifikasi.');
                    } else {
                        set_flash('success', 'Data ' . e($nama) . ' berhasil ditambahkan.');
                    }
                }
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {
                    set_flash('danger', 'No. KK tersebut sudah terdaftar.');
                } else {
                    set_flash('danger', 'Gagal menyimpan data: ' . $ex->getMessage());
                }
            }
        }
        header('Location: kriteria.php'); exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM masyarakat WHERE id = :id');
        $stmt->execute(['id' => $id]);
        set_flash('success', 'Data berhasil dihapus.');
        header('Location: kriteria.php'); exit;
    }

    if ($action === 'delete_all') {
        $pdo->exec('DELETE FROM masyarakat');
        set_flash('success', 'Seluruh data masyarakat berhasil dihapus.');
        header('Location: kriteria.php'); exit;
    }

    if ($action === 'upload_csv' && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $inserted = 0; $skipped = 0; $insertedUnlabeled = 0;
        if ($handle) {
            $header = fgetcsv($handle);
            $stmt = $pdo->prepare('INSERT IGNORE INTO masyarakat
                (no_kk, nama, alamat, penghasilan, status_pekerjaan, jumlah_tanggungan, kondisi_tempat_tinggal, kepemilikan_aset, keterangan, keterangan_source, keterangan_survei)
                VALUES (:no_kk, :nama, :alamat, :penghasilan, :status, :tanggungan, :kondisi, :aset, :keterangan, :sumber, :ket_survei)');
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 8) { $skipped++; continue; }
                [$no_kk, $nama, $alamat, $penghasilan, $status, $tanggungan, $kondisi, $aset, $ket] = array_pad($row, 9, null);
                $status = in_array($status, $statusOptions, true) ? $status : 'Tidak Bekerja';
                $kondisi = in_array($kondisi, $kondisiOptions, true) ? $kondisi : 'Sewa';
                $aset = in_array($aset, $asetOptions, true) ? $aset : 'Tidak Ada';
                $ket = in_array($ket, $keteranganOptions, true) ? $ket : null;

                $sumber = $ket !== null ? 'survei' : null;
                $ketSurvei = $ket; // data CSV dianggap ground truth asli

                if (!preg_match('/^\d{16}$/', trim($no_kk ?? ''))) { $skipped++; continue; }

                try {
                    $stmt->execute([
                        'no_kk' => trim($no_kk), 'nama' => trim($nama), 'alamat' => trim($alamat ?? ''),
                        'penghasilan' => (float)$penghasilan, 'status' => $status, 'tanggungan' => (int)$tanggungan,
                        'kondisi' => $kondisi, 'aset' => $aset, 'keterangan' => $ket, 'sumber' => $sumber,
                        'ket_survei' => $ketSurvei,
                    ]);
                    $inserted += $stmt->rowCount();
                    if ($stmt->rowCount() === 0) $skipped++;
                    elseif ($ket === null) $insertedUnlabeled++;
                } catch (PDOException $ex) {
                    $skipped++;
                }
            }
            fclose($handle);
        }
        $msg = "Upload selesai: $inserted data ditambahkan, $skipped baris dilewati (duplikat/tidak valid).";
        if ($insertedUnlabeled > 0) {
            $msg .= " Terdapat $insertedUnlabeled data baru yang belum berlabel -- klik tombol \"Prediksi Semua Data Baru\" di bawah untuk langsung menentukan kelayakannya.";
        }
        set_flash('success', $msg);
        header('Location: kriteria.php'); exit;
    }
}

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM masyarakat WHERE no_kk LIKE :q1 OR nama LIKE :q2 ORDER BY id DESC");
    $stmt->execute(['q1' => '%' . $search . '%', 'q2' => '%' . $search . '%']);
} else {
    $stmt = $pdo->query('SELECT * FROM masyarakat ORDER BY id DESC');
}
$data = $stmt->fetchAll();

$totalBelumBerlabel = (int)$pdo->query('SELECT COUNT(*) FROM masyarakat WHERE keterangan IS NULL')->fetchColumn();
$adaModelSiap = (bool)$pdo->query('SELECT COUNT(*) FROM cart_runs')->fetchColumn();

$pageTitle   = 'Kriteria';
$activeMenu  = 'kriteria';
$pageIcon    = 'bi bi-list-check';
$pageEyebrow = 'Management';
$pageHeading = 'Data Masyarakat';

require_once __DIR__ . '/includes/layout_top.php';
?>

<h5 class="mb-4"><b>DATA MASYARAKAT / KRITERIA</b></h5>

<div class="panel">
  <div class="panel-body">

    <div class="row mb-4 g-2">
      <div class="col-auto">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
          <i class="bi bi-upload"></i> Upload Data
        </button>
      </div>

      <?php if ($adaModelSiap && $totalBelumBerlabel > 0): ?>
      <div class="col-auto">
        <form method="post" action="predict_new.php" class="d-inline"
          onsubmit="return confirm('Prediksi <?= $totalBelumBerlabel ?> data warga baru yang belum berlabel, memakai model CART terakhir (tanpa melatih ulang)?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="predict_all">
          <button class="btn btn-outline-success" type="submit">
            <i class="bi bi-lightning-charge"></i> Prediksi Semua Data Baru (<?= $totalBelumBerlabel ?>)
          </button>
        </form>
      </div>
      <?php endif; ?>

      <div class="col">
        <form class="input-group" method="get">
          <input class="form-control" type="text" name="q" value="<?= e($search) ?>" placeholder="Cari No. KK atau Nama">
          <button class="btn btn-secondary" type="submit"><i class="bi bi-search"></i> Cari</button>
          <?php if ($search !== ''): ?><a href="kriteria.php" class="btn btn-outline-secondary">Reset</a><?php endif; ?>
        </form>
      </div>

      <div class="col-auto">
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#formModal" data-bs-mode="add">
          <i class="bi bi-plus-lg"></i> Tambah
        </button>
      </div>

      <div class="col-auto">
        <form method="post" onsubmit="return confirm('Yakin ingin menghapus SEMUA data masyarakat? Tindakan ini tidak dapat dibatalkan.');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_all">
          <button class="btn btn-danger" type="submit"><i class="bi bi-trash"></i> Hapus Semua</button>
        </form>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered table-hover align-middle" id="tabelKriteria">
        <thead>
          <tr>
            <th rowspan="2">No</th>
            <th colspan="3" class="text-center">Data Identitas</th>
            <th colspan="5" class="text-center">Data Kriteria (variabel klasifikasi CART)</th>
            <th rowspan="2">Keterangan Aktual<br><small class="fw-normal">(status resmi terkini)</small></th>
            <th rowspan="2">Dataset</th>
            <th rowspan="2">Aksi</th>
          </tr>
          <tr>
            <th>No. KK</th>
            <th>Nama</th>
            <th>Alamat</th>
            <th>Penghasilan</th>
            <th>Status Pekerjaan</th>
            <th>Tanggungan</th>
            <th>Kondisi Tempat Tinggal</th>
            <th>Kepemilikan Aset</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$data): ?>
          <tr><td colspan="12" class="text-center text-muted">Belum ada data. Silakan tambah atau upload data.</td></tr>
          <?php endif; ?>
          <?php foreach ($data as $i => $row): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= e($row['no_kk']) ?></td>
            <td><?= e($row['nama']) ?></td>
            <td><?= e($row['alamat']) ?></td>
            <td><?= rupiah($row['penghasilan']) ?></td>
            <td><?= e($row['status_pekerjaan']) ?></td>
            <td class="text-center"><?= (int)$row['jumlah_tanggungan'] ?></td>
            <td><?= e($row['kondisi_tempat_tinggal']) ?></td>
            <td><?= e($row['kepemilikan_aset']) ?></td>
            <td>
              <?php if ($row['keterangan']): ?>
                <?= badge_hasil($row['keterangan']) ?>
                <?php if ($row['keterangan_survei'] !== null && $row['keterangan_survei'] !== $row['keterangan']): ?>
                  <br><span class="badge bg-warning text-dark mt-1" title="Hasil survei asli warga ini adalah &quot;<?= e($row['keterangan_survei']) ?>&quot;, tapi status resmi terkini sudah mengikuti hasil klasifikasi CART terbaru.">
                    <i class="bi bi-exclamation-triangle"></i> Beda dari survei (<?= e($row['keterangan_survei']) ?>)
                  </span>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge bg-secondary">Belum ada (data baru)</span>
              <?php endif; ?>
            </td>
            <td><?= badge_dataset_split($row['dataset_split'], $row['keterangan'] !== null) ?></td>
            <td class="text-nowrap">
              <?php if ($row['keterangan'] === null && $adaModelSiap): ?>
              <form method="post" action="predict_new.php" class="d-inline"
                onsubmit="return confirm('Prediksi data <?= e($row['nama']) ?> sekarang memakai model CART terakhir?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="predict_one">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button class="btn btn-sm btn-outline-success" type="submit" title="Prediksi Sekarang">
                  <i class="bi bi-lightning-charge"></i>
                </button>
              </form>
              <?php endif; ?>
              <button class="btn btn-sm btn-outline-primary"
                data-bs-toggle="modal" data-bs-target="#formModal" data-bs-mode="edit"
                data-row='<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
                <i class="bi bi-pencil"></i>
              </button>
              <form method="post" class="d-inline" onsubmit="return confirm('Hapus data <?= e($row['nama']) ?>?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="text-muted small mb-0">Total: <?= count($data) ?> data.</p>
  </div>
</div>

<!-- Modal Tambah / Edit -->
<div class="modal fade" id="formModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" id="formKriteria">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="f_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="formModalTitle">Tambah Data Masyarakat</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <h6 class="text-uppercase text-muted small fw-bold mb-2">Data Identitas</h6>
          <p class="text-muted small mb-3">Data pengenal warga. Tidak dipakai sebagai variabel perhitungan CART.</p>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label">No. KK (16 digit)</label>
              <input type="text" class="form-control" name="no_kk" id="f_no_kk" maxlength="16" pattern="\d{16}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Nama</label>
              <input type="text" class="form-control" name="nama" id="f_nama" required>
            </div>
            <div class="col-md-12">
              <label class="form-label">Alamat</label>
              <input type="text" class="form-control" name="alamat" id="f_alamat" placeholder="Kp. Pabuaran RT.. /RW.., Desa Cicadas">
            </div>
          </div>

          <hr class="mb-3">

          <h6 class="text-uppercase text-muted small fw-bold mb-2">Data Kriteria</h6>
          <p class="text-muted small mb-3">Variabel predictor yang dipakai algoritma CART untuk membentuk pohon keputusan.</p>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label">Penghasilan per Bulan (Rp)</label>
              <input type="number" min="0" step="1000" class="form-control" name="penghasilan" id="f_penghasilan" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Jumlah Tanggungan</label>
              <input type="number" min="0" max="20" class="form-control" name="jumlah_tanggungan" id="f_tanggungan" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Status Pekerjaan</label>
              <select class="form-select" name="status_pekerjaan" id="f_status" required>
                <?php foreach ($statusOptions as $opt): ?>
                <option value="<?= e($opt) ?>"><?= e($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Kondisi Tempat Tinggal</label>
              <select class="form-select" name="kondisi_tempat_tinggal" id="f_kondisi" required>
                <?php foreach ($kondisiOptions as $opt): ?>
                <option value="<?= e($opt) ?>"><?= e($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Kepemilikan Aset</label>
              <select class="form-select" name="kepemilikan_aset" id="f_aset" required>
                <?php foreach ($asetOptions as $opt): ?>
                <option value="<?= e($opt) ?>"><?= e($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <hr class="mb-3">

          <h6 class="text-uppercase text-muted small fw-bold mb-2">Label Hasil Survei</h6>
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Keterangan Aktual (hasil survei, opsional)</label>
              <select class="form-select" name="keterangan" id="f_keterangan">
                <option value="">-- Belum ada / data baru --</option>
                <?php foreach ($keteranganOptions as $opt): ?>
                <option value="<?= e($opt) ?>"><?= e($opt) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="hidden" name="keterangan_source" id="f_keterangan_source" value="">
              <div class="form-text">Diisi jika hasil survei lapangan sudah diketahui. Digunakan untuk melatih model CART dan menghitung akurasi. Kosongkan untuk data baru yang ingin diprediksi sistem.</div>
            </div>
            <div class="col-md-4 d-flex align-items-start pt-4">
              <button type="button" id="btnCekKelayakan" class="btn btn-outline-info w-100">
                <i class="bi bi-lightning-charge"></i> Cek Kelayakan
              </button>
            </div>
          </div>

          <div id="rekomendasiBox" class="alert alert-info d-none mt-3 mb-0">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <i class="bi bi-lightbulb"></i>
                Rekomendasi Sistem: <strong id="rekomendasiHasil"></strong>
                <span class="text-muted small">(confidence <span id="rekomendasiConf"></span>%)</span>
                <div class="small text-muted mt-1">Ini hasil prediksi model, BUKAN hasil survei. Gunakan hanya jika belum ada data survei lapangan untuk warga ini.</div>
              </div>
              <button type="button" id="btnGunakanHasil" class="btn btn-sm btn-info flex-shrink-0 ms-3">Gunakan hasil ini &rarr;</button>
            </div>
          </div>
          <div id="rekomendasiError" class="alert alert-warning d-none mt-3 mb-0 py-2 small"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Upload CSV -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload_csv">
        <div class="modal-header">
          <h5 class="modal-title">Upload Data (CSV)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small">
            Format kolom CSV: <code>no_kk,nama,alamat,penghasilan,status_pekerjaan,jumlah_tanggungan,kondisi_tempat_tinggal,kepemilikan_aset,keterangan</code><br>
            Baris pertama dianggap header dan akan dilewati.
          </p>
          <input type="file" class="form-control" name="csv_file" accept=".csv" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var formModal = document.getElementById('formModal');
  formModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var mode = button.getAttribute('data-bs-mode');
    var title = document.getElementById('formModalTitle');
    var form = document.getElementById('formKriteria');
    form.reset();
    document.getElementById('f_id').value = 0;

    if (mode === 'edit') {
      var row = JSON.parse(button.getAttribute('data-row'));
      title.textContent = 'Edit Data: ' + row.nama;
      document.getElementById('f_id').value = row.id;
      document.getElementById('f_no_kk').value = row.no_kk;
      document.getElementById('f_nama').value = row.nama;
      document.getElementById('f_alamat').value = row.alamat || '';
      document.getElementById('f_penghasilan').value = row.penghasilan;
      document.getElementById('f_tanggungan').value = row.jumlah_tanggungan;
      document.getElementById('f_status').value = row.status_pekerjaan;
      document.getElementById('f_kondisi').value = row.kondisi_tempat_tinggal;
      document.getElementById('f_aset').value = row.kepemilikan_aset;
      document.getElementById('f_keterangan').value = row.keterangan || '';
      document.getElementById('f_keterangan_source').value = row.keterangan_source || '';
    } else {
      title.textContent = 'Tambah Data Masyarakat';
    }
    document.getElementById('rekomendasiBox').classList.add('d-none');
    document.getElementById('rekomendasiError').classList.add('d-none');
  });

  var csrfToken = document.querySelector('#formKriteria input[name="csrf_token"]').value;

  // Cek Kelayakan: kirim form ke predict_check.php, tampilkan rekomendasi
  document.getElementById('btnCekKelayakan').addEventListener('click', function () {
    var btn = this;
    var rekomendasiBox = document.getElementById('rekomendasiBox');
    var errorBox = document.getElementById('rekomendasiError');
    rekomendasiBox.classList.add('d-none');
    errorBox.classList.add('d-none');

    var penghasilan = document.getElementById('f_penghasilan').value;
    var tanggungan = document.getElementById('f_tanggungan').value;
    var status = document.getElementById('f_status').value;
    var kondisi = document.getElementById('f_kondisi').value;
    var aset = document.getElementById('f_aset').value;

    if (!penghasilan || tanggungan === '' || !status || !kondisi || !aset) {
      errorBox.textContent = 'Lengkapi dahulu Data Kriteria (Penghasilan, Jumlah Tanggungan, Status Pekerjaan, Kondisi Tempat Tinggal, Kepemilikan Aset) sebelum cek kelayakan.';
      errorBox.classList.remove('d-none');
      return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memeriksa...';

    var body = new URLSearchParams({
      csrf_token: csrfToken,
      penghasilan: penghasilan,
      jumlah_tanggungan: tanggungan,
      status_pekerjaan: status,
      kondisi_tempat_tinggal: kondisi,
      kepemilikan_aset: aset,
    });

    fetch('predict_check.php', { method: 'POST', body: body })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.ok) {
          document.getElementById('rekomendasiHasil').textContent = data.hasil;
          document.getElementById('rekomendasiConf').textContent = data.confidence;
          rekomendasiBox.classList.remove('d-none');
        } else {
          errorBox.textContent = data.message || 'Gagal memeriksa kelayakan.';
          errorBox.classList.remove('d-none');
        }
      })
      .catch(function () {
        errorBox.textContent = 'Terjadi kesalahan koneksi. Coba lagi.';
        errorBox.classList.remove('d-none');
      })
      .finally(function () {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-lightning-charge"></i> Cek Kelayakan';
      });
  });

  // Isi Keterangan dari rekomendasi sistem, tandai sumbernya
  document.getElementById('btnGunakanHasil').addEventListener('click', function () {
    var hasil = document.getElementById('rekomendasiHasil').textContent;
    document.getElementById('f_keterangan').value = hasil;
    document.getElementById('f_keterangan_source').value = 'sistem';
  });

  // Ubah manual dropdown Keterangan dianggap keputusan survei asli
  document.getElementById('f_keterangan').addEventListener('change', function () {
    document.getElementById('f_keterangan_source').value = '';
  });
});
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
