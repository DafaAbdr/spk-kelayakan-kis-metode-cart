<?php

// Konfigurasi timezone, session, konstanta default CART
error_reporting(E_ALL);
ini_set('display_errors', 1);

$__tz = $_COOKIE['user_tz'] ?? '';
if ($__tz === '' || !in_array($__tz, DateTimeZone::listIdentifiers(), true)) {
    $__tz = 'Asia/Jakarta';
}
date_default_timezone_set($__tz);

if (function_exists('opcache_invalidate')) {
    foreach ([__DIR__ . '/../includes/layout_top.php', __DIR__ . '/../includes/functions.php'] as $__f) {
        if (file_exists($__f)) {
            opcache_invalidate($__f, true);
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_URL', '');
define('APP_NAME', 'SPK Kelayakan KIS - Desa Cicadas');

define('CART_MAX_DEPTH_DEFAULT', 4);
define('CART_MIN_SAMPLES_SPLIT_DEFAULT', 4);
define('CART_MIN_SAMPLES_LEAF_DEFAULT', 2);