<?php

/*
|--------------------------------------------------------------------------
| NOTIFIKASI TOKU COFFEE ERP
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| AMBIL USER ID YANG SEDANG LOGIN
|--------------------------------------------------------------------------
*/

if (!function_exists('getCurrentUserId')) {

    function getCurrentUserId($conn)
    {
        /*
        | Session user_id
        */
        if (!empty($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }


        /*
        | Session id
        */
        if (!empty($_SESSION['id'])) {
            $_SESSION['user_id'] = (int) $_SESSION['id'];

            return (int) $_SESSION['id'];
        }


        /*
        | Jika hanya username yang tersedia
        */
        if (!empty($_SESSION['username'])) {

            $username = $_SESSION['username'];

            $stmt = $conn->prepare("
                SELECT id
                FROM users
                WHERE username = ?
                LIMIT 1
            ");

            if (!$stmt) {
                return 0;
            }

            $stmt->bind_param(
                "s",
                $username
            );

            $stmt->execute();

            $stmt->bind_result($userId);

            if ($stmt->fetch()) {

                $stmt->close();

                $_SESSION['user_id'] = (int) $userId;

                return (int) $userId;
            }

            $stmt->close();
        }

        return 0;
    }
}


/*
|--------------------------------------------------------------------------
| JUMLAH NOTIFIKASI BELUM DIBACA
|--------------------------------------------------------------------------
*/

if (!function_exists('getUnreadNotificationCount')) {

    function getUnreadNotificationCount($conn, $userId)
    {
        if ((int)$userId <= 0) {
            return 0;
        }

        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = ?
            AND is_read = 0
        ");

        if (!$stmt) {
            return 0;
        }

        $userId = (int) $userId;

        $stmt->bind_param(
            "i",
            $userId
        );

        $stmt->execute();

        $stmt->bind_result($count);

        $stmt->fetch();

        $stmt->close();

        return (int) $count;
    }
}


/*
|--------------------------------------------------------------------------
| CEK PENGATURAN NOTIFIKASI
|--------------------------------------------------------------------------
*/

if (!function_exists('isNotificationEnabled')) {

    function isNotificationEnabled($conn, $type)
    {
        $settingMap = [

            'order'   => 'notification_new_order',

            'stock'   => 'notification_low_stock',

            'payment' => 'notification_payment'

        ];


        

        if ($type === 'system') {
            return true;
        }




        if (!isset($settingMap[$type])) {
            return true;
        }


        $settingKey = $settingMap[$type];




        $stmt = $conn->prepare("
            SELECT setting_value
            FROM settings
            WHERE setting_key = ?
            LIMIT 1
        ");


        if (!$stmt) {
            return true;
        }


        $stmt->bind_param(
            "s",
            $settingKey
        );

        $stmt->execute();

        $stmt->bind_result($settingValue);


        if (!$stmt->fetch()) {

            $stmt->close();

            return true;
        }


        $stmt->close();


        return in_array(
            strtolower(trim((string)$settingValue)),
            [
                '1',
                'true',
                'yes',
                'on',
                'aktif'
            ],
            true
        );
    }
}


/*
|--------------------------------------------------------------------------
| BUAT NOTIFIKASI
|--------------------------------------------------------------------------
*/

if (!function_exists('createNotification')) {

    function createNotification(
        $conn,
        $title,
        $message,
        $type = 'system',
        $icon = 'fa-bell',
        $link = null,
        $userId = null
    ) {

        /*
        | Cek setting notifikasi
        */

        if (!isNotificationEnabled(
            $conn,
            $type
        )) {

            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | NOTIFIKASI UNTUK USER TERTENTU
        |--------------------------------------------------------------------------
        */

        if (
            $userId !== null &&
            (int)$userId > 0
        ) {

            $stmt = $conn->prepare("
                INSERT INTO notifications
                (
                    user_id,
                    title,
                    message,
                    type,
                    icon,
                    link,
                    is_read
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    0
                )
            ");


            if (!$stmt) {
                return false;
            }


            $targetUserId = (int)$userId;


            $stmt->bind_param(
                "isssss",
                $targetUserId,
                $title,
                $message,
                $type,
                $icon,
                $link
            );


            $success = $stmt->execute();


            $stmt->close();


            return $success;
        }


        /*
        |--------------------------------------------------------------------------
        | NOTIFIKASI UNTUK ADMIN DAN STAFF
        |--------------------------------------------------------------------------
        */

        $stmtUsers = $conn->prepare("
            SELECT id
            FROM users
            WHERE role IN ('admin', 'staff')
        ");


        if (!$stmtUsers) {
            return false;
        }


        $stmtUsers->execute();


        $resultUsers = $stmtUsers->get_result();


        $inserted = 0;


        while ($user = $resultUsers->fetch_assoc()) {

            $targetUserId = (int)$user['id'];


            $stmtInsert = $conn->prepare("
                INSERT INTO notifications
                (
                    user_id,
                    title,
                    message,
                    type,
                    icon,
                    link,
                    is_read
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    0
                )
            ");


            if (!$stmtInsert) {
                continue;
            }


            $stmtInsert->bind_param(
                "isssss",
                $targetUserId,
                $title,
                $message,
                $type,
                $icon,
                $link
            );


            if ($stmtInsert->execute()) {
                $inserted++;
            }


            $stmtInsert->close();
        }


        $stmtUsers->close();


        return $inserted;
    }
}
