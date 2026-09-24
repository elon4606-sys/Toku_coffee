<?php

require_once "../config/koneksi.php";
require_once "../config/session.php";

requireRole(['admin', 'staff']);

/*
|--------------------------------------------------------------------------
| ID PRODUK
|--------------------------------------------------------------------------
*/

$produk_id = (int)($_GET['id'] ?? 0);

if ($produk_id <= 0) {
    header(
        "Location: products.php?error=" .
            urlencode("ID produk tidak valid.")
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| AMBIL DATA PRODUK
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        nama_produk,
        gambar
    FROM produk
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    header(
        "Location: products.php?error=" .
            urlencode("Query produk gagal: " . $conn->error)
    );
    exit;
}

$stmt->bind_param("i", $produk_id);
$stmt->execute();

$result = $stmt->get_result();
$produk = $result->fetch_assoc();

$stmt->close();

if (!$produk) {
    header(
        "Location: products.php?error=" .
            urlencode("Produk tidak ditemukan.")
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| TRANSACTION
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | HAPUS DARI KERANJANG
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE FROM keranjang
        WHERE produk_id = ?
    ");

    if (!$stmt) {
        throw new Exception(
            "Gagal menghapus data keranjang: " . $conn->error
        );
    }

    $stmt->bind_param("i", $produk_id);

    if (!$stmt->execute()) {
        throw new Exception(
            "Gagal menghapus data keranjang: " . $stmt->error
        );
    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | HAPUS DARI INVENTORY
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE FROM inventory
        WHERE produk_id = ?
    ");

    if (!$stmt) {
        throw new Exception(
            "Gagal menghapus data inventory: " . $conn->error
        );
    }

    $stmt->bind_param("i", $produk_id);

    if (!$stmt->execute()) {
        throw new Exception(
            "Gagal menghapus data inventory: " . $stmt->error
        );
    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | HAPUS DARI MUTASI STOK
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE FROM mutasi_stok
        WHERE produk_id = ?
    ");

    if (!$stmt) {
        throw new Exception(
            "Gagal menghapus data mutasi stok: " . $conn->error
        );
    }

    $stmt->bind_param("i", $produk_id);

    if (!$stmt->execute()) {
        throw new Exception(
            "Gagal menghapus data mutasi stok: " . $stmt->error
        );
    }

    $stmt->close();




    $stmt = $conn->prepare("
        DELETE FROM detail_pesanan
        WHERE produk_id = ?
    ");

    if (!$stmt) {
        throw new Exception(
            "Gagal menghapus detail pesanan: " . $conn->error
        );
    }

    $stmt->bind_param("i", $produk_id);

    if (!$stmt->execute()) {
        throw new Exception(
            "Gagal menghapus detail pesanan: " . $stmt->error
        );
    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | HAPUS PRODUK
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        DELETE FROM produk
        WHERE id = ?
    ");

    if (!$stmt) {
        throw new Exception(
            "Gagal menyiapkan penghapusan produk: " . $conn->error
        );
    }

    $stmt->bind_param("i", $produk_id);

    if (!$stmt->execute()) {
        throw new Exception(
            "Produk gagal dihapus: " . $stmt->error
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CEK APAKAH PRODUK BENAR-BENAR TERHAPUS
    |--------------------------------------------------------------------------
    */

    if ($stmt->affected_rows <= 0) {
        throw new Exception(
            "Produk gagal dihapus atau sudah tidak tersedia."
        );
    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | COMMIT TRANSACTION
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    if (!empty($produk['gambar'])) {

        $gambar = trim($produk['gambar']);



        if (
            strpos($gambar, 'upload/') === 0 &&
            strpos($gambar, '..') === false
        ) {

            $gambarPath = __DIR__ . DIRECTORY_SEPARATOR . $gambar;

            if (is_file($gambarPath)) {
                @unlink($gambarPath);
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | REDIRECT BERHASIL
    |--------------------------------------------------------------------------
    */

    header(
        "Location: products.php?success=" .
            urlencode(
                "Produk " . $produk['nama_produk'] .
                    " berhasil dihapus."
            )
    );

    exit;
} catch (Exception $e) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */

    $conn->rollback();

    header(
        "Location: products.php?error=" .
            urlencode($e->getMessage())
    );

    exit;
}
