<?php
// Minimal security helpers used by auth endpoints

function validate_email($email) {
    $email = trim($email);
    if (empty($email)) return false;
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
}

function validate_string($s, $max = 255) {
    $s = trim($s);
    if ($s === '') return false;
    if (strlen($s) > $max) return false;
    return $s;
}

function validate_password($p, $min = 8, $max = 128) {
    if (!is_string($p)) return false;
    $len = strlen($p);
    if ($len < $min || $len > $max) return false;
    return $p;
}

function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

function esc($s) {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function session_touch() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['last_active'] = time();
}

function session_is_active($timeout = 1800) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['last_active'])) return false;
    return (time() - $_SESSION['last_active']) <= $timeout;
}

?>