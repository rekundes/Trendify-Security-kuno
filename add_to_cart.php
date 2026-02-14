<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
require_once 'config.php';
require_once 'security_helpers.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in", "success" => false]);
    exit;
}

// Update session touch time
session_touch(1800);

try {
    $data = json_decode(file_get_contents("php://input"), true);

    // Validate inputs
    $name = validate_string($data['name'] ?? 'Unknown', 255, 1) ?: 'Unknown Product';
    $price = validate_float(str_replace(["₱",","], "", $data['price'] ?? 0), 0);
    $img = validate_string($data['img'] ?? '', 500, 0) ?: '';
    $size = validate_string($data['size'] ?? '', 50, 0) ?: '';
    $qty = validate_integer($data['qty'] ?? 1, 1, 1000);

    if ($price === false || $qty === false) {
        echo json_encode(["error" => "Invalid input", "success" => false]);
        exit;
    }

    $user = intval($_SESSION['user_id']);

    /* Check if same product+size already exists */
    $check = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id=? AND product_name=? AND size=?");
    if (!$check) {
        echo json_encode(["error" => "Database error", "success" => false]);
        exit;
    }
    
    $check->bind_param("iss", $user, $name, $size);
    $check->execute();
    $result = $check->get_result();

    if ($row = $result->fetch_assoc()) {
        // Update quantity
        $newQty = $row['quantity'] + $qty;
        $update = $conn->prepare("UPDATE cart SET quantity=? WHERE id=?");
        if ($update) {
            $update->bind_param("ii", $newQty, $row['id']);
            $update->execute();
            $update->close();
        }
    } else {
        // Insert new
        $insert = $conn->prepare("INSERT INTO cart (user_id, product_name, price, image, size, quantity) VALUES (?, ?, ?, ?, ?, ?)");
        if ($insert) {
            $insert->bind_param("isdssi", $user, $name, $price, $img, $size, $qty);
            $insert->execute();
            $insert->close();
        }
    }
    
    $check->close();
    
    echo json_encode(["success" => true, "message" => "Item added to cart"]);
    
} catch (Exception $e) {
    log_suspicious_activity('Add to cart error', $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Error adding to cart", "success" => false]);
}

$conn->close();
?>
