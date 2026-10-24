<?php

// Kerangka tampilan bersama: header, sidebar, navbar

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_login();

$currentUser = refresh_current_user($pdo);
if (!$currentUser) {
    header('Location: logout.php');
    exit;
}
$currentUserAvatarSrc = avatar_src_for($currentUser);

$pageTitle   = $pageTitle   ?? 'Dashboard';
$activeMenu  = $activeMenu  ?? 'dashboard';
$pageIcon    = $pageIcon    ?? 'bi bi-speedometer2';
$pageEyebrow = $pageEyebrow ?? 'Overview';
$pageHeading = $pageHeading ?? $pageTitle;
$pageDesc    = $pageDesc    ?? '';

$menus = [
    'dashboard'=> ['type' => 'link', 'url' => 'index.php',    'icon' => 'bi bi-speedometer2',  'label' => 'Dashboard'],
    'kriteria' => ['type' => 'link', 'url' => 'kriteria.php', 'icon' => 'bi bi-list-check',    'label' => 'Data Masyarakat'],
    'cart'     => ['type' => 'group', 'icon' => 'bi bi-diagram-3', 'label' => 'Klasifikasi CART', 'children' => [
        'training' => ['url' => 'data_training.php', 'icon' => 'bi bi-diagram-3', 'label' => 'Data Training'],
        'testing'  => ['url' => 'data_testing.php',  'icon' => 'bi bi-clipboard-check', 'label' => 'Data Testing'],
        'proses'   => ['url' => 'proses.php',   'icon' => 'bi bi-table',         'label' => 'Proses Klasifikasi'],
        'hasil'    => ['url' => 'hasil.php',    'icon' => 'bi bi-bar-chart',     'label' => 'Hasil Klasifikasi'],
    ]],
    'laporan'  => ['type' => 'link', 'url' => 'laporan.php',  'icon' => 'bi bi-file-earmark',  'label' => 'Laporan'],
    'settings' => ['type' => 'link', 'url' => 'settings.php', 'icon' => 'bi bi-gear',          'label' => 'Settings'],
];

// Kunci menu bertahap sampai syarat alur sebelumnya terpenuhi
$totalMasyarakatMenu = (int)$pdo->query('SELECT COUNT(*) FROM masyarakat')->fetchColumn();
$totalTrainSplitMenu = (int)$pdo->query("SELECT COUNT(*) FROM masyarakat WHERE dataset_split = 'training'")->fetchColumn();
$totalTestSplitMenu  = (int)$pdo->query("SELECT COUNT(*) FROM masyarakat WHERE dataset_split = 'testing'")->fetchColumn();
$totalRunsMenu       = (int)$pdo->query('SELECT COUNT(*) FROM cart_runs')->fetchColumn();

$cart = &$menus['cart']['children'];
if ($totalMasyarakatMenu === 0) {
    $cart['training']['locked'] = true;
    $cart['training']['lock_reason'] = 'Tambahkan data warga dulu di menu "Data Masyarakat".';
}
if ($totalTrainSplitMenu === 0) {
    $cart['testing']['locked'] = true;
    $cart['testing']['lock_reason'] = 'Bagi data jadi Training/Testing dulu di menu "Data Training".';
}
if ($totalTrainSplitMenu < 2 || $totalTestSplitMenu < 1) {
    $cart['proses']['locked'] = true;
    $cart['proses']['lock_reason'] = 'Bagi data jadi Training/Testing dulu di menu "Data Training".';
}
if ($totalRunsMenu === 0) {
    $cart['hasil']['locked'] = true;
    $cart['hasil']['lock_reason'] = 'Jalankan proses klasifikasi dulu di menu "Proses Klasifikasi".';
}
// Menu aktif tidak pernah dikunci, biar admin tidak terjebak
if (isset($cart[$activeMenu]['locked'])) {
    unset($cart[$activeMenu]['locked'], $cart[$activeMenu]['lock_reason']);
}
unset($cart);

// Buka grup CART otomatis kalau lagi di salah satu submenunya
$cartGroupActive = array_key_exists($activeMenu, $menus['cart']['children']);

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title><?= e($pageTitle) ?> | SPK KIS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script>
    // Simpan timezone browser ke cookie untuk format tanggal
    (function () {
      try {
        var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (tz && document.cookie.indexOf('user_tz=' + tz) === -1) {
          document.cookie = 'user_tz=' + tz + ';path=/;max-age=' + (60 * 60 * 24 * 365);
        }
      } catch (e) { /* biarkan default WIB */ }
    })();
  </script>

  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/tree.css">
</head>

