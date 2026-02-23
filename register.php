<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'security_helpers.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Rate limiting - max 10 registrations per IP per hour
    $rate_key = 'register_' . $_SERVER['REMOTE_ADDR'];
    if (!rate_limit_check($rate_key, 10, 3600)) {
        log_suspicious_activity('Registration rate limit exceeded', 'IP: ' . $_SERVER['REMOTE_ADDR']);
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many registration attempts. Try again later.']);
        exit;
    }

    // Input validation
    $email = isset($input['email']) ? $input['email'] : '';
    $password = isset($input['password']) ? $input['password'] : '';
    $first_name = isset($input['first_name']) ? $input['first_name'] : '';
    $last_name = isset($input['last_name']) ? $input['last_name'] : '';

    // Validate inputs
    $email = validate_email($email);
    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }

    $password = validate_password($password, 8, 128);
    if (!$password) {
        echo json_encode(['success' => false, 'message' => 'Password must be 8-128 characters']);
        exit;
    }

    $first_name = validate_string($first_name, 100, 1);
    if (!$first_name) {
        echo json_encode(['success' => false, 'message' => 'First name is required (1-100 chars)']);
        exit;
    }

    $last_name = validate_string($last_name, 100, 0);
    if ($last_name === false) {
        echo json_encode(['success' => false, 'message' => 'Last name must be 0-100 characters']);
        exit;
    }

    // Check if email already exists
    $check_sql = "SELECT user_id FROM users WHERE email = ?";
    $check_stmt = $conn->prepare($check_sql);
    if (!$check_stmt) {
        secure_error('Database error)', 500);
    }
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        log_suspicious_activity('Duplicate registration attempt', "Email: $email");
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        $check_stmt->close();
        exit;
    }
    $check_stmt->close();

    // Hash password with strong cost
    $password_hash = hash_password($password);

    // Insert new user
    $insert_sql = "INSERT INTO users (email, password_hash, first_name, last_name, role) VALUES (?, ?, ?, ?, 'customer')";
    $insert_stmt = $conn->prepare($insert_sql);
    if (!$insert_stmt) {
        secure_error('Database error', 500);
    }
    $insert_stmt->bind_param("ssss", $email, $password_hash, $first_name, $last_name);

    if ($insert_stmt->execute()) {
        $user_id = $insert_stmt->insert_id;
        
        // Regenerate session ID for new session
        session_regenerate_id(true);
        
        // Set session variables
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $email;
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name'] = $last_name ?? '';
        $_SESSION['is_admin'] = 0;
        $_SESSION['role'] = 'customer';
        $_SESSION['login_time'] = time();
        
        // Initialize session timeout tracking (REQUIRED for session_validate_user to work)
        $_SESSION['last_active'] = time();
        $_SESSION['session_timeout'] = 1800;  // 30 minutes
        
        // Session fingerprinting
        session_set_fingerprint();
        
        // Generate CSRF token
        csrf_token_generate();
        
        log_auth_attempt($email, true, 'Registration successful');
        
        echo json_encode([
            'success' => true, 
            'message' => 'Registration successful',
            'user' => [
                'user_id' => $user_id,
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name ?? ''
            ]
        ]);
    } else {
        log_suspicious_activity('Registration insertion failed', "Email: $email");
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    }

    $insert_stmt->close();

} catch (Exception $e) {
    log_suspicious_activity('Registration exception', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}

$conn->close();
?>