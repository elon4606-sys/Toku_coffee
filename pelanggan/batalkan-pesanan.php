<?php

require_once "../config/koneksi.php";
require_once "../config/session.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   CEK LOGIN
========================================================= */

if (!isset($_SESSION['user_id'])) {

    header("Location: ../login/login.php");

    exit;
}


/* =========================================================
   CEK ROLE
========================================================= */

$userId = (int) $_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';

if ($role !== 'customer') {

    header(
        "Location: ../login/login.php?error=akses_ditolak"
    );

    exit;
}


/* =========================================================
   CEK METHOD
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        "Location: akun-pelanggan.php?error=request_tidak_valid"
    );

    exit;
}


/* =========================================================
   AMBIL ID PESANAN
========================================================= */

$pesananId = isset($_POST['pesanan_id'])
    ? (int) $_POST['pesanan_id']
    : 0;


if ($pesananId <= 0) {

    header(
        "Location: akun-pelanggan.php?error=pesanan_tidak_valid"
    );

    exit;
}


/* =========================================================
   CEK PESANAN MILIK CUSTOMER
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        invoice,
        status
    FROM pesanan
    WHERE id = ?
      AND user_id = ?
    LIMIT 1
");


if (!$stmt) {

    header(
        "Location: akun-pelanggan.php?error=database"
    );

    exit;
}


$stmt->bind_param(
    "ii",
    $pesananId,
    $userId
);

$stmt->execute();

$result = $stmt->get_result();

$pesanan = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   PESANAN TIDAK DITEMUKAN
========================================================= */

if (!$pesanan) {

    header(
        "Location: akun-pelanggan.php?error=pesanan_tidak_ditemukan"
    );

    exit;
}


/* =========================================================
   CEK STATUS
========================================================= */

$status = strtolower(
    trim(
        $pesanan['status'] ?? ''
    )
);


/*
    Customer hanya boleh membatalkan
    pesanan yang masih Menunggu.
*/

if ($status !== 'menunggu') {

    header(
        "Location: akun-pelanggan.php?error=tidak_bisa_dibatalkan"
    );

    exit;
}


/* =========================================================
   UPDATE STATUS PESANAN
========================================================= */

$stmtUpdate = $conn->prepare("
    UPDATE pesanan
    SET status = 'Dibatalkan'
    WHERE id = ?
      AND user_id = ?
      AND status = 'Menunggu'
");


if (!$stmtUpdate) {

    header(
        "Location: akun-pelanggan.php?error=database"
    );

    exit;
}


$stmtUpdate->bind_param(
    "ii",
    $pesananId,
    $userId
);


if (!$stmtUpdate->execute()) {

    $stmtUpdate->close();

    header(
        "Location: akun-pelanggan.php?error=gagal_membatalkan"
    );

    exit;
}


$affectedRows = $stmtUpdate->affected_rows;

$stmtUpdate->close();


/* =========================================================
   CEK HASIL UPDATE
========================================================= */

if ($affectedRows <= 0) {

    header(
        "Location: akun-pelanggan.php?error=tidak_bisa_dibatalkan"
    );

    exit;
}


/* =========================================================
   BERHASIL
========================================================= */

header(
    "Location: akun-pelanggan.php?success=pesanan_dibatalkan"
);

exit;
