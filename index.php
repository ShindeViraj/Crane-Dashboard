<?php
/**
 * Entry point - redirect to login or dashboard
 */
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
?>
