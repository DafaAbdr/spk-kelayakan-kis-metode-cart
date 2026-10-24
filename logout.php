<?php

// Hapus sesi login lalu kembali ke halaman login
require_once __DIR__ . '/config/config.php';
session_unset();
session_destroy();
header('Location: login.php');
exit;