<body>
<?php if (!empty($printOnlyHtml)): ?>
<div class="print-only"><?= $printOnlyHtml ?></div>
<?php endif; ?>
<div class="admin-shell">

  <aside class="admin-sidebar">
    <div class="sidebar-header">
      <div class="brand-mark">
        <span class="brand-icon brand-icon-logo"><img src="assets/images/logo-desa.png" alt="Logo Pemerintah Desa Cicadas"></span>
        <span class="brand-copy">
          <span class="brand-title">DESA CICADAS</span>
          <span class="brand-subtitle">KABUPATEN BOGOR</span>
        </span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <?php foreach ($menus as $key => $m): ?>
      <?php if (($m['type'] ?? 'link') === 'group'): ?>
      <button class="nav-link nav-group-toggle<?= $cartGroupActive ? ' active' : '' ?>" type="button"
        data-bs-toggle="collapse" data-bs-target="#navGroup<?= e($key) ?>"
        aria-expanded="<?= $cartGroupActive ? 'true' : 'false' ?>">
        <span class="nav-icon"><i class="<?= e($m['icon']) ?>"></i></span>
        <span class="nav-text"><?= e($m['label']) ?></span>
        <i class="bi bi-chevron-down nav-caret" aria-hidden="true"></i>
      </button>
      <div class="collapse<?= $cartGroupActive ? ' show' : '' ?>" id="navGroup<?= e($key) ?>">
        <div class="nav-subgroup">
          <?php foreach ($m['children'] as $childKey => $c): ?>
          <?php if (!empty($c['locked'])): ?>
          <span class="nav-link nav-sublink disabled" tabindex="-1" aria-disabled="true" title="Terkunci: <?= e($c['lock_reason']) ?>">
            <span class="nav-icon"><i class="<?= e($c['icon']) ?>"></i></span>
            <span class="nav-text"><?= e($c['label']) ?></span>
            <i class="bi bi-lock-fill nav-lock-icon" aria-hidden="true"></i>
          </span>
          <?php else: ?>
          <a class="nav-link nav-sublink<?= $activeMenu === $childKey ? ' active' : '' ?>" href="<?= e($c['url']) ?>">
            <span class="nav-icon"><i class="<?= e($c['icon']) ?>"></i></span>
            <span class="nav-text"><?= e($c['label']) ?></span>
          </a>
          <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php elseif (!empty($m['locked'])): ?>
      <span class="nav-link disabled" tabindex="-1" aria-disabled="true" title="Terkunci: <?= e($m['lock_reason']) ?>">
        <span class="nav-icon"><i class="<?= e($m['icon']) ?>"></i></span>
        <span class="nav-text"><?= e($m['label']) ?></span>
        <i class="bi bi-lock-fill nav-lock-icon" aria-hidden="true"></i>
      </span>
      <?php else: ?>
      <a class="nav-link<?= $activeMenu === $key ? ' active' : '' ?>" href="<?= e($m['url']) ?>">
        <span class="nav-icon"><i class="<?= e($m['icon']) ?>"></i></span>
        <span class="nav-text"><?= e($m['label']) ?></span>
      </a>
      <?php endif; ?>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
      <span class="status-dot"></span>
      <span class="sidebar-footer-text">System Running smoothly</span>
    </div>
  </aside>

  <div class="admin-main">

    <nav class="navbar admin-navbar navbar-expand bg-white">
      <div class="container-fluid px-3 px-lg-4">
        <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="adminSidebar" aria-expanded="true" aria-label="Toggle sidebar">
          <span></span><span></span><span></span>
        </button>

        <div class="navbar-actions ms-auto">
          <button class="icon-button theme-toggle" type="button" data-theme-toggle aria-label="Switch color theme" title="Switch color theme">
            <i class="bi bi-moon-stars" data-theme-icon aria-hidden="true"></i>
          </button>

          <div class="dropdown">
            <button class="profile-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <img class="avatar-img avatar-sm" src="<?= e($currentUserAvatarSrc) ?>" alt="<?= e($currentUser['name']) ?>">
              <span class="profile-name d-none d-sm-inline"><?= e($currentUser['name']) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="profile.php">Profile</a></li>
              <li><a class="dropdown-item" href="settings.php">Account settings</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="logout.php">Sign out</a></li>
            </ul>
          </div>
        </div>
      </div>
    </nav>

    <main class="dashboard-content">
      <div class="container-fluid px-3 px-lg-4 py-4">

        <?php if ($flash): ?>
          <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <div class="page-heading">
          <div class="page-heading-copy">
            <span class="page-icon"><i class="<?= e($pageIcon) ?>"></i></span>
            <div>
              <p class="eyebrow mb-1"><?= e($pageEyebrow) ?></p>
              <h1 class="h3 mb-1"><?= e($pageHeading) ?></h1>
              <?php if ($pageDesc): ?><p class="text-muted mb-0"><?= e($pageDesc) ?></p><?php endif; ?>
            </div>
          </div>
        </div>
