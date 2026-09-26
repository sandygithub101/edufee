<?php
/**
 * Session Authentication Guard
 * Protects admin pages from unauthorized access
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || empty($_SESSION['admin_id'])) {
    $_SESSION['auth_error'] = 'Please log in to access the Fee Management System.';
    header('Location: login.php');
    exit;
}
