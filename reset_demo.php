<?php

// Reset data demo: kosongkan tabel transaksi, akun login tidak disentuh.
// Halaman ini SENGAJA tidak ditaut di menu manapun -- akses lewat URL
// langsung saat butuh mulai demo dari kondisi bersih.
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset') {
    verify_csrf();

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['hasil_klasifikasi', 'cart_nodes', 'cart_runs', 'masyarakat'] as $table) {
        $pdo->exec("TRUNCATE TABLE `$table`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $done = true;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Reset Data Demo | SPK KIS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      font-family: -apple-system, "Segoe UI", Roboto, sans-serif;
      background: #0f172a;
      color: #e4e8f1;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0;
      padding: 20px;
    }
    .card {
      background: #1f2734;
      border: 1px solid #3a4356;
      border-radius: 12px;
      padding: 32px;
      max-width: 420px;
      width: 100%;
      text-align: center;
    }
    h1 { font-size: 1.15rem; margin: 0 0 12px; }
    p { color: #a6adbb; font-size: 0.9rem; line-height: 1.5; }
    .btn {
      display: inline-block;
      margin-top: 16px;
      padding: 10px 22px;
      border-radius: 8px;
      border: none;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
    }
    .btn-danger { background: #e35d5d; color: #fff; }
    .btn-secondary { background: #3a4356; color: #e4e8f1; margin-left: 8px; }
    .ok { color: #34c38f; }
  </style>
</head>
<body>
  <div class="card">
    <?php if ($done): ?>
      <h1 class="ok">Data demo sudah direset</h1>
      <p>Data Masyarakat, Data Training/Testing, dan seluruh riwayat Proses Klasifikasi sudah dikosongkan. Akun login tidak berubah.</p>
      <a class="btn btn-danger" href="index.php">Ke Dashboard</a>
    <?php else: ?>
      <h1>Reset Data Demo?</h1>
      <p>Ini akan MENGHAPUS PERMANEN seluruh Data Masyarakat, Data Training/Testing, dan riwayat Proses Klasifikasi. Akun login tidak terpengaruh. Tindakan ini tidak bisa dibatalkan.</p>
      <form method="post" onsubmit="return confirm('Yakin? Semua data akan hilang permanen.');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset">
        <button class="btn btn-danger" type="submit">Ya, Reset Sekarang</button>
        <a class="btn btn-secondary" href="index.php">Batal</a>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($done): ?>
  <script>
    // Bersihkan preferensi tema/sidebar browser supaya tampilan demo fresh
    try {
      localStorage.removeItem('admin.sidebarMini');
      localStorage.removeItem('admin.colorTheme');
    } catch (e) {}
  </script>
  <?php endif; ?>
</body>
</html>
