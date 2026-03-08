<?php
declare(strict_types=1);

session_start();

if (!empty($_SESSION['admin_user'])) {
    header('Location: admin_portal.php');
    exit;
}

header('Location: admin_login.php');
exit;
