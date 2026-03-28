<?php
/**
 * auth_guard.php
 * Include this file in any page that requires the user to be logged in.
 * Redirects to login.php if no active session.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
