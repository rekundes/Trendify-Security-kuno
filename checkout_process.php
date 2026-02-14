<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
require_once 'config.php';
require_once 'security_helpers.php';

try {
    // Verify session
    if (!session_validate_user()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login.']);
        exit;
    }

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'No data received']);
        exit;
    }

    // Validate required fields
    $required = ['firstName','email','phone','address1','city','postcode','items'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
            exit;
        }
    }

    // Validate and sanitize all inputs
    $firstName = validate_string($data['firstName'], 100, 1);
    if (!$firstName) {
        echo json_encode(['success' => false, 'message' => 'First name is invalid']);
        exit;
    }

    $lastName = validate_string($data['lastName'] ?? '', 100, 0) ?: '';

    $email = validate_email($data['email']);
    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }

    $phone = validate_phone($data['phone']);
    if (!$phone) {
        echo json_encode(['success' => false, 'message' => 'Invalid phone number']);
        exit;
    }

    $country = validate_string($data['country'] ?? 'Philippines', 100, 1);
    $address1 = validate_string($data['address1'], 255, 1);
    $city = validate_string($data['city'], 100, 1);
    $postcode = validate_string($data['postcode'], 20, 1);

    if (!$address1 || !$city || !$postcode) {
        echo json_encode(['success' => false, 'message' => 'Address information is incomplete']);
        exit;
    }

    // Validate prices (non-negative)
    $subtotal = validate_float($data['subtotal'] ?? 0, 0);
    $delivery = validate_float($data['delivery'] ?? 0, 0);
    $total = validate_float($data['total'] ?? 0, 0);

    if ($subtotal === false || $delivery === false || $total === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid amount']);
        exit;
    }

    // Validate items array
    if (!is_array($data['items']) || count($data['items']) === 0) {
        echo json_encode(['success' => false, 'message' => 'Order must contain at least one item']);
        exit;
    }

    // Get user_id from session
    $user_id = intval($_SESSION['user_id']);

    // Build shipping address
    $shipping_address = "Address: " . $address1 . ", City: " . $city . ", Postal: " . $postcode . ", Country: " . $country;

    // Insert order with prepared statement
    $order_sql = "INSERT INTO orders 
        (user_id, first_name, last_name, email, phone, city, postcode, status, total_amount, shipping_address, order_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Processing', ?, ?, NOW())";

    $stmt = $conn->prepare($order_sql);
    if (!$stmt) {
        secure_error('Failed to prepare order statement', 500);
    }

    $stmt->bind_param("issssssds", $user_id, $firstName, $lastName, $email, $phone, $city, $postcode, $total, $shipping_address);

    if (!$stmt->execute()) {
        log_suspicious_activity('Order insertion failed', "User: $user_id, Email: $email");
        secure_error('Failed to create order', 500);
    }

    $order_id = $stmt->insert_id;
    $stmt->close();

    // Insert order items
    if (is_array($data['items']) && count($data['items']) > 0) {
        $item_sql = "INSERT INTO order_items 
            (order_id, product_name, price, quantity, product_size)
            VALUES (?, ?, ?, ?, ?)";
        $item_stmt = $conn->prepare($item_sql);
        
        if (!$item_stmt) {
            secure_error('Failed to prepare item statement', 500);
        }

        foreach ($data['items'] as $item) {
            // Validate item data
            $name = validate_string($item['name'] ?? 'Unknown', 255, 1);
            $price = validate_float($item['price'] ?? 0, 0);
            $qty = validate_integer($item['qty'] ?? $item['quantity'] ?? 1, 1, 1000);
            $size = validate_string($item['size'] ?? '', 50, 0) ?: '';

            if (!$name || $price === false || $qty === false) {
                continue; // Skip invalid items
            }
            
            $item_stmt->bind_param("isids", $order_id, $name, $price, $qty, $size);
            if (!$item_stmt->execute()) {
                log_suspicious_activity('Item insertion failed', "Order: $order_id");
            }

            // Remove item from cart if it has an ID
            if (isset($item['id'])) {
                $cart_id = validate_integer($item['id'], 1);
                if ($cart_id) {
                    $del_stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                    if ($del_stmt) {
                        $del_stmt->bind_param('ii', $cart_id, $user_id);
                        $del_stmt->execute();
                        $del_stmt->close();
                    }
                }
            }
        }
        $item_stmt->close();
    }

    log_auth_attempt($email, true, "Order created: $order_id");

    echo json_encode([
        'success' => true, 
        'message' => 'Order placed successfully',
        'order_id' => $order_id
    ]);

} catch (Exception $e) {
    log_suspicious_activity('Checkout exception', $e->getMessage());
    secure_error('Checkout failed', 500);
}

$conn->close();
?>
