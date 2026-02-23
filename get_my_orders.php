<?php
session_start();
header('Content-Type: application/json');

require 'config.php';
require_once 'security_helpers.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Keep session alive
session_touch(1800);

$user_id = (int) $_SESSION['user_id'];

try {
    // Fetch orders for the user
    $orders_sql = "SELECT order_id, order_date, status, total_amount, shipping_address, city, postcode, first_name, last_name FROM orders WHERE user_id = ? ORDER BY order_date DESC";
    $stmt = $conn->prepare($orders_sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $orders_result = $stmt->get_result();

    $orders = [];

    while ($order = $orders_result->fetch_assoc()) {
        // fetch items for this order
        $items_sql = "SELECT product_name, product_size, quantity, price FROM order_items WHERE order_id = ?";
        $stmt2 = $conn->prepare($items_sql);
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
    log_suspicious_activity("Orders fetch error", "User ID: $user_id, Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

$conn->close();
?>