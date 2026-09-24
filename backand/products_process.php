<?php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

requireRole(['admin', 'staff']);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* =========================
   TAMBAH PRODUK
========================= */

if ($action === 'tambah') {

    $kategori_id = intval($_POST['kategori_id']);
    $sku = trim($_POST['sku']);
    $nama = trim($_POST['nama_produk']);
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $harga = floatval($_POST['harga']);
    $harga_beli = floatval($_POST['harga_beli']);
    $stok = intval($_POST['stok']);
    $stok_minimum = intval($_POST['stok_minimum']);
    $satuan = trim($_POST['satuan']);

    $stmt = $conn->prepare("
        INSERT INTO produk
        (
            kategori_id,
            sku,
            nama_produk,
            deskripsi,
            harga,
            harga_beli,
            stok,
            stok_minimum,
            satuan
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isssddiis",
        $kategori_id,
        $sku,
        $nama,
        $deskripsi,
        $harga,
        $harga_beli,
        $stok,
        $stok_minimum,
        $satuan
    );

    if ($stmt->execute()) {
        header("Location: ../products.php?success=produk_ditambahkan");
    } else {
        header("Location: ../products.php?error=gagal_menambah_produk");
    }

    exit;
}

/* =========================
   HAPUS PRODUK
========================= */

if ($action === 'hapus') {

    $id = intval($_GET['id']);

    $stmt = $conn->prepare("
        DELETE FROM produk
        WHERE id = ?
    ");

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: ../products.php?success=produk_dihapus");
    } else {
        header("Location: ../products.php?error=produk_tidak_dapat_dihapus");
    }

    exit;
}

/* =========================
   UPDATE STATUS
========================= */

if ($action === 'status') {

    $id = intval($_POST['id']);
    $status = $_POST['status'];

    $stmt = $conn->prepare("
        UPDATE produk
        SET status = ?
        WHERE id = ?
    ");

    $stmt->bind_param("si", $status, $id);
    $stmt->execute();

    header("Location: ../products.php?success=status_diubah");
    exit;
}

header("Location: ../products.php");
