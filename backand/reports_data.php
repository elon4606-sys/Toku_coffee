<?php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

requireRole(['admin', 'staff']);

$data = [];

/* Penjualan per bulan */

$result = $conn->query("
    SELECT
        DATE_FORMAT(tanggal_pesanan, '%Y-%m') AS bulan,
        COUNT(*) AS jumlah_pesanan,
        SUM(total) AS total_penjualan
    FROM pesanan
    WHERE status != 'Dibatalkan'
    GROUP BY DATE_FORMAT(tanggal_pesanan, '%Y-%m')
    ORDER BY bulan ASC
");

$data['penjualan_bulanan'] = [];

while ($row = $result->fetch_assoc()) {
    $data['penjualan_bulanan'][] = $row;
}

/* Produk terlaris */

$result = $conn->query("
    SELECT
        p.nama_produk,
        SUM(dp.jumlah) AS total_terjual
    FROM detail_pesanan dp
    JOIN produk p
        ON dp.produk_id = p.id
    JOIN pesanan ps
        ON dp.pesanan_id = ps.id
    WHERE ps.status != 'Dibatalkan'
    GROUP BY dp.produk_id
    ORDER BY total_terjual DESC
    LIMIT 10
");

$data['produk_terlaris'] = [];

while ($row = $result->fetch_assoc()) {
    $data['produk_terlaris'][] = $row;
}

/* Stok kritis */

$result = $conn->query("
    SELECT
        sku,
        nama_produk,
        stok,
        stok_minimum
    FROM produk
    WHERE stok <= stok_minimum
    AND status = 'aktif'
    ORDER BY stok ASC
");

$data['stok_kritis'] = [];

while ($row = $result->fetch_assoc()) {
    $data['stok_kritis'][] = $row;
}

header('Content-Type: application/json');

echo json_encode($data);
