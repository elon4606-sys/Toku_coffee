<?php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

requireRole(['admin', 'staff']);

/*
    Data keuangan utama berasal dari tabel
    pesanan dan pembayaran.
*/

$action = $_GET['action'] ?? '';

if ($action === 'summary') {

    $data = [];

    /* Pendapatan */

    $result = $conn->query("
        SELECT COALESCE(SUM(jumlah), 0) AS total
        FROM pembayaran
        WHERE status = 'Dibayar'
    ");

    $data['pendapatan'] =
        $result->fetch_assoc()['total'];

    /* Pembelian */

    $result = $conn->query("
        SELECT COALESCE(SUM(total), 0) AS total
        FROM pembelian
        WHERE status = 'Diterima'
    ");

    $data['pembelian'] =
        $result->fetch_assoc()['total'];

    /* Profit sederhana */

    $data['profit'] =
        $data['pendapatan'] -
        $data['pembelian'];

    header(
        'Content-Type: application/json'
    );

    echo json_encode($data);

    exit;
}

header("Location: ../finance.php");
