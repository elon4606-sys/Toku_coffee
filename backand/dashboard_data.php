<?php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

requireRole(['admin', 'staff']);

$data = [];

/* TOTAL PRODUK */
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM produk
    WHERE status = 'aktif'
");

$data['total_produk'] = $result->fetch_assoc()['total'];

/* TOTAL CUSTOMER */
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'customer'
");

$data['total_customer'] = $result->fetch_assoc()['total'];

/* TOTAL PESANAN */
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM pesanan
");

$data['total_pesanan'] = $result->fetch_assoc()['total'];

/* TOTAL PENJUALAN */
$result = $conn->query("
    SELECT COALESCE(SUM(total), 0) AS total
    FROM pesanan
    WHERE status != 'Dibatalkan'
");

$data['total_penjualan'] = $result->fetch_assoc()['total'];

/* STOK MENIPIS */
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM produk
    WHERE stok <= stok_minimum
    AND status = 'aktif'
");

$data['stok_menipis'] = $result->fetch_assoc()['total'];

/* PESANAN TERBARU */

$result = $conn->query("
    SELECT
        p.id,
        p.invoice,
        u.full_name,
        p.total,
        p.status,
        p.tanggal_pesanan
    FROM pesanan p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.id DESC
    LIMIT 10
");

$data['pesanan_terbaru'] = [];

while ($row = $result->fetch_assoc()) {
    $data['pesanan_terbaru'][] = $row;
}

header('Content-Type: application/json');

echo json_encode($data);

?>
