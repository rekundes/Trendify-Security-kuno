<?php
require_once 'config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$product_name = '';
$rating = 0;
$comment = '';
$image_rating = null;
$image_path = null;

// Support both JSON body and multipart/form-data (FormData)
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $product_name = isset($data['product_name']) ? trim($data['product_name']) : '';
    $rating = isset($data['rating']) ? intval($data['rating']) : 0;
    $comment = isset($data['comment']) ? trim($data['comment']) : '';
    if (isset($data['image_rating'])) $image_rating = intval($data['image_rating']);
} else {
    // multipart/form-data
    $product_name = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    if (isset($_POST['image_rating'])) $image_rating = intval($_POST['image_rating']);

    if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $up = $_FILES['image'];
        $ext = strtolower(pathinfo($up['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allowed)) {
            $dir = __DIR__ . '/img/reviews';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $name = uniqid('r_', true) . '.' . $ext;
            $target = $dir . '/' . $name;
            if (move_uploaded_file($up['tmp_name'], $target)) {
                // store web path
                $image_path = 'img/reviews/' . $name;
            }
        }
    }
}
$user_id = intval($_SESSION['user_id']);

if (!$product_name || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Create reviews table if not exists
$create_sql = "CREATE TABLE IF NOT EXISTS product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    rating TINYINT NOT NULL,
    comment TEXT,
    image_rating TINYINT DEFAULT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($create_sql);

// Prevent duplicate review by same user for same product
$check = $conn->prepare("SELECT COUNT(*) as cnt FROM product_reviews WHERE user_id = ? AND product_name = ?");
if ($check) {
    $check->bind_param('is', $user_id, $product_name);
    $check->execute();
    $res = $check->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $count = $row['cnt'] ?? 0;
    $check->close();
    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already reviewed this product']);
        exit;
    }
}

$insert = $conn->prepare("INSERT INTO product_reviews (user_id, product_name, rating, comment, image_rating, image_path) VALUES (?, ?, ?, ?, ?, ?)");
if (!$insert) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed']);
    exit;
}
$insert->bind_param('isisis', $user_id, $product_name, $rating, $comment, $image_rating, $image_path);
$ok = $insert->execute();
$insert->close();

if ($ok) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Insert failed']);
}

?>