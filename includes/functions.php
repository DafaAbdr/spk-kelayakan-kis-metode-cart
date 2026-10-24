<?php

// Kumpulan fungsi bantu lintas halaman & jembatan ke CartTree

require_once __DIR__ . '/../classes/CartTree.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function rupiah($angka): string
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function current_user_name(): string
{
    return $_SESSION['user_name'] ?? 'Admin';
}

function current_user_email(): string
{
    return $_SESSION['user_email'] ?? '-';
}

function refresh_current_user(PDO $pdo): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    $stmt = $pdo->prepare('SELECT id, name, email, avatar FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $u = $stmt->fetch();
    if ($u) {
        $_SESSION['user_name'] = $u['name'];
        $_SESSION['user_email'] = $u['email'];
        $_SESSION['user_avatar'] = $u['avatar'] ?? '';
    }
    return $u ?: null;
}

function avatar_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        if ($p !== '') $letters .= strtoupper(substr($p, 0, 1));
    }
    return $letters !== '' ? $letters : '?';
}

function avatar_color(string $name): string
{
    $colors = ['#5b7fdb', '#34c38f', '#f1b44c', '#e35d5d', '#8e6fdb', '#4fb0d6'];
    $hash = crc32($name);
    return $colors[$hash % count($colors)];
}

function avatar_placeholder_uri(string $name): string
{
    $initials = avatar_initials($name);
    $color = avatar_color($name);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
        . '<rect width="100" height="100" rx="50" fill="' . $color . '"/>'
        . '<text x="50" y="50" font-family="Arial, sans-serif" font-size="40" fill="#ffffff" '
        . 'text-anchor="middle" dominant-baseline="central" font-weight="700">' . e($initials) . '</text>'
        . '</svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function avatar_src_for(array $userRow): string
{
    $avatar = $userRow['avatar'] ?? '';
    if ($avatar !== '' && $avatar !== null && file_exists(__DIR__ . '/../' . $avatar)) {
        return $avatar;
    }
    return avatar_placeholder_uri($userRow['name'] ?? '?');
}

function current_user_avatar(): string
{
    $avatar = $_SESSION['user_avatar'] ?? '';
    if ($avatar !== '' && file_exists(__DIR__ . '/../' . $avatar)) {
        return $avatar;
    }
    return avatar_placeholder_uri(current_user_name());
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        set_flash('danger', 'Sesi tidak valid, silakan ulangi aksi Anda.');
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
    }
}

function redirect_back(string $default = 'index.php'): void
{
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? $default));
    exit;
}

// Alur: daftar atribut prediktor dipakai fit() & predict()
function cart_feature_config(): array
{
    return [
        'penghasilan' => ['type' => 'numeric', 'label' => 'Penghasilan'],
        'jumlah_tanggungan' => ['type' => 'numeric', 'label' => 'Jumlah Tanggungan'],
        'status_pekerjaan' => ['type' => 'categorical', 'label' => 'Status Pekerjaan'],
        'kondisi_tempat_tinggal' => ['type' => 'categorical', 'label' => 'Kondisi Tempat Tinggal'],
        'kepemilikan_aset' => ['type' => 'categorical', 'label' => 'Kepemilikan Aset'],
    ];
}

// Alur: baca ulang struktur pohon tersimpan dari database
function load_tree_from_run(PDO $pdo, int $runId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM cart_nodes WHERE run_id = :run_id ORDER BY id ASC');
    $stmt->execute(['run_id' => $runId]);
    $nodeRows = $stmt->fetchAll();
    if (!$nodeRows) return null;

    $byId = [];
    $childrenOf = [];
    $rootId = null;
    foreach ($nodeRows as $row) {
        $byId[(int)$row['id']] = $row;
        if ($row['node_side'] === 'root') {
            $rootId = (int)$row['id'];
        } else {
            $childrenOf[(int)$row['parent_id']][$row['node_side']] = (int)$row['id'];
        }
    }
    if ($rootId === null) return null;

    $build = function (int $id) use (&$build, $byId, $childrenOf): array {
        $row = $byId[$id];
        $isLeaf = (bool)$row['is_leaf'];

        $splitValue = null;
        if (!$isLeaf) {
            $splitValue = $row['split_type'] === 'numeric'
                ? (float)$row['split_value']
                : ($row['split_value'] !== null ? explode('||', (string)$row['split_value']) : []);
        }

        $node = [
            'is_leaf' => $isLeaf,
            'feature' => $row['feature'],
            'feature_label' => $row['feature_label'],
            'split_type' => $row['split_type'],
            'split_value' => $splitValue,
            'predicted_class' => $row['predicted_class'],
            'confidence' => $row['confidence'] !== null ? (float)$row['confidence'] : null,
        ];

        if (!$isLeaf) {
            $leftId = $childrenOf[$id]['left'] ?? null;
            $rightId = $childrenOf[$id]['right'] ?? null;
            $node['left'] = $leftId !== null ? $build($leftId) : null;
            $node['right'] = $rightId !== null ? $build($rightId) : null;
        }

        return $node;
    };

    return $build($rootId);
}

