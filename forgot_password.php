<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'config.php';
require_once 'security_helpers.php';

// Optional mail config (create mail_config.php to override defaults)
$mailConfigPath = __DIR__ . '/mail_config.php';
if (file_exists($mailConfigPath)) {
    require_once $mailConfigPath;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    $email = isset($input['email']) ? trim($input['email']) : '';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
        exit;
    }

    // Check if user exists
    $check_sql = "SELECT user_id, email, first_name FROM users WHERE email = ?";
    $check_stmt = $conn->prepare($check_sql);
    if (!$check_stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        // Don't reveal whether email exists for security
        echo json_encode(['success' => true, 'email' => $email, 'message' => 'Verification code sent']);
        $check_stmt->close();
        $conn->close();
        exit;
    }

    $user = $result->fetch_assoc();
    $user_id = $user['user_id'];
    $first_name = $user['first_name'];
    $check_stmt->close();

    // Generate 6-digit verification code
    $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiry_time = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Store code
    $update_sql = "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    if (!$update_stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    $update_stmt->bind_param("ssi", $verification_code, $expiry_time, $user_id);
    if (!$update_stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to save verification code']);
        $update_stmt->close();
        $conn->close();
        exit;
    }
    $update_stmt->close();

    // Prepare email
    $subject = "Password Reset Verification Code - Trendify";
    $htmlBody = "<html><body>"
        . "<p>Hello " . htmlspecialchars($first_name ?: 'User') . ",</p>"
        . "<p>Your password reset verification code is:</p>"
        . "<div style='font-family:monospace;font-size:28px;background:#000;color:#fff;padding:10px;border-radius:4px;display:inline-block;'>" . htmlspecialchars($verification_code) . "</div>"
        . "<p>This code will expire in 10 minutes.</p>"
        . "</body></html>";

    $sent = false;

    // Try PHPMailer if available (recommended)
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            // Mail config constants should be defined in mail_config.php
            $smtpHost = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
            $smtpPort = defined('SMTP_PORT') ? SMTP_PORT : 587;
            $smtpUser = defined('SMTP_USER') ? SMTP_USER : '';
            $smtpPass = defined('SMTP_PASS') ? SMTP_PASS : '';
            $smtpSecure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';

            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $mail->SMTPSecure = $smtpSecure;
            $mail->Port = $smtpPort;

            $fromEmail = defined('MAIL_FROM') ? MAIL_FROM : $smtpUser;
            $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Trendify';

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;

            $mail->send();
            $sent = true;
        } catch (Exception $e) {
            // Log error silently and fallback
            error_log('PHPMailer error: ' . $e->getMessage());
            $sent = false;
        }
    }

    // Fallback to PHP mail() if PHPMailer not available
    if (!$sent) {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: noreply@trendify.local\r\n";
        @mail($email, $subject, $htmlBody, $headers);
    }

    // Response - do not reveal whether mail actually delivered
    $response = ['success' => true, 'email' => $email, 'message' => 'Verification code sent'];
    // For local testing only, include test code when host is localhost
    if (in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'])) {
        $response['test_code'] = $verification_code;
    }

    echo json_encode($response);
    $conn->close();

} catch (Exception $e) {
    error_log('forgot_password exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

?>

