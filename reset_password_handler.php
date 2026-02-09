<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'security_helpers.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? $input['password'] : '';

// Validate inputs
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit;
}

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
    exit;
}

if (strlen($password) > 128) {
    echo json_encode(['success' => false, 'message' => 'Password is too long']);
    exit;
}

// Find user and verify code is still valid
$check_sql = "SELECT user_id FROM users WHERE email = ? AND reset_token IS NOT NULL AND reset_token_expiry > NOW()";
$check_stmt = $conn->prepare($check_sql);

if (!$check_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$check_stmt->bind_param("s", $email);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired verification. Please request a new password reset.']);
    $check_stmt->close();
    $conn->close();
    exit;
}

$user = $result->fetch_assoc();
$user_id = $user['user_id'];
$check_stmt->close();

// Hash the new password using security helper
$password_hash = hash_password($password);

// Update password and clear reset token
$update_sql = "UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = ?";
$update_stmt = $conn->prepare($update_sql);

if (!$update_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$update_stmt->bind_param("si", $password_hash, $user_id);

if (!$update_stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to update password']);
    $update_stmt->close();
    $conn->close();
    exit;
}

$update_stmt->close();

// Send confirmation email
$subject = "Password Reset Successful - Trendify";
$message = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #28a745; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .footer { background-color: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 5px 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>✓ Password Reset Successful</h1>
        </div>
        <div class='content'>
            <p>Hello,</p>
            <p>Your password has been successfully reset. You can now sign in with your new password.</p>
            <p><strong>If you didn't request this password reset,</strong> please contact our support team immediately as your account may have been compromised.</p>
            <p><strong>Tips to keep your account secure:</strong></p>
            <ul>
                <li>Use a strong, unique password</li>
                <li>Don't share your password with anyone</li>
                <li>Change your password regularly</li>
                <li>Be cautious of phishing emails</li>
            </ul>
        </div>
        <div class='footer'>
            <p>&copy; 2026 Trendify. All rights reserved.</p>
            <p>This is an automated email. Please do not reply directly to this message.</p>
        </div>
    </div>
</body>
</html>
";

$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=UTF-8\r\n";
$headers .= "From: noreply@trendify.local\r\n";

// Send confirmation email (don't fail if email doesn't send)
mail($email, $subject, $message, $headers);

echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
$conn->close();
?>
