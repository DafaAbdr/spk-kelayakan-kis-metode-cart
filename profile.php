<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('danger', 'Nama dan email harus valid.');
        header('Location: profile.php');
        exit;
    }

    $dup = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id');
    $dup->execute(['email' => $email, 'id' => $user['id']]);
    if ($dup->fetch()) {
        set_flash('danger', 'Email sudah digunakan akun lain.');
        header('Location: profile.php');
        exit;
    }

    $avatarPath = $user['avatar'];
    if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $sizeOk = $_FILES['avatar']['size'] <= 2 * 1024 * 1024;

        if (!in_array($ext, $allowedExt, true)) {
            set_flash('danger', 'Format foto harus JPG, PNG, atau WEBP.');
            header('Location: profile.php');
            exit;
        }
        if (!$sizeOk) {
            set_flash('danger', 'Ukuran foto maksimal 2MB.');
            header('Location: profile.php');
            exit;
        }

        $checkInfo = getimagesize($_FILES['avatar']['tmp_name']);
        if ($checkInfo === false) {
            set_flash('danger', 'File yang diunggah bukan gambar yang valid.');
            header('Location: profile.php');
            exit;
        }

        $destDir = __DIR__ . '/uploads/avatars';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $fileName = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
        $destPath = $destDir . '/' . $fileName;

        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destPath)) {

            if ($avatarPath && file_exists(__DIR__ . '/' . $avatarPath)) {
                @unlink(__DIR__ . '/' . $avatarPath);
            }
            $avatarPath = 'uploads/avatars/' . $fileName;
        } else {
            set_flash('danger', 'Gagal mengunggah foto profil.');
            header('Location: profile.php');
            exit;
        }
    }

    $upd = $pdo->prepare('UPDATE users SET name=:name, email=:email, bio=:bio, avatar=:avatar WHERE id=:id');
    $upd->execute(['name' => $name, 'email' => $email, 'bio' => $bio, 'avatar' => $avatarPath, 'id' => $user['id']]);

    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_avatar'] = $avatarPath ?? '';

    set_flash('success', 'Profil berhasil diperbarui.');
    header('Location: profile.php');
    exit;
}

$pageTitle   = 'Profile';
$activeMenu  = 'profile';
$pageIcon    = 'bi bi-person-badge';
$pageEyebrow = 'Account';
$pageHeading = 'Profile';
$pageDesc    = 'Kelola informasi pribadi, biodata, dan preferensi kontak Anda.';

require_once __DIR__ . '/includes/layout_top.php';
?>

<section class="row g-3">
  <div class="col-12 col-xl-4">
    <div class="panel h-100 text-center profile-card">
      <div class="profile-cover"><img src="assets/images/kes.jpg" alt="Cover profil"></div>
      <img class="avatar-img avatar-xl profile-photo" src="<?= e(avatar_src_for($user)) ?>" alt="<?= e($user['name']) ?>">
      <h2 class="h5 mt-3 mb-1"><?= e($user['name']) ?></h2>
      <p class="text-muted mb-3"><?= e($user['role']) ?></p>
      <div class="d-flex justify-content-center gap-2">
        <span class="badge text-bg-primary"><?= e($user['role']) ?></span>
        <span class="badge text-bg-success">Verified</span>
      </div>
      <div class="info-list mt-4 text-start">
        <div><span>Email</span><strong><?= e($user['email']) ?></strong></div>
        <div><span>Bergabung Sejak</span><strong><?= date('d M Y', strtotime($user['created_at'])) ?></strong></div>
        <div><span>Time Zone</span><strong>Asia/Jakarta</strong></div>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-8">
    <form class="panel needs-validation" method="post" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>
      <div class="panel-header">
        <div>
          <h2 class="h5 mb-1 section-title"><i class="bi bi-person-gear"></i><span> Profile Settings</span></h2>
          <p class="text-muted mb-0">Update your account profile and contact details.</p>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label" for="profileAvatar">Foto Profil</label>
          <div class="d-flex align-items-center gap-3">
            <img class="avatar-img avatar-md" id="avatarPreview" src="<?= e(avatar_src_for($user)) ?>" alt="Preview foto profil">
            <input class="form-control" id="profileAvatar" name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp">
          </div>
          <div class="form-text">Format JPG, PNG, atau WEBP, maksimal 2MB.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="profileName">Name</label>
          <input class="form-control" id="profileName" name="name" type="text" value="<?= e($user['name']) ?>" required>
          <div class="invalid-feedback">Name is required.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="profileEmail">Email</label>
          <input class="form-control" id="profileEmail" name="email" type="email" value="<?= e($user['email']) ?>" required>
          <div class="invalid-feedback">Enter a valid email.</div>
        </div>
        <div class="col-12">
          <label class="form-label" for="profileBio">Bio</label>
          <textarea class="form-control" id="profileBio" name="bio" rows="5"><?= e($user['bio'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="d-flex justify-content-end mt-4">
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Save Profile</button>
      </div>
    </form>
  </div>
</section>

<script>
document.getElementById('profileAvatar')?.addEventListener('change', function (e) {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = function (ev) {
    document.getElementById('avatarPreview').src = ev.target.result;
  };
  reader.readAsDataURL(file);
});
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
