<?php
header('Content-Type: application/json');
require_once 'config.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim($input['email']) : '';
$code = isset($input['code']) ? trim($input['code']) : '';

// Validate inputs
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit;
}

if (empty($code) || strlen($code) !== 6 || !ctype_digit($code)) {
    echo json_encode(['success' => false, 'message' => 'Invalid code']);
    exit;
}

// Check if code matches and is not expired
$check_sql = "SELECT user_id FROM users WHERE email = ? AND reset_token = ? AND reset_token_expiry > NOW()";
$check_stmt = $conn->prepare($check_sql);

if (!$check_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$check_stmt->bind_param("ss", $email, $code);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code']);
    $check_stmt->close();
    $conn->close();
    exit;
}

$check_stmt->close();

// Code is valid, don't clear it yet (will clear after password is reset)
echo json_encode(['success' => true, 'message' => 'Code verified successfully']);
$conn->close();
?>
