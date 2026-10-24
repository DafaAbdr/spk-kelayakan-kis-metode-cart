<?php

// Pendaftaran akun admin baru
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$old = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $old = ['name' => $name, 'email' => $email];

    if ($name === '' || $email === '' || $password === '') {
        $errors[] = 'Semua kolom wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email sudah terdaftar, silakan gunakan email lain.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, "Administrator")');
            $stmt->execute(['name' => $name, 'email' => $email, 'password' => $hash]);

            set_flash('success', 'Akun berhasil dibuat. Silakan login.');
            header('Location: login.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Register SPK Kelayakan KIS Desa Cicadas">
  <title>Register | SPK KIS</title>

  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="auth-body">
  <button class="icon-button theme-toggle auth-theme-toggle" type="button" data-theme-toggle aria-label="Switch color theme" title="Switch color theme">
    <i class="bi bi-moon-stars" data-theme-icon aria-hidden="true"></i>
  </button>
  <main class="auth-page">
    <section class="auth-card">
      <a class="auth-brand" href="login.php">
        <span class="brand-icon brand-icon-logo"><img src="assets/images/logo-desa.png" alt="Logo Pemerintah Desa Cicadas"></span>
        <span><strong>Desa Cicadas &mdash; Kabupaten Bogor</strong><small>Create your account.</small></span>
      </a>
      <div class="auth-visual"><img src="assets/images/kes.jpg" alt="Ilustrasi kesehatan"></div>

      <?php if ($errors): ?>
        <div class="alert alert-danger py-2 mb-3">
          <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form class="needs-validation" method="post" novalidate>
        <div class="mb-4">
          <p class="eyebrow mb-1">Secure Access</p>
          <h1 class="h3 mb-1">Register</h1>
          <p class="text-muted mb-0">Create your account.</p>
        </div>
        <div class="mb-3">
          <label class="form-label" for="registerName">Full name</label>
          <input class="form-control" id="registerName" name="name" type="text" value="<?= e($old['name']) ?>" required>
          <div class="invalid-feedback">Full name is required.</div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="registerEmail">Email address</label>
          <input class="form-control" id="registerEmail" name="email" type="email" value="<?= e($old['email']) ?>" required>
          <div class="invalid-feedback">Enter a valid email.</div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="registerPassword">Password</label>
          <input class="form-control" id="registerPassword" name="password" type="password" minlength="6" required>
          <div class="invalid-feedback">Password must be at least 6 characters.</div>
        </div>
        <div class="form-check mb-4">
          <input class="form-check-input" type="checkbox" id="terms" required>
          <label class="form-check-label" for="terms">I agree to the terms</label>
          <div class="invalid-feedback">You must agree before continuing.</div>
        </div>
        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-person-plus"></i> Create Account</button>
      </form>

      <div class="auth-footer">Already have an account? <a href="login.php">Sign in</a></div>
    </section>
  </main>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>
</body>
</html>
