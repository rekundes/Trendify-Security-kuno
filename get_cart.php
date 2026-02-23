<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
session_start();
require "config.php";
require_once __DIR__ . '/db_helper.php';

// If not logged in, return empty cart (guests can see localStorage items)
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        "success" => true,
        "items" => [],
        "item_count" => 0
    ]);
    exit;
}

$user = $_SESSION['user_id'];

$cart = [];
$item_count = 0;

$rows = db_fetch_all("SELECT id, product_name, price, image, size, quantity FROM cart WHERE user_id = ? ORDER BY added_at DESC", 'i', [$user]);
if ($rows === false) {
    echo json_encode(["success" => false, "items" => [], "item_count" => 0]);
    exit;
}

foreach ($rows as $row) {
    $item_count += $row['quantity'];
    $cart[] = [
        "id" => $row["id"],
        "name" => $row["product_name"],
        "price" => "₱" . number_format($row["price"], 0),
        "img" => $row["image"],
        "size" => $row["size"],
        "quantity" => $row["quantity"]
    ];
}

echo json_encode([
    "success" => true,
    "items" => $cart,
    "item_count" => $item_count
]);
