<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once 'config.php';
require_once 'security_helpers.php';

try {
    // Verify session is active and not expired
    if (!session_validate_user()) {
        http_response_code(401);
        echo json_encode([
            "logged_in" => false,
            "message" => "Session expired or invalid"
        ]);
        exit;
    }

    // For mobile compatibility: Don't fail the entire session if fingerprint changes
    // Just log suspicious activity but allow continuation
    if (!session_verify_fingerprint()) {
        log_suspicious_activity('Session fingerprint mismatch - may be mobile/network change', 'User: ' . ($_SESSION['user_id'] ?? 'unknown'));
        // Update fingerprint to allow for network changes
        session_set_fingerprint();
    }

    // Update session touch time
    session_touch(1800);

    echo json_encode([
        "logged_in" => true,
        "user" => [
            "user_id" => $_SESSION['user_id'],
            "first_name" => esc($_SESSION['first_name'] ?? ''),
            "last_name" => esc($_SESSION['last_name'] ?? ''),
            "email" => esc($_SESSION['email']),
            "role" => $_SESSION['role'] ?? 'customer',
            "is_admin" => $_SESSION['is_admin'] ?? 0
        ]
    ]);

} catch (Exception $e) {
    log_suspicious_activity('Session check exception', $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "logged_in" => false,
        "message" => "Error verifying session"
    ]);
}

?>
