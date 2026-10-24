<?php
require_once __DIR__ . '/auth_guard.php';

if (is_logged_in()) {
    if (is_admin()) {
        header('Location: dashboard.php');
    } else {
        header('Location: user_portal.php');
    }
} else {
    header('Location: login.php');
}
exit;
?>
