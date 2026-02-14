<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
require_once 'config.php';
require_once 'security_helpers.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = isset($input['email']) ? trim($input['email']) : '';
    $password = isset($input['password']) ? $input['password'] : '';

    // Input validation
    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        exit;
    }

    $email = validate_email($email);
    if (!$email) {
        log_auth_attempt($email, false, 'Invalid email format');
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit;
    }

    // Rate limiting - max 5 attempts per IP per 5 minutes
    $rate_key = 'login_' . $_SERVER['REMOTE_ADDR'];
    if (!rate_limit_check($rate_key, 5, 300)) {
        log_suspicious_activity('Login rate limit exceeded', 'IP: ' . $_SERVER['REMOTE_ADDR']);
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many login attempts. Try again in 5 minutes.']);
        exit;
    }

    // Create login_attempts table if missing
    $create_table = "CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        failed_attempts INT DEFAULT 1,
        last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_locked TINYINT DEFAULT 0,
        locked_until TIMESTAMP NULL,
        UNIQUE KEY (email)
    )";
    $conn->query($create_table);

    // Check if account is locked
    $lock_sql = "SELECT is_locked, locked_until FROM login_attempts WHERE email = ?";
    $lock_stmt = $conn->prepare($lock_sql);
    if (!$lock_stmt) {
        secure_error('Database connection error', 500);
    }
    $lock_stmt->bind_param("s", $email);
    $lock_stmt->execute();
    $lock_result = $lock_stmt->get_result();

    $is_locked = false;
    $time_left = 0;

    if ($lock_result->num_rows > 0) {
        $lock_data = $lock_result->fetch_assoc();
        if (!empty($lock_data['is_locked']) && !empty($lock_data['locked_until'])) {
            $now_ts = time();
            $until_ts = strtotime($lock_data['locked_until']);
            if ($until_ts === false) {
                $until_ts = $now_ts;
            }

            if ($now_ts < $until_ts) {
                $is_locked = true;
                $time_left = $until_ts - $now_ts;
            } else {
                $reset = "UPDATE login_attempts SET failed_attempts = 0, is_locked = 0, locked_until = NULL WHERE email = ?";
                $rs = $conn->prepare($reset);
                if ($rs) {
                    $rs->bind_param("s", $email);
                    $rs->execute();
                    $rs->close();
                }
            }
        }
    }
    $lock_stmt->close();

    // Fetch user
    $sql = "SELECT user_id, email, password_hash, first_name, last_name, is_admin, role FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        secure_error('Database error', 500);
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Log failed auth attempt
        $ins = "INSERT INTO login_attempts (email, failed_attempts) VALUES (?, 1) ON DUPLICATE KEY UPDATE failed_attempts = failed_attempts + 1, last_attempt = NOW()";
        $ins_stmt = $conn->prepare($ins);
        if ($ins_stmt) {
            $ins_stmt->bind_param("s", $email);
            $ins_stmt->execute();
            $ins_stmt->close();
        }
        log_auth_attempt($email, false, 'User not found');
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        $stmt->close();
        exit;
    }

    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password_hash'])) {
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        // Set session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        // Session fingerprinting for additional security
        session_set_fingerprint();
        
        // Generate CSRF token
        csrf_token_generate();
        
        // Clear login attempts
        $clr = "UPDATE login_attempts SET failed_attempts = 0, is_locked = 0, locked_until = NULL WHERE email = ?";
        $clr_stmt = $conn->prepare($clr);
        if ($clr_stmt) {
            $clr_stmt->bind_param("s", $email);
            $clr_stmt->execute();
            $clr_stmt->close();
        }
        
        // Routes based on role
        $redirect = 'main.html';
        if ($user['role'] === 'superadmin') {
            $redirect = 'superadmin_dashboard.php';
        } elseif ($user['is_admin'] || $user['role'] === 'admin') {
            $redirect = 'admin_dashboard.php';
        }
        
        log_auth_attempt($email, true, 'Login successful');
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'redirect' => $redirect,
            'is_admin' => $user['is_admin'],
            'role' => $user['role'],
            'user' => [
                'user_id' => $user['user_id'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name']
            ]
        ]);
    } else {
        // Invalid password
        $upd = "INSERT INTO login_attempts (email, failed_attempts) VALUES (?, 1) ON DUPLICATE KEY UPDATE failed_attempts = failed_attempts + 1, last_attempt = NOW()";
        $upd_stmt = $conn->prepare($upd);
        if ($upd_stmt) {
            $upd_stmt->bind_param("s", $email);
            $upd_stmt->execute();
            $upd_stmt->close();
        }
        
        $chk = "SELECT failed_attempts FROM login_attempts WHERE email = ?";
        $chk_stmt = $conn->prepare($chk);
        if ($chk_stmt) {
            $chk_stmt->bind_param("s", $email);
            $chk_stmt->execute();
            $chk_res = $chk_stmt->get_result();
            $chk_data = $chk_res->fetch_assoc();
            $attempts = $chk_data['failed_attempts'] ?? 0;
            $chk_stmt->close();
            
            if ($attempts >= 3) {
                // Lock for 10 seconds
                $lck = "UPDATE login_attempts SET is_locked = 1, locked_until = DATE_ADD(NOW(), INTERVAL 10 SECOND) WHERE email = ?";
                $lck_stmt = $conn->prepare($lck);
                if ($lck_stmt) {
                    $lck_stmt->bind_param("s", $email);
                    $lck_stmt->execute();
                    $lck_stmt->close();
                }
                log_auth_attempt($email, false, 'Account locked after failed attempts');
                echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Account locked for 10 seconds.', 'seconds_left' => 10]);
            } else {
                $left = 3 - $attempts;
                log_auth_attempt($email, false, "Invalid password - $left attempts left");
                echo json_encode(['success' => false, 'message' => "Invalid email or password. Attempts left: $left"]);
            }
        }
    }

    $stmt->close();

} catch (Exception $e) {
    log_suspicious_activity('Login exception', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}

$conn->close();
?>
