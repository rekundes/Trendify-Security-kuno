<?php
/**
 * Comprehensive Security Helpers
 * - Input Validation
 * - Password Hashing & Verification
 * - Session Management & CSRF Protection
 * - Output Encoding
 * - Secure Error Handling
 */

/* ========== INPUT VALIDATION ========== */

function validate_email($email) {
    $email = trim($email);
    if (empty($email)) return false;
    if (strlen($email) > 254) return false;
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
}

function validate_string($s, $max = 255, $min = 1) {
    $s = trim($s);
    if (strlen($s) < $min || strlen($s) > $max) return false;
    return $s;
}

function validate_password($p, $min = 8, $max = 128) {
    if (!is_string($p)) return false;
    $len = strlen($p);
    if ($len < $min || $len > $max) return false;
    // Optional: enforce strong password (uncomment if needed)
    // if (!preg_match('/[A-Z]/', $p) || !preg_match('/[a-z]/', $p) || !preg_match('/\d/', $p)) {
    //     return false;
    // }
    return $p;
}

function validate_phone($phone, $max = 20) {
    $phone = preg_replace('/[^0-9+\-\s]/', '', $phone);
    if (strlen($phone) < 7 || strlen($phone) > $max) return false;
    return $phone;
}

function validate_integer($val, $min = null, $max = null) {
    if (!is_numeric($val)) return false;
    $val = intval($val);
    if ($min !== null && $val < $min) return false;
    if ($max !== null && $val > $max) return false;
    return $val;
}

function validate_float($val, $min = null, $max = null) {
    if (!is_numeric($val)) return false;
    $val = floatval($val);
    if ($min !== null && $val < $min) return false;
    if ($max !== null && $val > $max) return false;
    return $val;
}

function validate_file_upload($file, $allowed_types = ['jpg', 'png', 'gif', 'webp'], $max_size = 5242880) {
    // $file should be from $_FILES['fieldname']
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed or file missing'];
    }
    
    if ($file['size'] > $max_size) {
        return ['ok' => false, 'error' => 'File too large (max ' . ($max_size / 1024 / 1024) . ' MB)'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_types)) {
        return ['ok' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_types)];
    }
    
    // Verify MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp'
    ];
    if (!in_array($mime, $allowed_mimes)) {
        return ['ok' => false, 'error' => 'Invalid file type (MIME check failed)'];
    }
    
    return ['ok' => true, 'ext' => $ext];
}

function sanitize_filename($filename) {
    $filename = basename($filename);
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    return $filename;
}

/* ========== PASSWORD & HASHING ========== */

function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Note: password_needs_rehash() is a built-in PHP function

/* ========== OUTPUT ENCODING ========== */

function esc($s) {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_url($url) {
    return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_attr($attr) {
    return htmlspecialchars($attr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_js($s) {
    return json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/* ========== SESSION MANAGEMENT ========== */

function secure_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        // Secure session cookie settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1); // Requires HTTPS in production
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.sid_length', 48);
        ini_set('session.sid_bits_per_character', 6);
        
        session_start();
    }
}

function session_touch($timeout = 1800) {
    if (session_status() === PHP_SESSION_NONE) secure_session_start();
    $_SESSION['last_active'] = time();
    $_SESSION['session_timeout'] = $timeout;
}

function session_is_active() {
    if (session_status() === PHP_SESSION_NONE) return false;
    if (!isset($_SESSION['last_active']) || !isset($_SESSION['session_timeout'])) {
        return false;
    }
    $timeout = $_SESSION['session_timeout'] ?? 1800;
    return (time() - $_SESSION['last_active']) <= $timeout;
}

function session_validate_user() {
    if (!session_is_active()) {
        session_destroy();
        return false;
    }
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
        return false;
    }
    return true;
}

function session_fingerprint() {
    return hash('sha256', $_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);
}

function session_verify_fingerprint() {
    if (!isset($_SESSION['fingerprint'])) {
        return false;
    }
    return $_SESSION['fingerprint'] === session_fingerprint();
}

function session_set_fingerprint() {
    $_SESSION['fingerprint'] = session_fingerprint();
}

/* ========== CSRF PROTECTION ========== */

function csrf_token_generate() {
    if (session_status() === PHP_SESSION_NONE) secure_session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_token_get() {
    if (session_status() === PHP_SESSION_NONE) secure_session_start();
    return $_SESSION['csrf_token'] ?? '';
}

function csrf_token_verify($token) {
    if (session_status() === PHP_SESSION_NONE) return false;
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/* ========== SECURE ERROR HANDLING ========== */

function secure_error($message, $code = 500) {
    // Log actual error (with details)
    error_log('[Security Error] ' . date('Y-m-d H:i:s') . ' - ' . $message);
    
    // Return generic error to user
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
    exit;
}

function secure_log($message, $category = 'general') {
    $logfile = __DIR__ . '/logs/' . date('Y-m-d') . '.log';
    if (!is_dir(dirname($logfile))) {
        mkdir(dirname($logfile), 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] [$category] $message\n";
    file_put_contents($logfile, $entry, FILE_APPEND);
}

function log_auth_attempt($email, $success, $reason = '') {
    $status = $success ? 'SUCCESS' : 'FAILED';
    $msg = "Auth attempt by $email: $status";
    if ($reason) $msg .= " ($reason)";
    secure_log($msg, 'AUTH');
}

function log_suspicious_activity($event, $details = '') {
    $msg = "SUSPICIOUS: $event";
    if ($details) $msg .= " - $details";
    secure_log($msg, 'SECURITY');
}

/* ========== RATE LIMITING ========== */

function rate_limit_check($key, $limit = 5, $window = 300) {
    // Simple in-memory rate limit (use Redis in production)
    $file = sys_get_temp_dir() . '/ratelimit_' . md5($key) . '.txt';
    $now = time();
    
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if ($now - $data['start'] > $window) {
            // Window expired, reset
            file_put_contents($file, json_encode(['count' => 1, 'start' => $now]));
            return true;
        }
        if ($data['count'] >= $limit) {
            return false;
        }
        $data['count']++;
        file_put_contents($file, json_encode($data));
    } else {
        file_put_contents($file, json_encode(['count' => 1, 'start' => $now]));
    }
    return true;
}

?>