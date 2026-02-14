<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');

require_once 'config.php';
require_once 'security_helpers.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Update session touch time
session_touch(1800);

try {
    $user_id = intval($_SESSION['user_id']);

    // Fetch orders for the user
    $orders_sql = "SELECT order_id, order_date, status, total_amount, shipping_address, city, postcode, first_name, last_name FROM orders WHERE user_id = ? ORDER BY order_date DESC";
    $stmt = $conn->prepare($orders_sql);
    if (!$stmt) {
        secure_error('Database error', 500);
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $orders_result = $stmt->get_result();

    $orders = [];

    while ($order = $orders_result->fetch_assoc()) {
        // fetch items for this order
        $items_sql = "SELECT product_name, product_size, quantity, price FROM order_items WHERE order_id = ?";
        $stmt2 = $conn->prepare($items_sql);
        if ($stmt2) {
            $stmt2->bind_param('i', $order['order_id']);
            $stmt2->execute();
            $items_result = $stmt2->get_result();
        $items = [];
        while ($item = $items_result->fetch_assoc()) {
            $items[] = $item;
        }

        $order['items'] = $items;
        $orders[] = $order;
    }

    echo json_encode(['success' => true, 'orders' => $orders]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

$conn->close();
?>