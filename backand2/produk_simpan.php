<?php

require_once "config/koneksi.php";
require_once "config/session.php";

requireRole(['admin', 'staff']);

/* =========================================================
   CEK REQUEST
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: products.php");
    exit;
}


/* =========================================================
   AMBIL DATA FORM
========================================================= */

$nama_produk = trim($_POST['nama_produk'] ?? '');
$kategori_id = (int)($_POST['kategori_id'] ?? 0);
$sku         = trim($_POST['sku'] ?? '');
$harga       = (float)($_POST['harga'] ?? 0);
$stok        = (int)($_POST['stok'] ?? 0);
$deskripsi   = trim($_POST['deskripsi'] ?? '');


/* =========================================================
   VALIDASI
========================================================= */

if ($nama_produk === '') {
    header("Location: produk_tambah.php?error=Nama produk wajib diisi");
    exit;
}

if ($kategori_id <= 0) {
    header("Location: produk_tambah.php?error=Kategori wajib dipilih");
    exit;
}

if ($sku === '') {
    header("Location: produk_tambah.php?error=SKU wajib diisi");
    exit;
}

if ($harga < 0) {
    header("Location: produk_tambah.php?error=Harga tidak valid");
    exit;
}

if ($stok < 0) {
    header("Location: produk_tambah.php?error=Stok tidak valid");
    exit;
}


/* =========================================================
   CEK SKU
========================================================= */

$stmt = $conn->prepare("
    SELECT id
    FROM produk
    WHERE sku = ?
    LIMIT 1
");

if (!$stmt) {
    die("Query cek SKU gagal: " . $conn->error);
}

$stmt->bind_param("s", $sku);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $stmt->close();

    header("Location: produk_tambah.php?error=SKU sudah digunakan");
    exit;
}

$stmt->close();


/* =========================================================
   SIMPAN PRODUK
========================================================= */

$stmt = $conn->prepare("
    INSERT INTO produk
    (
        nama_produk,
        kategori_id,
        sku,
        harga,
        stok,
        deskripsi
    )
    VALUES
    (?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    die("Query simpan produk gagal: " . $conn->error);
}

$stmt->bind_param(
    "sisdis",
    $nama_produk,
    $kategori_id,
    $sku,
    $harga,
    $stok,
    $deskripsi
);


/* =========================================================
   EKSEKUSI
========================================================= */

if ($stmt->execute()) {

    $produk_id = $stmt->insert_id;

    $stmt->close();

    header("Location: products.php?success=Produk berhasil ditambahkan");
    exit;
} else {

    $error = $stmt->error;

    $stmt->close();

    die("Produk gagal disimpan: " . $error);
}
