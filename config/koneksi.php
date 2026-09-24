<?php

/* =====================================================
   KONEKSI DATABASE TOKU COFFEE
===================================================== */

$host = "localhost";
$user = "root";
$password = "";
$database = "toku_coffee";
$port = 3307;

$conn = new mysqli(
    $host,
    $user,
    $password,
    $database,
    $port
);

if ($conn->connect_error) {
    die("Koneksi database gagal: " .
        $conn->connect_error);
}

$conn->set_charset("utf8mb4");
