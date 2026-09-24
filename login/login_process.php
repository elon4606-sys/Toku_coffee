<?php

/* =====================================================
   LOGIN PROCESS TOKU COFFEE
===================================================== */

require_once "../config/koneksi.php";
require_once "../config/session.php";


/* =====================================================
   CEK REQUEST
===================================================== */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}


/* =====================================================
   AMBIL DATA LOGIN
===================================================== */

$login = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';


/* =====================================================
   VALIDASI DATA
===================================================== */

if ($login === '' || $password === '') {
    header("Location: login.php?error=data_kosong");
    exit;
}


/* =====================================================
   CARI USER BERDASARKAN USERNAME / EMAIL
===================================================== */

$sql = "
    SELECT
        id,
        full_name,
        username,
        email,
        password,
        role,
        status,
        failed_attempts
    FROM users
    WHERE username = ?
       OR email = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);


/* =====================================================
   CEK QUERY
===================================================== */

if (!$stmt) {
    die("Query login gagal: " . $conn->error);
}


/* =====================================================
   BIND PARAMETER
===================================================== */

$stmt->bind_param(
    "ss",
    $login,
    $login
);


/* =====================================================
   EXECUTE
===================================================== */

if (!$stmt->execute()) {

    $stmt->close();

    die("Eksekusi login gagal: " . $conn->error);
}


/* =====================================================
   AMBIL HASIL
===================================================== */

$result = $stmt->get_result();

$user = $result->fetch_assoc();

$stmt->close();


/* =====================================================
   USER TIDAK DITEMUKAN
===================================================== */

if (!$user) {

    header("Location: login.php?error=login_gagal");
    exit;
}


/* =====================================================
   CEK STATUS AKUN
===================================================== */

$status = strtolower(trim($user['status'] ?? ''));

if ($status === 'blocked') {

    header("Location: login.php?error=akun_diblokir");
    exit;
}

if ($status === 'inactive') {

    header("Location: login.php?error=akun_tidak_aktif");
    exit;
}


/* =====================================================
   CEK PASSWORD
===================================================== */

if (!password_verify($password, $user['password'])) {

    $failed = (int)$user['failed_attempts'] + 1;


    /* =================================================
       JIKA GAGAL 5 KALI → BLOKIR
    ================================================= */

    if ($failed >= 5) {

        $stmt = $conn->prepare("
            UPDATE users
            SET
                failed_attempts = ?,
                status = 'blocked'
            WHERE id = ?
        ");

        if (!$stmt) {
            die("Query blokir gagal: " . $conn->error);
        }
    } else {

        $stmt = $conn->prepare("
            UPDATE users
            SET
                failed_attempts = ?
            WHERE id = ?
        ");

        if (!$stmt) {
            die("Query failed login gagal: " . $conn->error);
        }
    }


    $stmt->bind_param(
        "ii",
        $failed,
        $user['id']
    );


    if (!$stmt->execute()) {

        $stmt->close();

        die("Update failed login gagal: " . $conn->error);
    }

    $stmt->close();


    /* =================================================
       PESAN JIKA AKUN BARU DIBLOKIR
    ================================================= */

    if ($failed >= 5) {

        header("Location: login.php?error=akun_diblokir");
        exit;
    }


    /* =================================================
       LOGIN GAGAL
    ================================================= */

    header("Location: login.php?error=login_gagal");
    exit;
}


/* =====================================================
   LOGIN BERHASIL
===================================================== */

session_regenerate_id(true);


/* =====================================================
   SIMPAN DATA USER KE SESSION
===================================================== */

$_SESSION['user_id'] = (int)$user['id'];

$_SESSION['full_name'] = $user['full_name'];

$_SESSION['username'] = $user['username'];

$_SESSION['role'] = $user['role'];


/* =====================================================
   RESET FAILED LOGIN
===================================================== */

$stmt = $conn->prepare("
    UPDATE users
    SET failed_attempts = 0
    WHERE id = ?
");


if (!$stmt) {
    die("Reset login gagal: " . $conn->error);
}


$stmt->bind_param(
    "i",
    $user['id']
);


if (!$stmt->execute()) {

    $stmt->close();

    die("Reset failed login gagal: " . $conn->error);
}


$stmt->close();


/* =====================================================
   REDIRECT BERDASARKAN ROLE
===================================================== */

$role = strtolower(trim($user['role']));


/* =====================================================
   ADMIN
===================================================== */

if ($role === 'admin') {

    header("Location: ../dashboar.php");
    exit;
}


/* =====================================================
   STAFF
===================================================== */

if ($role === 'staff') {

    header("Location: ../dashboar.php");
    exit;
}


/* =====================================================
   CUSTOMER
===================================================== */

if ($role === 'customer') {

    header("Location: ../pelanggan/index.php");
    exit;
}


/* =====================================================
   ROLE TIDAK VALID
===================================================== */

$_SESSION = [];

if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

header("Location: login.php?error=role_tidak_valid");
exit;