// Alur: bangun objek CartTree siap prediksi dari run terakhir
function load_last_cart(PDO $pdo): ?array
{
    $lastRun = $pdo->query('SELECT * FROM cart_runs ORDER BY id DESC LIMIT 1')->fetch();
    if (!$lastRun) return null;

    $tree = load_tree_from_run($pdo, (int)$lastRun['id']);
    if (!$tree) return null;

    $cart = new CartTree(
        cart_feature_config(),
        'keterangan',
        (int)$lastRun['max_depth'],
        (int)$lastRun['min_samples_split'],
        CART_MIN_SAMPLES_LEAF_DEFAULT
    );
    $cart->tree = $tree;

    return ['cart' => $cart, 'run' => $lastRun];
}

// Alur: prediksi cepat tanpa simpan ke database (cek kelayakan)
function predict_raw(PDO $pdo, array $inputRow): ?array
{
    $loaded = load_last_cart($pdo);
    if (!$loaded) return null;

    $pred = $loaded['cart']->predict($inputRow);
    return ['hasil' => $pred['predicted_class'], 'confidence' => (float)$pred['confidence']];
}

// Alur: prediksi satu warga baru & simpan hasilnya
function predict_one_masyarakat(PDO $pdo, array $row): ?array
{
    $loaded = load_last_cart($pdo);
    if (!$loaded) return null;

    $pred = $loaded['cart']->predict($row);
    $runId = (int)$loaded['run']['id'];

    $pdo->prepare('INSERT INTO hasil_klasifikasi (run_id, masyarakat_id, hasil_prediksi, confidence, keterangan_aktual, is_correct)
        VALUES (:run_id, :mid, :hasil, :conf, NULL, NULL)')
        ->execute([
            'run_id' => $runId, 'mid' => $row['id'],
            'hasil' => $pred['predicted_class'], 'conf' => $pred['confidence'],
        ]);

    $pdo->prepare("UPDATE masyarakat SET keterangan = :ket, keterangan_source = 'sistem' WHERE id = :id")
        ->execute(['ket' => $pred['predicted_class'], 'id' => $row['id']]);

    return ['hasil' => $pred['predicted_class'], 'confidence' => (float)$pred['confidence']];
}

function badge_hasil(string $hasil): string
{
    $class = $hasil === 'Layak' ? 'bg-success' : 'bg-danger';
    return '<span class="badge ' . $class . '">' . e($hasil) . '</span>';
}

function badge_source(?string $source): string
{
    if ($source === 'sistem') return '<span class="badge bg-warning text-dark" title="Hasil rekomendasi/prediksi sistem, belum diverifikasi survei"><i class="bi bi-cpu"></i> Sistem</span>';
    if ($source === 'survei') return '<span class="badge bg-light text-dark border" title="Hasil pendataan/survei lapangan"><i class="bi bi-clipboard-data"></i> Survei</span>';
    return '';
}

function badge_dataset_split(?string $split, bool $berlabel): string
{
    if ($split === 'training') return '<span class="badge bg-primary">Training</span>';
    if ($split === 'testing')  return '<span class="badge bg-info text-dark">Testing</span>';
    if ($berlabel) return '<span class="badge bg-secondary">Belum dibagi</span>';
    return '<span class="badge bg-light text-muted border">Belum diprediksi</span>';
}

// Alur: bagi data survei jadi Training/Testing secara acak
function stratified_split(array $rows, float $trainRatio = 0.8): array
{
    $byClass = [];
    foreach ($rows as $r) {
        $byClass[$r['keterangan']][] = (int)$r['id'];
    }

    $training = [];
    $testing = [];

    foreach ($byClass as $ids) {
        shuffle($ids);
        $n = count($ids);
        $nTrain = (int)round($n * $trainRatio);

        if ($n >= 2 && $nTrain >= $n) $nTrain = $n - 1;

        $training = array_merge($training, array_slice($ids, 0, $nTrain));
        $testing  = array_merge($testing, array_slice($ids, $nTrain));
    }

    return ['training' => $training, 'testing' => $testing];
}

function format_tanggal_indonesia(?int $timestamp = null): string
{
    $timestamp = $timestamp ?? time();
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $namaHari = $hari[(int)date('w', $timestamp)];
    $tanggal = (int)date('j', $timestamp);
    $namaBulan = $bulan[(int)date('n', $timestamp)];
    $tahun = date('Y', $timestamp);
    return "$namaHari, $tanggal $namaBulan $tahun";
}

function format_tanggal_indonesia_ttd(?int $timestamp = null): string
{
    $timestamp = $timestamp ?? time();
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $namaHari = $hari[(int)date('w', $timestamp)];
    $tanggal = (int)date('j', $timestamp);
    $namaBulan = $bulan[(int)date('n', $timestamp)];
    $tahun = date('Y', $timestamp);
    return "$namaHari $tanggal $namaBulan $tahun";
}
