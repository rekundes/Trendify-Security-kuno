<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'security_helpers.php';

// Get the token from request
$input = json_decode(file_get_contents('php://input'), true);
$token = isset($input['token']) ? $input['token'] : '';

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'No token provided']);
    exit;
}

// Google Client ID
$GOOGLE_CLIENT_ID = '479378742152-0jc9qqlk4ueg5a0pc0suk6mldjg2uk7k.apps.googleusercontent.com';

try {
    // Verify the token with Google
    // NOTE: In production, you should use Google's PHP client library
    // For now, this is a simplified implementation
    
    // Option 1: Using file_get_contents (requires allow_url_fopen = On)
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($token);
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 5
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to verify token']);
        exit;
    }
    
    $tokenData = json_decode($response, true);
    
    if (!isset($tokenData['aud']) || $tokenData['aud'] !== $GOOGLE_CLIENT_ID) {
        echo json_encode(['success' => false, 'message' => 'Invalid token audience']);
        exit;
    }
    
    $email = $tokenData['email'] ?? null;
    $name = $tokenData['name'] ?? '';
    $picture = $tokenData['picture'] ?? '';
    
    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'No email in token']);
        exit;
    }
    
    // Check if user exists
    $check_sql = "SELECT user_id, email, first_name, last_name FROM users WHERE email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // User exists, log them in
        $user = $result->fetch_assoc();
        $user_id = $user['user_id'];
        $check_stmt->close();
        
        // Create session
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $email;
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['is_admin'] = 0;
        $_SESSION['role'] = 'customer';
        
        // Return user data
        echo json_encode([
            'success' => true,
            'user' => [
                'user_id' => $user_id,
                'email' => $email,
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name']
            ]
        ]);
    } else {
        // Create new user from Google data
        $check_stmt->close();
        
        // Extract first and last names from Google name
        $name_parts = explode(' ', trim($name), 2);
        $first_name = $name_parts[0] ?? '';
        $last_name = $name_parts[1] ?? '';
        
        // Generate a random password for Google users
        try {
            $random_password = bin2hex(random_bytes(16));
        } catch (Exception $e) {
            $random_password = bin2hex(md5(uniqid('', true)));
        }
        
        $password_hash = hash_password($random_password);
        
        // Insert new user
        $insert_sql = "INSERT INTO users (email, password_hash, first_name, last_name, role) VALUES (?, ?, ?, ?, 'customer')";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ssss", $email, $password_hash, $first_name, $last_name);
        
        if ($insert_stmt->execute()) {
            $user_id = $insert_stmt->insert_id;
            $insert_stmt->close();
            
            // Create session
            $_SESSION['user_id'] = $user_id;
            $_SESSION['email'] = $email;
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['is_admin'] = 0;
            $_SESSION['role'] = 'customer';
            
            // Return user data
            echo json_encode([
                'success' => true,
                'user' => [
                    'user_id' => $user_id,
                    'email' => $email,
                    'first_name' => $first_name,
                    'last_name' => $last_name
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create account']);
        }
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
