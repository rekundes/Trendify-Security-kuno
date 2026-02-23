<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');

require_once 'config.php';
require_once 'security_helpers.php';

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validate user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

// Touch session to keep it alive
session_touch(1800);

// Get input data
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

// Input validation
$name  = validate_string($data['name'] ?? '', 200);
$price = validate_float(str_replace(["₱",","], "", $data['price'] ?? '0'), 0);
$img   = validate_string($data['img'] ?? '', 500);
$size  = validate_string($data['size'] ?? '', 50);
$qty   = validate_integer($data['qty'] ?? 1, 1, 999);

if (!$name || $price === false || !$img || !$size || $qty === false) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid product data"]);
    exit;
}

$user = $_SESSION['user_id'];

// Check if same product+size already exists
$check = $conn->prepare("
  SELECT id, quantity 
  FROM cart 
  WHERE user_id=? AND product_name=? AND size=?
");
$check->bind_param("iss", $user, $name, $size);
$check->execute();
$result = $check->get_result();

try {
    if ($row = $result->fetch_assoc()) {
        // Update quantity
        $newQty = $row['quantity'] + $qty;
        $update = $conn->prepare("UPDATE cart SET quantity=? WHERE id=?");
        $update->bind_param("ii", $newQty, $row['id']);
        if (!$update->execute()) {
            throw new Exception("Failed to update cart");
        }
    } else {
        // Insert new item
        $insert = $conn->prepare("
          INSERT INTO cart (user_id, product_name, price, image, size, quantity)
          VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insert->bind_param("isdssi", $user, $name, $price, $img, $size, $qty);
        if (!$insert->execute()) {
            throw new Exception("Failed to add to cart");
        }
    }
    
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    log_suspicious_activity("Cart error", "User ID: $user, Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Failed to update cart"]);
}
?>
