<?php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

requireRole(['admin', 'staff']);

$action = $_POST['action'] ?? '';

if ($action === 'status') {

    $id = intval($_POST['id']);
    $status = $_POST['status'];

    $allowed = [
        'Menunggu',
        'Diproses',
        'Dikirim',
        'Selesai',
        'Dibatalkan'
    ];

    if (!in_array($status, $allowed)) {
        header("Location: ../orders.php?error=status_invalid");
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE pesanan
        SET status = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "si",
        $status,
        $id
    );

    $stmt->execute();

    header("Location: ../orders.php?success=status_pesanan_diubah");
    exit;
}

header("Location: ../orders.php");
