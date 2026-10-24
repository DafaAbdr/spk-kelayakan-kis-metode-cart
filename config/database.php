<?php

// Koneksi database PDO ke MySQL

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_kis_cart');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('Koneksi database gagal: ' . $e->getMessage() .
        '<br>Pastikan Anda sudah membuat database "db_kis_cart" dan mengimpor file database.sql.');
}
