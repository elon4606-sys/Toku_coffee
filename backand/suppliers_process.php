<?php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

requireRole(['admin', 'staff']);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'tambah') {

    $nama = trim($_POST['nama_supplier']);
    $kontak = trim($_POST['kontak']);
    $email = trim($_POST['email']);
    $alamat = trim($_POST['alamat']);

    $stmt = $conn->prepare("
        INSERT INTO supplier
        (
            nama_supplier,
            kontak,
            email,
            alamat
        )
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssss",
        $nama,
        $kontak,
        $email,
        $alamat
    );

    $stmt->execute();

    header("Location: ../suppliers.php?success=supplier_ditambahkan");
    exit;
}

if ($action === 'hapus') {

    $id = intval($_GET['id']);

    $stmt = $conn->prepare("
        DELETE FROM supplier
        WHERE id = ?
    ");

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: ../suppliers.php?success=supplier_dihapus");
    } else {
        header("Location: ../suppliers.php?error=supplier_masih_digunakan");
    }

    exit;
}

header("Location: ../suppliers.php");
