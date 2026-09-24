<?php

require_once "../config/koneksi.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit;
}

$full_name = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (
    $full_name === '' ||
    $username === '' ||
    $email === '' ||
    $password === ''
) {
    header("Location: register.php?error=data_tidak_lengkap");
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: register.php?error=email_tidak_valid");
    exit;
}

if ($password !== $confirm_password) {
    header("Location: register.php?error=password_tidak_sama");
    exit;
}

if (strlen($password) < 6) {
    header("Location: register.php?error=password_minimal_6");
    exit;
}

/* Cek username/email */

$stmt = $conn->prepare("
    SELECT id
    FROM users
    WHERE username = ?
       OR email = ?
    LIMIT 1
");

$stmt->bind_param(
    "ss",
    $username,
    $email
);

$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    header("Location: register.php?error=username_atau_email_sudah_ada");
    exit;
}

/* Password */

$hashed_password = password_hash(
    $password,
    PASSWORD_DEFAULT
);

/* Customer selalu customer */

$role = 'customer';
$status = 'active';

$stmt = $conn->prepare("
    INSERT INTO users
    (
        full_name,
        username,
        email,
        password,
        role,
        status
    )
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "ssssss",
    $full_name,
    $username,
    $email,
    $hashed_password,
    $role,
    $status
);

if ($stmt->execute()) {

    header(
        "Location: login.php?success=register_berhasil"
    );
} else {

    header(
        "Location: register.php?error=gagal_register"
    );
}

exit;
