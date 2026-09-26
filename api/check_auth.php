<?php
/**
 * Auth Status Check API Endpoint
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

init_app_session();

if (!empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id'])) {
    echo json_encode([
        'authenticated' => true,
        'user' => $_SESSION['admin_user'] ?? 'admin',
        'name' => $_SESSION['admin_name'] ?? 'System Administrator',
        'token' => $_SESSION['auth_token'] ?? ''
    ]);
} else {
    echo json_encode([
        'authenticated' => false
    ]);
}
