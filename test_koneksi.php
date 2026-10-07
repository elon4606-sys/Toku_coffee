<?php

$host = "sql211.infinityfree.com";
$user = "if0_43109955";
$pass = "kFHvvUGil0y";
$db   = "if0_43109955_toku";
$port = 3306;

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("❌ Koneksi database gagal: " . $conn->connect_error);
}

echo "✅ Koneksi database InfinityFree berhasil!";
echo "<br>";
echo "Database: " . $db;

$conn->close();
