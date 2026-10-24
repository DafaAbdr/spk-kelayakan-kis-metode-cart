<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$flash = get_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Email dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_avatar'] = $user['avatar'] ?? '';
            header('Location: index.php');
            exit;
        }

        $errors[] = 'Email atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Login SPK Kelayakan KIS Desa Cicadas">
  <title>Login | SPK KIS</title>

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
        <span><strong>Desa Cicadas &mdash; Kabupaten Bogor</strong><small>Sign in to your admin workspace.</small></span>
      </a>
      <div class="auth-visual"><img src="assets/images/kes.jpg" alt="Ilustrasi kesehatan"></div>

      <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> py-2 mb-3"><?= e($flash['message']) ?></div>
      <?php endif; ?>

      <?php if ($errors): ?>
        <div class="alert alert-danger py-2 mb-3">
          <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form class="needs-validation" method="post" novalidate>
        <div class="mb-4">
          <p class="eyebrow mb-1">Secure Access</p>
          <h1 class="h3 mb-1">Login</h1>
          <p class="text-muted mb-0">Sign in to your admin workspace.</p>
        </div>
        <div class="mb-3">
          <label class="form-label" for="loginEmail">Email address</label>
          <input class="form-control" id="loginEmail" name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>" required>
          <div class="invalid-feedback">Enter a valid email.</div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="loginPassword">Password</label>
          <input class="form-control" id="loginPassword" name="password" type="password" minlength="6" required>
          <div class="invalid-feedback">Password must be at least 6 characters.</div>
        </div>
        <div class="form-check mb-4">
          <input class="form-check-input" type="checkbox" id="rememberMe">
          <label class="form-check-label" for="rememberMe">Remember me</label>
        </div>
        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right"></i> Sign In</button>
      </form>

      <div class="auth-footer">New here? <a href="register.php">Create an account</a></div>
    </section>
  </main>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>
</body>
</html>
