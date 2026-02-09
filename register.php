<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'security_helpers.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

 $email = isset($input['email']) ? $input['email'] : '';
 $password = isset($input['password']) ? $input['password'] : '';
 $first_name = isset($input['first_name']) ? $input['first_name'] : '';
 $last_name = isset($input['last_name']) ? $input['last_name'] : '';

// Validate inputs
 $email = validate_email($email);
 $password = validate_password($password, 8, 128);
 $first_name = validate_string($first_name, 100) ?: '';
 $last_name = validate_string($last_name, 100) ?: '';

// Validate input
if ($email === false || $password === false) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required or invalid']);
    exit;
}

// Check if email already exists
$check_sql = "SELECT user_id FROM users WHERE email = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("s", $email);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already registered']);
    $check_stmt->close();
    $conn->close();
    exit;
}
$check_stmt->close();

// Hash password using helper
$password_hash = hash_password($password);

// Insert new user
$insert_sql = "INSERT INTO users (email, password_hash, first_name, last_name) VALUES (?, ?, ?, ?)";
$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("ssss", $email, $password_hash, $first_name, $last_name);

if ($insert_stmt->execute()) {
    $user_id = $insert_stmt->insert_id;
    
    // Set session variables and harden session
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user_id;
    $_SESSION['email'] = $email;
    $_SESSION['first_name'] = $first_name;
    session_touch();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Registration successful',
        'user' => [
            'user_id' => $user_id,
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
}

$insert_stmt->close();
$conn->close();
?>