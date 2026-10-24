<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password'])) {
            set_flash('danger', 'Password saat ini salah.');
        } elseif (strlen($new) < 6) {
            set_flash('danger', 'Password baru minimal 6 karakter.');
        } elseif ($new !== $confirm) {
            set_flash('danger', 'Konfirmasi password baru tidak cocok.');
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $upd = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
            $upd->execute(['password' => $hash, 'id' => $user['id']]);
            set_flash('success', 'Password berhasil diperbarui.');
        }
    }

    if ($action === 'save_cart_params') {
        $maxDepth = max(2, min(8, (int)($_POST['cart_max_depth'] ?? 4)));
        $minSplit = max(2, min(20, (int)($_POST['cart_min_samples_split'] ?? 4)));

        $upd = $pdo->prepare('UPDATE app_settings SET setting_value = :val WHERE setting_key = :key');
        $upd->execute(['val' => $maxDepth, 'key' => 'cart_max_depth']);
        $upd->execute(['val' => $minSplit, 'key' => 'cart_min_samples_split']);

        set_flash('success', 'Parameter default CART berhasil diperbarui.');
    }

    header('Location: settings.php');
    exit;
}

$settingsRows = $pdo->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$cartMaxDepth = (int)($settingsRows['cart_max_depth'] ?? CART_MAX_DEPTH_DEFAULT);
$cartMinSplit = (int)($settingsRows['cart_min_samples_split'] ?? CART_MIN_SAMPLES_SPLIT_DEFAULT);

$pageTitle   = 'Settings';
$activeMenu  = 'settings';
$pageIcon    = 'bi bi-gear';
$pageEyebrow = 'Account';
$pageHeading = 'Settings';
$pageDesc    = 'Kelola keamanan akun dan parameter default algoritma CART.';

require_once __DIR__ . '/includes/layout_top.php';
?>

<section class="row g-3">
  <div class="col-12 col-xl-6">
    <form class="panel needs-validation" method="post" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="change_password">
      <div class="panel-header">
        <h2 class="h5 mb-1 section-title"><i class="bi bi-shield-lock"></i><span> Keamanan Akun</span></h2>
        <p class="text-muted mb-0">Perbarui password login Anda secara berkala.</p>
      </div>

      <div class="mb-3">
        <label class="form-label" for="currentPassword">Password Saat Ini</label>
        <input class="form-control" id="currentPassword" name="current_password" type="password" required>
      </div>
      <div class="mb-3">
        <label class="form-label" for="newPassword">Password Baru</label>
        <input class="form-control" id="newPassword" name="new_password" type="password" minlength="6" required>
      </div>
      <div class="mb-3">
        <label class="form-label" for="confirmPassword">Konfirmasi Password Baru</label>
        <input class="form-control" id="confirmPassword" name="confirm_password" type="password" minlength="6" required>
      </div>

      <div class="d-flex justify-content-end mt-4">
        <button class="btn btn-primary" type="submit"><i class="bi bi-key"></i> Update Password</button>
      </div>
    </form>
  </div>

  <div class="col-12 col-xl-6">
    <form class="panel needs-validation" method="post" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_cart_params">
      <div class="panel-header">
        <h2 class="h5 mb-1 section-title"><i class="bi bi-diagram-3"></i><span> Parameter Default CART</span></h2>
        <p class="text-muted mb-0">Nilai ini akan digunakan sebagai bawaan setiap kali proses klasifikasi dijalankan.</p>
      </div>

      <div class="mb-3">
        <label class="form-label" for="cartMaxDepth">Kedalaman Maksimum Pohon</label>
        <input class="form-control" id="cartMaxDepth" name="cart_max_depth" type="number" min="2" max="8" value="<?= $cartMaxDepth ?>" required>
        <div class="form-text">Semakin dalam pohon, semakin detail aturan yang terbentuk, namun berisiko overfitting.</div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="cartMinSplit">Minimum Sampel untuk Split</label>
        <input class="form-control" id="cartMinSplit" name="cart_min_samples_split" type="number" min="2" max="20" value="<?= $cartMinSplit ?>" required>
        <div class="form-text">Node dengan jumlah data di bawah nilai ini tidak akan displit lagi (menjadi daun).</div>
      </div>

      <div class="d-flex justify-content-end mt-4">
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Simpan Parameter</button>
      </div>
    </form>
  </div>

  <div class="col-12">
    <div class="panel">
      <h2 class="h5 mb-1 section-title"><i class="bi bi-palette"></i><span> Tampilan</span></h2>
      <p class="text-muted mb-3">Gunakan tombol <i class="bi bi-moon-stars"></i> pada navbar untuk beralih antara mode terang dan gelap.</p>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
