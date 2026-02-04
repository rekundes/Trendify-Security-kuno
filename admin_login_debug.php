<?php
require_once 'config.php';
header('Content-Type: application/json');

// Restrict access to localhost only for safety
$client = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($client, ['127.0.0.1', '::1', 'localhost'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - debug only on localhost', 'client' => $client]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? $input['password'] : null;

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email required']);
    exit;
}

$data = ['success' => true, 'email' => $email];

// check user
$stmt = $conn->prepare("SELECT user_id, email, password_hash, first_name, last_name, is_admin, role, created_at FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
    $user = $res->fetch_assoc();
    unset($user['password_hash']);
    $data['user'] = $user;
} else {
    $data['user'] = null;
}
$stmt->close();

// fetch password hash separately
$ph_stmt = $conn->prepare("SELECT password_hash FROM users WHERE email = ?");
$ph_stmt->bind_param('s', $email);
$ph_stmt->execute();
$ph_res = $ph_stmt->get_result();
$pw_hash = null;
if ($ph_res && $ph_res->num_rows > 0) {
    $pw_hash = $ph_res->fetch_assoc()['password_hash'];
}
$ph_stmt->close();

if ($password !== null) {
    $data['password_provided'] = true;
    $data['password_matches'] = $pw_hash ? password_verify($password, $pw_hash) : false;
} else {
    $data['password_provided'] = false;
}

// check login_attempts
$la_stmt = $conn->prepare("SELECT failed_attempts, is_locked, locked_until FROM login_attempts WHERE email = ?");
$la_stmt->bind_param('s', $email);
$la_stmt->execute();
$la_res = $la_stmt->get_result();
if ($la_res && $la_res->num_rows > 0) {
    $la = $la_res->fetch_assoc();
    $data['login_attempts'] = $la;
    // compute time left
    if (!empty($la['is_locked']) && !empty($la['locked_until'])) {
        $now = time();
        $until = strtotime($la['locked_until']);
        $data['login_attempts']['locked_until_ts'] = $until;
        $data['login_attempts']['now_ts'] = $now;
        $data['login_attempts']['seconds_left'] = max(0, $until - $now);
    }
} else {
    $data['login_attempts'] = null;
}
$la_stmt->close();

echo json_encode($data, JSON_PRETTY_PRINT);
$conn->close();
?>