<?php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

requireRole(['admin', 'staff']);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'status') {

    $id = intval($_POST['id']);
    $status = $_POST['status'];

    $allowed = [
        'active',
        'inactive',
        'blocked'
    ];

    if (!in_array($status, $allowed)) {
        header("Location: ../customers.php?error=status_invalid");
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE users
        SET status = ?
        WHERE id = ?
        AND role = 'customer'
    ");

    $stmt->bind_param(
        "si",
        $status,
        $id
    );

    $stmt->execute();

    header("Location: ../customers.php?success=status_customer_diubah");
    exit;
}

if ($action === 'hapus') {

    $id = intval($_GET['id']);

    $stmt = $conn->prepare("
        DELETE FROM users
        WHERE id = ?
        AND role = 'customer'
    ");

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: ../customers.php?success=customer_dihapus");
    } else {
        header("Location: ../customers.php?error=customer_tidak_dapat_dihapus");
    }

    exit;
}

header("Location: ../customers.php");
